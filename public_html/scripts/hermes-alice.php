<?php
/**
 * Hermes Alice — мост между Яндекс.Диалогами (Алисой) и Home Assistant
 * 
 * Разместить на pashaman.dev/hermes-alice.php
 * 
 * Ожидаемый формат от Алисы:
 * POST /
 * {
 *   "request": { "command": "показать траты", "original_utterance": "попроси гермеса показать траты", "type": "SimpleUtterance" },
 *   "session": { "session_id": "...", "user_id": "...", "new": true },
 *   "version": "1.0"
 * }
 */

// ⚙️ Настройки
define('HA_WEBHOOK_URL', 'https://ha.pashaman.crazedns.ru/api/webhook/hermes_command');

// Принимаем запрос от Алисы
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['request']['command'])) {
    // Проверка доступности (GET-запрос от Яндекса при проверке навыка)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        die(json_encode(['status' => 'ok']));
    }
    http_response_code(400);
    die(json_encode([
        'response' => ['text' => 'Неверный формат запроса', 'end_session' => true],
        'version' => '1.0'
    ]));
}

// Извлекаем команду
$command = trim($input['request']['command']);

// Отправляем команду в HA (асинхронно — не ждём ответа)
$haPayload = json_encode(['command' => $command]);
$ch = curl_init(HA_WEBHOOK_URL);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $haPayload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5,           // 5 сек на отправку
    CURLOPT_CONNECTTIMEOUT => 3,
]);
curl_exec($ch);
curl_close($ch);

// Отвечаем Алисе
header('Content-Type: application/json');
echo json_encode([
    'response' => [
        'text' => 'Передала гермесу',
        'end_session' => true,
    ],
    'version' => '1.0'
]);
