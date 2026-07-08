<?php
$url = 'https://alice.pashaman.crazedns.ru/alice';
$input = json_decode(file_get_contents('php://input'), true);
$command = trim($input['request']['command'] ?? '');

if (!$command) {
    echo json_encode(['response' => ['text' => 'Команда не распознана', 'end_session' => true], 'version' => '1.0']);
    exit;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['command' => $command]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 4,
]);
$resp = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode == 200 && $resp) {
    $data = json_decode($resp, true);
    $text = $data['text'] ?? '';
} else {
    $text = '';
}

if ($text) {
    $alice_text = $text;
} else {
    $alice_text = 'Ответ будет отправлен в Telegram';
}

echo json_encode([
    'response' => ['text' => $alice_text, 'end_session' => true],
    'version' => '1.0'
]);