<?php

require_once __DIR__ . '/../_env.php';

$logFile = __DIR__ . '/stat_bot.log';

function logMsg(string $msg): void
{
    global $logFile;
    file_put_contents($logFile, sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $msg), FILE_APPEND);
}

$tgToken  = getenv('TG_TOKEN_STAT');
$tgChatId = (int) getenv('TG_CHAT_ID');

if (empty($tgToken)) {
    logMsg("❌ TG_TOKEN_STAT не задан в .env файле");
    exit(1);
}

if (empty($tgChatId)) {
    logMsg("❌ TG_CHAT_ID не задан в .env файле");
    exit(1);
}

logMsg("🚀 Бот запущен, ожидание /alfa от TG_CHAT_ID={$tgChatId}");

$lastUpdateId = 0;

while (true) {
    try {
        $url = "https://api.telegram.org/bot{$tgToken}/getUpdates"
            . "?offset=" . ($lastUpdateId + 1)
            . "&timeout=50"
            . "&allowed_updates=[" . urlencode('"message"') . "]";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $response  = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            sleep(5);
            continue;
        }

        $data = json_decode($response, true);

        if (!$data || !($data['ok'] ?? false)) {
            if (($data['error_code'] ?? 0) === 401) {
                logMsg("❌ Неверный TG_TOKEN_STAT");
                exit(1);
            }
            sleep(5);
            continue;
        }

        foreach ($data['result'] ?? [] as $update) {
            $lastUpdateId = $update['update_id'] ?? $lastUpdateId;

            $message = $update['message'] ?? null;
            if (!$message) {
                continue;
            }

            // Только сообщения от TG_CHAT_ID
            $chatId = $message['chat']['id'] ?? null;
            if ((int) $chatId !== $tgChatId) {
                continue;
            }

            $text = trim($message['text'] ?? '');

            // Только команда /alfa
            if (!str_starts_with($text, '/alfa')) {
                continue;
            }

            logMsg("/alfa — запуск alfa.php");

            // Запуск alfa.php без ожидания результата (fire-and-forget)
            exec("/usr/local/bin/php82 /volume1/NAS/scripts/alfa.php > /dev/null 2>&1 &");
        }
    } catch (\Throwable $e) {
        logMsg("Исключение: " . $e->getMessage());
        sleep(5);
    }
}