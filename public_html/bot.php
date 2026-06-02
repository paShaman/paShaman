<?php

loadEnv(__DIR__ . '/.env');

// --- КОНФИГУРАЦИЯ ---
define('TG_TOKEN', getenv('TG_TOKEN'));
define('DEEPSEEK_KEY', getenv('TG_TOKEN'));
define('BUSINESS_CONN_ID', getenv('BUSINESS_CONN_ID')); // Твой рабочий ID соединения

// Твой персональный Telegram ID
define('OWNER_TELEGRAM_ID', getenv('OWNER_TELEGRAM_ID'));

// Список разрешенных Telegram ID (белый список)
define('ALLOWED_TELEGRAM_IDS', [
    OWNER_TELEGRAM_ID,  // Твой ID подставляется автоматически
    223434009,  // Ильдар (sila-uma)
    224028930,  // Алёнка
    1780404823, // alpus
]);

// --- ТУМБЛЕРЫ ЛОГИРОВАНИЯ (Все выключены по твоей просьбе) ---
define('LOG_TG_DEBUG', false);     // Все входящие запросы от Telegram (tg_debug.log)
define('LOG_DEEPSEEK', false);     // Запросы и ответы от DeepSeek (deepseek_debug.log)
define('LOG_TG_ERRORS', false);    // Ошибки при отправке методов в Telegram (tg_api_errors.log)

// Получаем входящий JSON от Telegram
$input = file_get_contents('php://input');

// Отладочный лог Telegram — запишет входящий JSON, если флаг включен
if (LOG_TG_DEBUG) {
    file_put_contents('tg_debug.log', $input . PHP_EOL, FILE_APPEND);
}

$update = json_decode($input, true);

if (!$update) exit('No data');

$chat_id = null;
$text = null;
$user_id = null;
$username = 'unknown';
$reply_to_text = null;
$is_business = false;

// Универсальный перехват данных (из бизнес-чатов или прямых сообщений боту)
if (isset($update['business_message'])) {
    $chat_id = $update['business_message']['chat']['id'];
    $text = $update['business_message']['text'] ?? '';
    $user_id = $update['business_message']['from']['id'] ?? null;
    $username = $update['business_message']['from']['username'] ?? 'no_username';

    $reply_to_text = $update['business_message']['reply_to_message']['text']
        ?? $update['business_message']['reply_to_message']['caption']
        ?? null;

    $is_business = true;
} elseif (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $text = $update['message']['text'] ?? '';
    $user_id = $update['message']['from']['id'] ?? null;
    $username = $update['message']['from']['username'] ?? 'no_username';

    $reply_to_text = $update['message']['reply_to_message']['text']
        ?? $update['message']['reply_to_message']['caption']
        ?? null;
}

// Тестовая проверка на команду старта
if (strpos($text, '/start') === 0) {
    sendTelegramMessage($chat_id, "Бизнес-бот успешно настроен и готов к работе!", $is_business ? BUSINESS_CONN_ID : '');
    exit;
}

// КОМАНДА /info — выдает укороченное описание бота
if (strpos($text, '/info') === 0) {
    $info_text = "📋 *Бизнес-помощник на базе DeepSeek V4*\n\n"
        . "Превращает хаотичные сообщения и ТЗ от клиентов в аккуратные нативные чек-листы прямо в диалоге.\n\n"
        . "⚡️ *Как это работает:*\n"
        . "1. Подключи бота к бизнес-аккаунту Telegram.\n"
        . "2. Ответь (reply) на любое сообщение словом «список».\n"
        . "3. Бот мгновенно пришлет интерактивный чек-лист.\n\n"
        . "👥 *Фичи:* совместное добавление/выполнение задач, замер времени генерации.\n"
        . "🔒 Доступ только по белому списку.";

    sendTelegramMessage($chat_id, $info_text, $is_business ? BUSINESS_CONN_ID : '');
    exit;
}

// Безопасность: реагируем ТОЛЬКО на разрешенных пользователей
if (!in_array($user_id, ALLOWED_TELEGRAM_IDS) || empty($text)) {
    exit;
}

// Проверяем, является ли запрос триггером на создание списка
$is_list_request = (!empty($reply_to_text) && trim(mb_strtolower($text)) === 'список');

// Если это не реплай со словом "список" — мягко выходим
if ($is_list_request) {
    $text = $reply_to_text;
} else {
    exit;
}

// 1. Отправляем текст в DeepSeek V4 и замеряем время генерации
$result_data = askDeepSeek($text, $user_id, $username);
$ai_raw_output = $result_data['text']; // Извлекаем текст
$generation_time = $result_data['time']; // Извлекаем время

// 2. Парсим ответ в массив для Checklist
$lines = explode("\n", trim($ai_raw_output));
$checklist_entries = [];
$task_id = 1;

