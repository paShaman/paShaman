<?php

loadEnv(__DIR__ . '/../.env');

// --- ВАЛИДАЦИЯ ОБЯЗАТЕЛЬНЫХ ENV-ПЕРЕМЕННЫХ ---
$requiredEnv = ['TG_TOKEN', 'DEEPSEEK_KEY', 'OWNER_TELEGRAM_ID'];
foreach ($requiredEnv as $var) {
    if (!getenv($var)) {
        http_response_code(500);
        exit("Missing required env variable: $var");
    }
}

// --- КОНФИГУРАЦИЯ ---
define('TG_TOKEN', getenv('TG_TOKEN'));
define('DEEPSEEK_KEY', getenv('DEEPSEEK_KEY'));
define('BUSINESS_CONN_ID', getenv('BUSINESS_CONN_ID') ?: '');
define('OWNER_TELEGRAM_ID', (int)getenv('OWNER_TELEGRAM_ID'));
define('DEEPSEEK_MODEL', 'deepseek-v4-flash');

// Список дополнительных разрешенных Telegram ID (белый список)
// OWNER_TELEGRAM_ID проверяется отдельно — всегда имеет доступ
const ALLOWED_TELEGRAM_IDS = [
    223434009,  // Ильдар (sila-uma)
    224028930,  // Алёнка
    1780404823, // alpus
];

// --- ТУМБЛЕРЫ ЛОГИРОВАНИЯ ---
const LOG_TG_DEBUG = true;      // Все входящие запросы от Telegram (tg_debug.log)
const LOG_DEEPSEEK = true;      // Запросы и ответы от DeepSeek (deepseek_debug.log)
const LOG_TG_ERRORS = true;     // Ошибки при отправке методов в Telegram (tg_api_errors.log)
const LOG_USER_REQUESTS = true;  // Логирование запросов пользователей (user_requests.log)

// Устанавливаем Content-Type для ответа Telegram
header('Content-Type: application/json');

// Получаем входящий JSON от Telegram
$input = file_get_contents('php://input');

// Отладочный лог Telegram — записывает входящий JSON, если флаг включен
if (LOG_TG_DEBUG) {
    file_put_contents('tg_debug.log', $input . PHP_EOL, FILE_APPEND);
}

$update = json_decode($input, true);

if (!$update) {
    exit(json_encode(['status' => 'no_data']));
}

$chatId = null;
$text = null;
$userId = null;
$username = 'unknown';
$replyToText = null;
$isBusiness = false;

// Универсальный перехват данных (из бизнес-чатов или прямых сообщений боту)
if (isset($update['business_message'])) {
    $chatId = $update['business_message']['chat']['id'];
    $text = $update['business_message']['text'] ?? '';
    $userId = $update['business_message']['from']['id'] ?? null;
    $username = $update['business_message']['from']['username'] ?? 'no_username';

    $replyToText = $update['business_message']['reply_to_message']['text']
        ?? $update['business_message']['reply_to_message']['caption']
        ?? null;

    $isBusiness = true;
} elseif (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];
    $text = $update['message']['text'] ?? '';
    $userId = $update['message']['from']['id'] ?? null;
    $username = $update['message']['from']['username'] ?? 'no_username';

    $replyToText = $update['message']['reply_to_message']['text']
        ?? $update['message']['reply_to_message']['caption']
        ?? null;
}

// Тестовая проверка на команду старта
if (strpos($text, '/start') === 0) {
    sendTelegramMessage($chatId, "Бизнес\\-бот успешно настроен и готов к работе\!", $isBusiness ? BUSINESS_CONN_ID : '');
    exit(json_encode(['status' => 'ok']));
}

// КОМАНДА /info — выдает укороченное описание бота
if (strpos($text, '/info') === 0) {
    $infoText = "📋 *Бизнес\\-помощник на базе DeepSeek V4*\n\n"
        . "Превращает хаотичные сообщения и ТЗ от клиентов в аккуратные нативные чек\\-листы прямо в диалоге\\.\n\n"
        . "⚡️ *Как это работает:*\n"
        . "1\\. Подключи бота к бизнес\\-аккаунту Telegram\\.\n"
        . "2\\. Ответь \\(reply\\) на любое сообщение словом «список»\\.\n"
        . "3\\. Бот мгновенно пришлет интерактивный чек\\-лист\\.\n\n"
        . "👥 *Фичи:* совместное добавление/выполнение задач, замер времени генерации\\.\n"
        . "🔒 Доступ только по белому списку\\.";

    sendTelegramMessage($chatId, $infoText, $isBusiness ? BUSINESS_CONN_ID : '');
    exit(json_encode(['status' => 'ok']));
}

