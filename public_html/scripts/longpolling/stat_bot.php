<?php

require_once __DIR__ . '/../_env.php';

$logFile      = __DIR__ . '/stat_bot.log';
$offsetFile   = __DIR__ . '/stat_bot.offset';
$lockFile     = __DIR__ . '/stat_bot.lock';
$cooldownFile = __DIR__ . '/alfa.lastrun';

const LOG_MAX_SIZE   = 5 * 1024 * 1024;  // 5 МБ — порог ротации лога
const ALFA_COOLDOWN  = 60;               // сек между запусками /alfa
const ALFA_SCRIPT    = '/volume1/NAS/scripts/alfa.php';
const MEMORY_LIMIT   = 64 * 1024 * 1024; // 64 МБ — плановый самоперезапуск

// Путь к текущему интерпретатору PHP (не хардкодим версию/путь вручную)
define('ALFA_PHP_BIN', PHP_BINARY !== '' ? PHP_BINARY : '/usr/local/bin/php82');

function logMsg(string $msg): void
{
    global $logFile;

    if (file_exists($logFile) && filesize($logFile) > LOG_MAX_SIZE) {
        @rename($logFile, $logFile . '.' . date('Ymd_His') . '.old');
    }

    file_put_contents($logFile, sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $msg), FILE_APPEND | LOCK_EX);
}

// --- Защита от повторного запуска второго экземпляра бота ---
$lockHandle = fopen($lockFile, 'c');
if ($lockHandle === false) {
    logMsg("❌ Не удалось открыть lock-файл {$lockFile}");
    exit(1);
}
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    logMsg("❌ Другой экземпляр stat_bot.php уже запущен, выход");
    exit(1);
}

$tgToken  = getenv('TG_TOKEN_STAT');
$tgChatId = getenv('TG_CHAT_ID');

if ($tgToken === false || $tgToken === '') {
    logMsg("❌ TG_TOKEN_STAT не задан в .env файле");
    exit(1);
}

if ($tgChatId === false || $tgChatId === '') {
    logMsg("❌ TG_CHAT_ID не задан в .env файле");
    exit(1);
}
$tgChatId = (int) $tgChatId;

// --- Мягкое завершение по сигналам (актуально при ручном kill/systemd stop;
//     DSM Task Scheduler обычно шлёт SIGKILL, который не перехватить) ---
$running = true;
if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    $stopHandler = static function (int $signo) use (&$running): void {
        logMsg("🛑 Получен сигнал {$signo}, завершение работы");
        $running = false;
    };
    pcntl_signal(SIGTERM, $stopHandler);
    pcntl_signal(SIGINT, $stopHandler);
}

// --- Восстановление offset после рестарта ---
$lastUpdateId = 0;
if (is_file($offsetFile)) {
    $saved = trim((string) @file_get_contents($offsetFile));
    if ($saved !== '' && ctype_digit($saved)) {
        $lastUpdateId = (int) $saved;
    }
}

function saveOffset(int $updateId): void
{
    global $offsetFile;
    file_put_contents($offsetFile, (string) $updateId, LOCK_EX);
}

function isAlfaOnCooldown(): bool
{
    global $cooldownFile;

    $last = 0;
    if (is_file($cooldownFile)) {
        $saved = trim((string) @file_get_contents($cooldownFile));
        if ($saved !== '' && ctype_digit($saved)) {
            $last = (int) $saved;
        }
    }

    if (time() - $last < ALFA_COOLDOWN) {
        return true;
    }

    file_put_contents($cooldownFile, (string) time(), LOCK_EX);
    return false;
}

logMsg("🚀 Бот запущен, ожидание /alfa от TG_CHAT_ID={$tgChatId}, offset={$lastUpdateId}");

while ($running) {
    // Плановый самоперезапуск при превышении лимита памяти.
    // ВАЖНО: это только завершает процесс — перезапустить его должен
    // внешний супервизор (DSM Task Scheduler / обёрточный shell-скрипт),
    // сам себя PHP-процесс не поднимет.
    if (memory_get_usage(true) > MEMORY_LIMIT) {
        logMsg("🔄 Превышен лимит памяти (" . (MEMORY_LIMIT / 1024 / 1024) . " МБ), плановое завершение");
        break;
    }

    try {
        $params = [
            'offset'          => $lastUpdateId + 1,
            'timeout'         => 50,
            'allowed_updates' => json_encode(['message']),
        ];
        $url = "https://api.telegram.org/bot{$tgToken}/getUpdates?" . http_build_query($params);

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
            logMsg("⚠️ Ошибка cURL: {$curlError}");
            sleep(5);
            continue;
        }

        $data = json_decode($response, true);

        if (!$data || !($data['ok'] ?? false)) {
            if (($data['error_code'] ?? 0) === 401) {
                logMsg("❌ Неверный TG_TOKEN_STAT");
                exit(1);
            }
            logMsg("⚠️ Некорректный ответ Telegram API: " . substr((string) $response, 0, 300));
            sleep(5);
            continue;
        }

        foreach ($data['result'] ?? [] as $update) {
            $lastUpdateId = $update['update_id'] ?? $lastUpdateId;
            saveOffset($lastUpdateId);

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
            if ($text === '') {
                continue;
            }

            // Точное совпадение команды: /alfa или /alfa@botname, без /alfabank и т.п.
            $command = strtok($text, " \n");
            $command = strtok((string) $command, '@');

            if ($command !== '/alfa') {
                continue;
            }

            if (isAlfaOnCooldown()) {
                logMsg("⏳ /alfa проигнорирован — cooldown ещё не истёк");
                continue;
            }

            logMsg("/alfa — запуск alfa.php");

            // Запуск alfa.php без ожидания результата (fire-and-forget)
            $cmd = escapeshellarg(ALFA_PHP_BIN) . ' ' . escapeshellarg(ALFA_SCRIPT) . ' > /dev/null 2>&1 &';
            exec($cmd, $out, $exitCode);
            logMsg("alfa.php запущен ({$cmd}), exec exitCode={$exitCode}");
        }
    } catch (\Throwable $e) {
        logMsg("Исключение: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        sleep(5);
    }
}

flock($lockHandle, LOCK_UN);
fclose($lockHandle);
logMsg("✅ Бот остановлен корректно");