foreach ($lines as $line) {
    $cleaned_line = trim($line);
    if (!empty($cleaned_line)) {
        $cleaned_line = ltrim($cleaned_line, "-*•·/ \t");
        $checklist_entries[] = [
            'id' => $task_id++,
            'text' => $cleaned_line
        ];
    }
}

// 3. Отправляем результат обратно в зависимости от типа чата
if ($is_business && defined('BUSINESS_CONN_ID') && BUSINESS_CONN_ID !== '') {
    sendTelegramChecklist($chat_id, $checklist_entries, $generation_time);
} else {
    $text_output = "📋 *Список задач* (~{$generation_time}с)\n\n";
    foreach ($checklist_entries as $entry) {
        $text_output .= "⬜️ " . $entry['text'] . "\n";
    }
    sendTelegramMessage($chat_id, $text_output);
}

/**
 * Запрос к API DeepSeek с логированием
 */
function askDeepSeek(string $message, int $user_id, string $username): array {
    $start_api = microtime(true); // Замер начала запроса

    $url = 'https://api.deepseek.com/chat/completions';
    $payload = [
        'model' => 'deepseek-chat',
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

    $json_payload = json_encode($payload, JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . DEEPSEEK_KEY]);
    $response = curl_exec($ch);
    curl_close($ch);

    // --- ЛОГИРОВАНИЕ DEEPSEEK ---
    if (LOG_DEEPSEEK) {
        $ds_log = "=== " . date('Y-m-d H:i:s') . " ===" . PHP_EOL;
        $ds_log .= ">>> TO DEEPSEEK: " . $json_payload . PHP_EOL;
        $ds_log .= "<<< FROM DEEPSEEK: " . ($response ?: 'Ошибка связи / Пустой ответ cURL') . PHP_EOL . PHP_EOL;
        file_put_contents('deepseek_debug.log', $ds_log, FILE_APPEND);
    }

    if (!$response) return "Ошибка связи с DeepSeek API.";

    $res = json_decode($response, true);
    $generation_time = round(microtime(true) - $start_api, 2); // Реальное время ответа API

    // Получаем данные из API
    $total = $res['usage']['total_tokens'] ?? 0;
    $cache = $res['usage']['prompt_cache_hit_tokens'] ?? 0;

    // Платные токены = Общие - Кэшированные
    $paid_tokens = $total - $cache;

    // Логирование доверенных пользователей (кроме владельца)
    //if (in_array($user_id, ALLOWED_TELEGRAM_IDS) && $user_id !== OWNER_TELEGRAM_ID) {
        $log = sprintf("[%s] ID: %d | User: @%s | Paid: %d | Cache: %d | Time: %.2fs\n",
            date('Y-m-d H:i:s'), $user_id, $username, $paid_tokens, $cache, $generation_time);
        file_put_contents('user_requests.log', $log, FILE_APPEND);
    //}

    return ['text' => $res['choices'][0]['message']['content'] ?? "Ошибка.", 'time' => $generation_time];
}

/**
 * Отправка нативного чек-листа через sendChecklist (Telegram Business)
 */
function sendTelegramChecklist(int $chat_id, array $entries, float $generation_time): void {
    $url = 'https://api.telegram.org/bot' . TG_TOKEN . '/sendChecklist';

    $payload = [
        'business_connection_id' => BUSINESS_CONN_ID,
        'chat_id' => $chat_id,
        'checklist' => [
            'title' => "📋 Список задач (~{$generation_time}с)",
            'tasks' => $entries,
            'others_can_add_tasks' => true,
            'others_can_mark_tasks_as_done' => true
        ]
    ];

    sendCurl($url, $payload);
}

/**
 * Отправка обычного сообщения (с поддержкой бизнес-коннекта)
 */
function sendTelegramMessage(int $chat_id, string $text, string $business_conn_id = ''): void {
    $url = 'https://api.telegram.org/bot' . TG_TOKEN . '/sendMessage';
    $payload = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];

    if ($business_conn_id !== '') {
        $payload['business_connection_id'] = $business_conn_id;
    }

    sendCurl($url, $payload);
}

/**
 * Хелпер для POST запросов с логированием ошибок от API Телеграма
 */
function sendCurl(string $url, array $payload): void {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        $res_arr = json_decode($response, true);
        if (isset($res_arr['ok']) && $res_arr['ok'] === false) {
            if (LOG_TG_ERRORS) {
                $log_msg = date('Y-m-d H:i:s') . " | URL: $url | Response: " . $response . PHP_EOL;
                file_put_contents('tg_api_errors.log', $log_msg, FILE_APPEND);
            }
        }
    }
}

function loadEnv($path) {
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Убираем кавычки если есть
            $value = trim($value, '"\'');

            // Устанавливаем переменную окружения
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}