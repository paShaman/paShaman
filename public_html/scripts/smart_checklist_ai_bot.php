<?php

include __DIR__ . '/_env.php';

/*
 * @setWebhook https://api.telegram.org/ TG_TOKEN /setWebhook?url=https://paShaman.dev/scripts/smart_checklist_ai_bot.php
 * @deleteWebhook https://api.telegram.org/ TG_TOKEN /deleteWebhook?url=https://paShaman.dev/scripts/smart_checklist_ai_bot.php
 */

// --- КОНФИГУРАЦИЯ ---
define('TG_TOKEN', getenv('TG_TOKEN'));
define('TG_CHAT_ID', (int)getenv('TG_CHAT_ID'));
define('DEEPSEEK_KEY', getenv('DEEPSEEK_KEY'));
define('DEEPSEEK_MODEL', getenv('DEEPSEEK_MODEL'));

// Список дополнительных разрешенных Telegram ID (белый список)
// TG_CHAT_ID проверяется отдельно — всегда имеет доступ
const ALLOWED_TELEGRAM_IDS = [
    223434009,  // Ильдар (sila-uma)
    224028930,  // Алёнка
    1780404823, // alpus
];

// --- ТУМБЛЕРЫ ЛОГИРОВАНИЯ ---
define('LOG_TG_DEBUG', getenv('LOG_TG_DEBUG') === 'true');      // Все входящие запросы от Telegram (tg_debug.log)
define('LOG_DEEPSEEK', getenv('LOG_DEEPSEEK') === 'true');      // Запросы и ответы от DeepSeek (deepseek_debug.log)
define('LOG_TG_ERRORS', getenv('LOG_TG_ERRORS') === 'true');     // Ошибки при отправке методов в Telegram (tg_api_errors.log)
define('LOG_TG_MESSAGES', getenv('LOG_TG_MESSAGES') === 'true');
define('LOG_USER_REQUESTS', getenv('LOG_USER_REQUESTS') === 'true');  // Логирование запросов пользователей (user_requests.log)

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
$isGroup = false;
$messageId = null; // ID сообщения для reply в группах
$businessConnectionId = ''; // Динамический ID подключения из входящего сообщения

// Универсальный перехват данных (из бизнес-чатов, групповых чатов или прямых сообщений боту)
if (isset($update['business_message'])) {
    $chatId = $update['business_message']['chat']['id'];
    $text = $update['business_message']['text'] ?? '';
    $userId = $update['business_message']['from']['id'] ?? null;
    $username = $update['business_message']['from']['username'] ?? 'no_username';

    $replyToText = $update['business_message']['reply_to_message']['text']
        ?? $update['business_message']['reply_to_message']['caption']
        ?? null;

    // Извлекаем business_connection_id из входящего сообщения
    // Telegram присылает его в каждом бизнес-сообщении; у каждого бизнес-аккаунта он свой
    $businessConnectionId = $update['business_message']['business_connection_id'] ?? '';

    $isBusiness = true;
} elseif (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $chatType = $message['chat']['type'] ?? 'private';
    $text = $message['text'] ?? ($message['caption'] ?? '');
    $userId = $message['from']['id'] ?? null;
    $username = $message['from']['username'] ?? 'no_username';
    $messageId = $message['message_id'] ?? null;

    $replyToText = $message['reply_to_message']['text']
        ?? $message['reply_to_message']['caption']
        ?? null;

    // Определяем, является ли чат группой
    $isGroup = in_array($chatType, ['group', 'supergroup'], true);
}

// Тестовая проверка на команду старта
if (strpos($text, '/start') === 0) {
    sendTelegramMessage($chatId, "Бизнес\\-бот успешно настроен и готов к работе\!", $businessConnectionId);
    exit(json_encode(['status' => 'ok']));
}

// КОМАНДА /info — выдает укороченное описание бота
if (strpos($text, '/info') === 0) {
    $infoText = "📋 *Бизнес\\-помощник на базе DeepSeek V4*\n\n"
        . "Превращает хаотичные сообщения и ТЗ от клиентов в аккуратные нативные чек\\-листы\\.\n\n"
        . "⚡️ *Как это работает:*\n"
        . "1\\. Добавь бота в личный чат\\, группу или подключи к бизнес\\-аккаунту\\.\n"
        . "2\\. Ответь \\(reply\\) на любое сообщение словом «список»\\.\n"
        . "3\\. Бот мгновенно пришлет структурированный чек\\-лист\\.\n\n"
        . "👥 *Фичи:* Бизнес\\-чаты — нативные интерактивные чек\\-листы\\. Группы и личные чаты — текстовый список с reply\\.\n"
        . "🔒 Доступ только по белому списку\\.\n"
        . "⚙️ *Поддерживаемые типы чатов:* личные\\, группы\\, супергруппы и бизнес\\-чаты\\.";

    sendTelegramMessage($chatId, $infoText, $businessConnectionId);
    exit(json_encode(['status' => 'ok']));
}