// Безопасность: реагируем ТОЛЬКО на разрешенных пользователей
// OWNER_TELEGRAM_ID всегда имеет доступ, плюс дополнительные ID из белого списка
$isAllowed = ($userId === OWNER_TELEGRAM_ID) || in_array($userId, ALLOWED_TELEGRAM_IDS, true);
if (!$isAllowed || empty($text)) {
    exit(json_encode(['status' => 'forbidden']));
}

// Проверяем, является ли запрос триггером на создание списка
$isListRequest = (!empty($replyToText) && trim(mb_strtolower($text)) === 'список');

// Если это не реплай со словом "список" — мягко выходим
if ($isListRequest) {
    $text = $replyToText;
} else {
    exit(json_encode(['status' => 'ignored']));
}

// 1. Отправляем текст в DeepSeek V4 и замеряем время генерации
$resultData = askDeepSeek($text, $userId, $username);
$aiRawOutput = $resultData['text'];
$generationTime = $resultData['time'];

// 2. Парсим ответ в массив для Checklist
$lines = explode("\n", trim($aiRawOutput));
$checklistEntries = [];
$taskId = 1;

foreach ($lines as $line) {
    $cleanedLine = trim($line);
    if (!empty($cleanedLine)) {
        $cleanedLine = ltrim($cleanedLine, "-*•·/ \t");
        $checklistEntries[] = [
            'id' => $taskId++,
            'text' => $cleanedLine
        ];
    }
}

// Проверка: если список пуст — сообщаем об ошибке
if (empty($checklistEntries)) {
    sendTelegramMessage(
        $chatId,
        "⚠️ Не удалось извлечь задачи из сообщения\\. Попробуйте переформулировать текст\\.",
        $isBusiness ? BUSINESS_CONN_ID : ''
    );
    exit(json_encode(['status' => 'empty_checklist']));
}

// 3. Отправляем результат обратно в зависимости от типа чата
if ($isBusiness && BUSINESS_CONN_ID !== '') {
    sendTelegramChecklist($chatId, $checklistEntries, $generationTime);
} else {
    $textOutput = "📋 *Список задач* \\(~" . escapeMarkdownV2((string)$generationTime) . "с\\)\n\n";
    foreach ($checklistEntries as $entry) {
        $textOutput .= "⬜️ " . escapeMarkdownV2($entry['text']) . "\n";
    }
    sendTelegramMessage($chatId, $textOutput);
}

exit(json_encode(['status' => 'ok']));

// ============================================================
// ФУНКЦИИ
// ============================================================

/**
 * Запрос к API DeepSeek с логированием
 */
