<?php

include __DIR__ . '/_env.php';

header('Content-Type: application/json; charset=utf-8');

// ---- Конфиг ----
$url = 'https://alice.pashaman.crazedns.ru/alice';

// ---- Проверка токена ----
$providedToken = $_GET['secret'] ?? '';
if (!hash_equals(getenv('HERMES_WEBHOOK_SECRET'), $providedToken)) {
    http_response_code(403);
    echo json_encode([
        'response' => ['text' => 'Доступ запрещён', 'end_session' => true],
        'version' => '1.0'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- Разбор запроса ----
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
$command = trim($input['request']['command'] ?? '');

if (!$command) {
    echo json_encode(['response' => ['text' => 'Команда не распознана', 'end_session' => true], 'version' => '1.0'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- Запрос к Hermes ----
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['command' => $command, 'alice' => $input]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_TIMEOUT_MS => 300,   // 0.3 сек — только чтобы отправить
]);
$resp = curl_exec($ch);

// Сразу отвечаем Алисе
echo json_encode([
    'response' => ['text' => 'Передала Гермесу', 'end_session' => true],
    'version' => '1.0'
], JSON_UNESCAPED_UNICODE);