if (strpos($text, '/tgid') === 0) {
    sendTelegramMessage($chatId, "🆔 {$userId}; 👤 @{$username}", $businessConnectionId);
    exit(json_encode(['status' => 'ok']));
}

// Безопасность: реагируем ТОЛЬКО на разрешенных пользователей
// TG_CHAT_ID всегда имеет доступ, плюс дополнительные ID из белого списка
$isAllowed = ($userId === TG_CHAT_ID) || in_array($userId, ALLOWED_TELEGRAM_IDS, true);
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

    if ($taskId > 30) {
        break;
    }

    if (!empty($cleanedLine)) {
        $cleanedLine = ltrim($cleanedLine, "-*•·/ \t");
        if (mb_strlen($cleanedLine) > 97) {
            $cleanedLine = mb_substr($cleanedLine, 0, 97) . '…';
        }
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
        $businessConnectionId
    );
    exit(json_encode(['status' => 'empty_checklist']));
}

// 3. Отправляем результат обратно в зависимости от типа чата
if ($isBusiness && $businessConnectionId !== '') {
    $result = sendTelegramChecklist($chatId, $checklistEntries, $generationTime, count($lines), $businessConnectionId);
} else {
    $textOutput = "📋 *Список задач* \\(\\~" . escapeMarkdownV2((string)$generationTime) . "с\\)\n\n";

    if (count($lines) > 30) {
        $textOutput .= "⚠️️ Максимум 30 задач\n";
    }

    foreach ($checklistEntries as $entry) {
        $textOutput .= "⬜️ " . escapeMarkdownV2($entry['text']) . "\n";
    }

    // В группах отвечаем reply'ем на исходное сообщение
    $replyToMsgId = $isGroup ? $messageId : null;
    $result = sendTelegramMessage($chatId, $textOutput, '', $replyToMsgId);
}

if (empty($result)) {
    sendTelegramMessage(
        $chatId,
        "⚠️ Ошибка генерации списка\\.",
        $businessConnectionId
    );
    exit(json_encode(['status' => 'error']));
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
        $log = sprintf("[%s] User: @%s | Paid: %d | Cache: %d | Time: %.2fs\n",
            date('Y-m-d H:i:s'), $username, $paidTokens, $cache, $generationTime);
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
function sendTelegramChecklist(int $chatId, array $entries, float $generationTime, int $linesCount, string $businessConnectionId): bool {
    $url = 'https://api.telegram.org/bot' . TG_TOKEN . '/sendChecklist';

    $title = "📋 Список задач (~{$generationTime}с)";

    if ($linesCount > 30) {
        $title .= ". ⚠️️ Максимум 30 задач\n";
    }

    $payload = [
        'business_connection_id' => $businessConnectionId,
        'chat_id' => $chatId,
        'checklist' => [
            'title' => $title,
            'tasks' => $entries,
            'others_can_add_tasks' => true,
            'others_can_mark_tasks_as_done' => true
        ]
    ];

    return sendCurl($url, $payload);
}

/**
 * Отправка обычного сообщения (с поддержкой бизнес-коннекта и reply)
 */
function sendTelegramMessage(int $chatId, string $text, string $businessConnId = '', ?int $replyToMsgId = null): bool {
    $url = 'https://api.telegram.org/bot' . TG_TOKEN . '/sendMessage';
    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'MarkdownV2'
    ];

    if ($businessConnId !== '') {
        $payload['business_connection_id'] = $businessConnId;
    }

    if ($replyToMsgId !== null) {
        $payload['reply_to_message_id'] = $replyToMsgId;
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

            if (LOG_TG_MESSAGES) {
                $logMsg .= print_r($payload, true) . PHP_EOL;
            }

            file_put_contents('tg_api_errors.log', $logMsg, FILE_APPEND);
        }
        return false;
    }

    if ($response) {
        $resArr = json_decode($response, true);
        if (isset($resArr['ok']) && $resArr['ok'] === false) {
            if (LOG_TG_ERRORS) {
                $logMsg = date('Y-m-d H:i:s') . " | URL: $url | Response: " . $response . PHP_EOL;

                if (LOG_TG_MESSAGES) {
                    $logMsg .= print_r($payload, true) . PHP_EOL;
                }

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