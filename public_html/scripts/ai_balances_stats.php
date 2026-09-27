<?php

include __DIR__.'/_env.php';
require_once __DIR__ . '/clients/deepseek_client.php';
require_once __DIR__ . '/clients/openrouter_client.php';
require_once __DIR__ . '/clients/telegram_client.php';

// --- НАСТРОЙКИ ---
$tgBotToken = getenv('TG_TOKEN_STAT');
$tgChatId   = getenv('TG_CHAT_ID');
$balanceFile = __DIR__ . '/ai_balance.json';

// Провайдеры: подпись => функция, возвращающая [валюта => остаток]
$providers = [
    'DeepSeek' => static function (): array {
        $data = (new DeepSeekClient())->balance();
        if (!isset($data['balance_infos'])) {
            throw new DeepSeekException('некорректный ответ API');
        }

        $balances = [];
        foreach ($data['balance_infos'] as $info) {
            $balances[$info['currency']] = (float) $info['total_balance'];
        }

        return $balances;
    },
    'OpenRouter' => static function (): array {
        $balance = (new OpenRouterClient())->balance();

        return [$balance['currency'] => $balance['remaining']];
    },
];

// Загружаем старые данные для расчета дельты
$prevBalances = file_exists($balanceFile)
    ? (json_decode(file_get_contents($balanceFile), true) ?: [])
    : [];

// 1. Опрашиваем балансы всех провайдеров
$currentBalances = [];
$spentMessages = [];
$errors = [];

foreach ($providers as $label => $fetch) {
    try {
        $balances = $fetch();
    } catch (Throwable $e) {
        $errors[] = "$label: " . $e->getMessage();
        continue;
    }

    foreach ($balances as $currency => $amount) {
        $currentBalances[$label][$currency] = $amount;

        $prev = $prevBalances[$label][$currency] ?? null;
        if ($prev !== null && $prev > $amount) {
            $spentMessages[] = "📉 $label ($currency): **" . number_format($prev - $amount, 4) . "**";
        }
    }
}

// 2. Сохраняем текущие балансы
if ($currentBalances) {
    file_put_contents($balanceFile, json_encode($currentBalances));
}

// 3. Формируем сообщение для Telegram
$message = "💰 **AI Wallets Status**\n";

foreach ($currentBalances as $label => $balances) {
    $message .= "\n**$label**\n";
    foreach ($balances as $currency => $amount) {
        $message .= "• $currency: **$amount**\n";
    }
}

if (!empty($spentMessages)) {
    $message .= "\n" . implode("\n", $spentMessages) . "\n";
}

if (!empty($errors)) {
    $message .= "\n⚠️ " . implode("\n⚠️ ", $errors) . "\n";
}

$message .= "\n⏰ _" . date('d.m.Y H:i') . "_";

// 4. Отправка
(new TelegramClient($tgBotToken))->sendMessage($tgChatId, $message, 'Markdown');

echo "Балансы обновлены: " . implode(", ", array_keys($currentBalances)) . "\n";
