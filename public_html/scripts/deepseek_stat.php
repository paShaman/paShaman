<?php

include __DIR__.'/_env.php';

// --- НАСТРОЙКИ ---
$dsApiKey   = getenv('DEEPSEEK_KEY');
$tgBotToken = getenv('TG_TOKEN_STAT');
$tgChatId   = getenv('TG_CHAT_ID');
$balanceFile = __DIR__ . '/ds_balance.json';

// 1. Запрос к DeepSeek
$ch = curl_init('https://api.deepseek.com/user/balance');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Bearer ' . trim($dsApiKey)
]);
curl_setopt($ch, CURLOPT_USERAGENT, 'curl/8.0');

$response = curl_exec($ch);
$dsData = json_decode($response, true);
curl_close($ch);

if (!isset($dsData['balance_infos'])) {
    die("Ошибка: некорректный ответ API\n");
}

// 2. Обработка всех валют
$currentBalances = [];
foreach ($dsData['balance_infos'] as $info) {
    $currentBalances[$info['currency']] = (float)$info['total_balance'];
}

// Загружаем старые данные для расчета дельты
$spentMessages = [];
$prevBalances = file_exists($balanceFile) ? json_decode(file_get_contents($balanceFile), true) : [];

foreach ($currentBalances as $currency => $amount) {
    if (isset($prevBalances[$currency]) && $prevBalances[$currency] > $amount) {
        $diff = $prevBalances[$currency] - $amount;
        $spentMessages[] = "📉 Потрачено ($currency): **" . number_format($diff, 4) . "**";
    }
}

// Сохраняем текущие балансы
file_put_contents($balanceFile, json_encode($currentBalances));

// 3. Формируем сообщение для Telegram
$message = "💰 **DeepSeek Wallet Status**\n\n";

foreach ($currentBalances as $currency => $amount) {
    $message .= "• $currency: **$amount**\n";
}

if (!empty($spentMessages)) {
    $message .= "\n" . implode("\n", $spentMessages) . "\n";
}

$message .= "\n⏰ _" . date('d.m.Y H:i') . "_";

// 4. Отправка
$tgUrl = "https://api.telegram.org/bot$tgBotToken/sendMessage";
$tgCh = curl_init($tgUrl);
curl_setopt($tgCh, CURLOPT_RETURNTRANSFER, true);
curl_setopt($tgCh, CURLOPT_POST, true);
curl_setopt($tgCh, CURLOPT_POSTFIELDS, [
    'chat_id' => $tgChatId,
    'text' => $message,
    'parse_mode' => 'Markdown'
]);
curl_exec($tgCh);
curl_close($tgCh);

echo "Балансы обновлены: " . implode(", ", array_keys($currentBalances)) . "\n";