function askDeepSeek(string $message, int $userId, string $username): array {
    $startApi = microtime(true);

    $url = 'https://api.deepseek.com/chat/completions';
    $payload = [
        'model' => DEEPSEEK_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => "Ты — утилита для структурирования задач. Твоя цель — извлечь список дел из хаотичного текста. Правила:\n1. Одна задача — одна строка.\n2. Никаких дефисов, звездочек, цифр и галочек в начале строки.\n3. Никакого вводного текста, пояснений или Markdown форматирования.\n4. Только сухой текст действий."
            ],
            [
                'role' => 'user',
                'content' => $message
            ]
        ],
        'temperature' => 0.2,
        'stream' => false
    ];

    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . DEEPSEEK_KEY
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (LOG_DEEPSEEK) {
        $dsLog = "=== " . date('Y-m-d H:i:s') . " ===" . PHP_EOL;
        $dsLog .= ">>> TO DEEPSEEK [" . DEEPSEEK_MODEL . "]: " . $jsonPayload . PHP_EOL;
        $dsLog .= "<<< FROM DEEPSEEK [HTTP $httpCode]: " . ($response ?: 'Ошибка cURL: ' . $curlError) . PHP_EOL . PHP_EOL;
        file_put_contents('deepseek_debug.log', $dsLog, FILE_APPEND);
    }

    if (!$response || $curlError) {
        return [
            'text' => "Ошибка связи с DeepSeek API.",
            'time' => round(microtime(true) - $startApi, 2)
        ];
    }

    $res = json_decode($response, true);
    $generationTime = round(microtime(true) - $startApi, 2);

    // Получаем данные из API
    $total = $res['usage']['total_tokens'] ?? 0;
    $cache = $res['usage']['prompt_cache_hit_tokens'] ?? 0;
    $paidTokens = $total - $cache;

    // Логирование запросов пользователей
    if (LOG_USER_REQUESTS) {
        $log = sprintf("[%s] ID: %d | User: @%s | Paid: %d | Cache: %d | Time: %.2fs\n",
            date('Y-m-d H:i:s'), $userId, $username, $paidTokens, $cache, $generationTime);
        file_put_contents('user_requests.log', $log, FILE_APPEND);
    }

    return [
        'text' => $res['choices'][0]['message']['content'] ?? "Ошибка: пустой ответ API.",
        'time' => $generationTime
    ];
}

/**
 * Отправка нативного чек-листа через sendChecklist (Telegram Business)
 */
function sendTelegramChecklist(int $chatId, array $entries, float $generationTime): bool {
    $url = 'https://api.telegram.org/bot' . TG_TOKEN . '/sendChecklist';

    $payload = [
        'business_connection_id' => BUSINESS_CONN_ID,
        'chat_id' => $chatId,
        'checklist' => [
            'title' => "📋 Список задач (~{$generationTime}с)",
            'tasks' => $entries,
            'others_can_add_tasks' => true,
            'others_can_mark_tasks_as_done' => true
        ]
    ];

    return sendCurl($url, $payload);
}

/**
 * Отправка обычного сообщения (с поддержкой бизнес-коннекта)
 */
function sendTelegramMessage(int $chatId, string $text, string $businessConnId = ''): bool {
    $url = 'https://api.telegram.org/bot' . TG_TOKEN . '/sendMessage';
    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'MarkdownV2'
    ];

    if ($businessConnId !== '') {
        $payload['business_connection_id'] = $businessConnId;
    }

    return sendCurl($url, $payload);
}

/**
 * Хелпер для POST запросов с логированием ошибок от API Телеграма
 * Возвращает true при успехе, false при ошибке
 */
function sendCurl(string $url, array $payload): bool {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        if (LOG_TG_ERRORS) {
            $logMsg = date('Y-m-d H:i:s') . " | URL: $url | cURL Error: $curlError" . PHP_EOL;
            file_put_contents('tg_api_errors.log', $logMsg, FILE_APPEND);
        }
        return false;
    }

    if ($response) {
        $resArr = json_decode($response, true);
        if (isset($resArr['ok']) && $resArr['ok'] === false) {
            if (LOG_TG_ERRORS) {
                $logMsg = date('Y-m-d H:i:s') . " | URL: $url | Response: " . $response . PHP_EOL;
                file_put_contents('tg_api_errors.log', $logMsg, FILE_APPEND);
            }
            return false;
        }
        return true;
    }

    return false;
}

/**
 * Экранирование спецсимволов для MarkdownV2
 * https://core.telegram.org/bots/api#markdownv2-style
 */
function escapeMarkdownV2(string $text): string {
    $specialChars = ['\\', '_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
    $escaped = array_map(function ($c) {
        return '\\' . $c;
    }, $specialChars);
    return str_replace($specialChars, $escaped, $text);
}

/**
 * Загрузка переменных окружения из .env файла
 */
function loadEnv($path): void {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Пропускаем строки-комментарии (начинающиеся с #)
        $trimmedLine = trim($line);
        if ($trimmedLine === '' || $trimmedLine[0] === '#') {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Убираем комментарии в конце строки (всё после #, если # не внутри кавычек)
            $value = preg_replace('/\s*#.*$/', '', $value);
            $value = trim($value);

            // Убираем кавычки если есть
            $value = trim($value, '"\'');

            // Устанавливаем переменную окружения
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}