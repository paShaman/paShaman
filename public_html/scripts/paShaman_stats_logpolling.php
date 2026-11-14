<?php

require_once __DIR__ . '/_env.php';
require_once __DIR__ . '/clients/telegram_client.php';

/**
 * # 1. Проверяем, не запущен ли уже скрипт
 * if ! pgrep -f "paShaman_stats_logpolling.php" > /dev/null; then
 * # 2. Подгружаем переменные из .env файла (если он лежит рядом со скриптом)
 * if [ -f "/volume1/NAS/scripts/.env" ]; then
 * export $(grep -v '^#' /volume1/NAS/scripts/.env | xargs)
 * fi
 *
 * # 3. Запускаем PHP в фоновом режиме
 * nohup /usr/local/bin/php82 /volume1/NAS/scripts/paShaman_stats_logpolling.php > /dev/null 2>&1 &
 * fi
 */

$logFile      = __DIR__ . '/paShaman_stats_logpolling.log';
$offsetFile   = __DIR__ . '/paShaman_stats_logpolling.offset';
$lockFile     = __DIR__ . '/paShaman_stats_logpolling.lock';
$cooldownFile = __DIR__ . '/alfa.lastrun';

const LOG_MAX_SIZE   = 5 * 1024 * 1024;  // 5 МБ — порог ротации лога
const ALFA_COOLDOWN  = 60;               // сек между запусками /alfa
const ALFA_SCRIPT    = '/volume1/NAS/scripts/alfa_stats.php';
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
    logMsg("❌ Другой экземпляр paShaman_stats_logpolling.php уже запущен, выход");
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

/** Сколько секунд осталось до конца cooldown (0 — можно запускать). */
function alfaCooldownRemaining(): int
{
    global $cooldownFile;

    $last = 0;
    if (is_file($cooldownFile)) {
        $saved = trim((string) @file_get_contents($cooldownFile));
        if ($saved !== '' && ctype_digit($saved)) {
            $last = (int) $saved;
        }
    }

    return max(0, ALFA_COOLDOWN - (time() - $last));
}

/** Отправляет текстовое сообщение в чат бота (ошибки только логируются). */
function tgSend(string $text): void
{
    global $tgToken, $tgChatId;

    try {
        $result = (new TelegramClient($tgToken))->call('sendMessage', [
            'chat_id' => $tgChatId,
            'text'    => $text,
        ]);
    } catch (TelegramException $e) {
        logMsg("⚠️ Ошибка отправки сообщения в Telegram: " . $e->getMessage());
        return;
    }

    if (!$result->ok()) {
        logMsg("⚠️ Telegram отклонил сообщение: [" . ($result->errorCode() ?? '?') . '] ' . ($result->description() ?? ''));
    }
}

/** Отправляет файл документом в чат бота. */
function tgSendDocument(string $filePath, string $caption): bool
{
    global $tgToken, $tgChatId;

    try {
        $result = (new TelegramClient($tgToken))
            ->timeout(120)
            ->sendDocument($tgChatId, $filePath, $caption);
    } catch (TelegramException $e) {
        logMsg("⚠️ Ошибка отправки документа: " . $e->getMessage());
        return false;
    }

    if (!$result->ok()) {
        logMsg("⚠️ Telegram отклонил документ: [" . ($result->errorCode() ?? '?') . '] ' . ($result->description() ?? ''));
        return false;
    }

    return true;
}

function alfaHelpText(): string
{
    return "/alfa — отчёт по расходам в Telegram\n"
        . "/alfa raw — сырой дамп операций из Альфа-API в JSON-файл\n"
        . "/alfa raw 2026-10-01 2026-10-31 — дамп за период\n"
        . "/alfa raw 2026-10-01 2026-10-31 EXPENSE — плюс фильтр направления\n"
        . "Направление: ALL (по умолчанию), EXPENSE, INCOME";
}

/** Удаляет дампы старше указанного срока, чтобы не копить файлы на NAS. */
function cleanupAlfaRaw(string $dir, int $keepDays = 30): void
{
    $threshold = time() - $keepDays * 86400;

    foreach (glob($dir . '/alfa_raw_*.json') ?: [] as $file) {
        $mtime = @filemtime($file);
        if ($mtime !== false && $mtime < $threshold) {
            @unlink($file);
        }
    }
}

/**
 * Выполняет `alfa_stats.php --raw` и отправляет полученный JSON документом в Telegram.
 *
 * Разбор аргументов: даты в формате YYYY-MM-DD (первая — from, вторая — to)
 * и направление ALL|EXPENSE|INCOME в любом порядке.
 *
 * @param list<string> $args аргументы после `raw`
 */
function runAlfaRaw(array $args): void
{
    $from = null;
    $to = null;
    $direction = null;

    foreach ($args as $arg) {
        $arg = trim($arg);
        if ($arg === '') {
            continue;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $arg) === 1) {
            $date = DateTime::createFromFormat('!Y-m-d', $arg);
            if (!$date || $date->format('Y-m-d') !== $arg) {
                tgSend("❌ Неверная дата: {$arg}. Ожидается YYYY-MM-DD.");
                return;
            }
            if ($from === null) {
                $from = $arg;
            } elseif ($to === null) {
                $to = $arg;
            } else {
                tgSend("❌ Слишком много дат.\n\n" . alfaHelpText());
                return;
            }
            continue;
        }

        $upper = strtoupper($arg);
        if (in_array($upper, ['ALL', 'EXPENSE', 'INCOME'], true)) {
            if ($direction !== null) {
                tgSend("❌ Направление указано дважды.\n\n" . alfaHelpText());
                return;
            }
            $direction = $upper;
            continue;
        }

        tgSend("❌ Не понял аргумент «{$arg}».\n\n" . alfaHelpText());
        return;
    }

    $from ??= date('Y-m-01');
    $to ??= date('Y-m-d');

    if ($from > $to) {
        tgSend("❌ Начало периода ({$from}) позже конца ({$to}).");
        return;
    }

    $rawDir  = dirname(ALFA_SCRIPT) . '/alfa_raw';
    $outFile = $rawDir . '/alfa_raw_' . $from . '_' . $to . '_' . date('Ymd-His') . '.json';

    if (!is_dir($rawDir) && !@mkdir($rawDir, 0775, true) && !is_dir($rawDir)) {
        logMsg("❌ Не удалось создать каталог {$rawDir}");
        tgSend("❌ Не удалось создать каталог для дампа на сервере.");
        return;
    }

    $cmd = escapeshellarg(ALFA_PHP_BIN) . ' ' . escapeshellarg(ALFA_SCRIPT) . ' --raw'
        . ' --from=' . escapeshellarg($from)
        . ' --to=' . escapeshellarg($to)
        . ' --out=' . escapeshellarg($outFile);

    if ($direction !== null && $direction !== 'ALL') {
        $cmd .= ' --direction=' . escapeshellarg($direction);
    }

    logMsg("/alfa raw — запуск: {$cmd}");
    tgSend("⏳ Запрашиваю операции у Альфы за {$from} – {$to}…");

    $output = [];
    $exitCode = 1;
    exec($cmd . ' 2>&1', $output, $exitCode);

    $outputText = trim(implode("\n", $output));
    logMsg("/alfa raw завершён, exitCode={$exitCode}" . ($outputText !== '' ? "\n{$outputText}" : ''));

    if (!is_file($outFile)) {
        $details = $outputText !== '' ? "\n\n" . mb_substr($outputText, -1500) : '';
        tgSend("❌ Дамп не создан (exitCode={$exitCode})." . $details);
        return;
    }

    $meta = [];
    $decoded = json_decode((string) @file_get_contents($outFile), true);
    if (is_array($decoded) && isset($decoded['meta']) && is_array($decoded['meta'])) {
        $meta = $decoded['meta'];
    }

    $caption = "📄 Сырой дамп Альфа-API\n"
        . "Период: {$from} – {$to}\n"
        . 'Направление: ' . ($direction ?? 'ALL') . "\n"
        . 'Страниц: ' . ($meta['pages'] ?? '?') . ', операций: ' . ($meta['operations'] ?? '?') . "\n"
        . 'Среда: ' . ($meta['environment'] ?? '?');

    if ($exitCode !== 0) {
        $caption .= "\n\n⚠️ API вернул ошибку — детали в полях error/error_page файла.";
    }

    if (!tgSendDocument($outFile, $caption)) {
        $details = $outputText !== '' ? "\n\n" . mb_substr($outputText, -1500) : '';
        tgSend("⚠️ Дамп сохранён на сервере ({$outFile}), но отправить файл не удалось." . $details);
    }

    cleanupAlfaRaw($rawDir);
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

        try {
            $result = (new TelegramClient($tgToken))->timeout(60)->call('getUpdates', $params);
        } catch (TelegramException $e) {
            logMsg("⚠️ Ошибка getUpdates: " . $e->getMessage());
            sleep(5);
            continue;
        }

        if (!$result->ok()) {
            if ($result->errorCode() === 401) {
                logMsg("❌ Неверный TG_TOKEN_STAT");
                exit(1);
            }
            logMsg("⚠️ Некорректный ответ Telegram API: " . json_encode($result->raw(), JSON_UNESCAPED_UNICODE));
            sleep(5);
            continue;
        }

        foreach ($result->result() ?? [] as $update) {
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

            // Команда /alfa (или /alfa@botname); поддерживаются подкоманды raw и help
            $parts   = array_values(array_filter(preg_split('/\s+/', $text) ?: [], static fn (string $p): bool => $p !== ''));
            $command = strtok((string) ($parts[0] ?? ''), '@');
            $args    = array_slice($parts, 1);

            if ($command !== '/alfa') {
                continue;
            }

            $subCommand = strtolower($args[0] ?? '');

            // Справка и неизвестные подкоманды не расходуют cooldown
            if ($subCommand === 'help' || ($subCommand !== '' && $subCommand !== 'raw')) {
                tgSend(alfaHelpText());
                logMsg("/alfa {$subCommand} — отправлена справка");
                continue;
            }

            if (isAlfaOnCooldown()) {
                $left = alfaCooldownRemaining();
                logMsg("⏳ /alfa проигнорирован — cooldown, осталось {$left} c");
                tgSend("⏳ Подожди {$left} с перед следующим запросом.");
                continue;
            }

            if ($subCommand === 'raw') {
                logMsg("/alfa raw — сырой дамп");
                runAlfaRaw(array_slice($args, 1));
                continue;
            }

            logMsg("/alfa — запуск alfa_stats.php");

            // Запуск alfa_stats.php без ожидания результата (fire-and-forget)
            $cmd = escapeshellarg(ALFA_PHP_BIN) . ' ' . escapeshellarg(ALFA_SCRIPT) . ' > /dev/null 2>&1 &';
            exec($cmd, $out, $exitCode);
            logMsg("alfa_stats.php запущен ({$cmd}), exec exitCode={$exitCode}");
        }
    } catch (\Throwable $e) {
        logMsg("Исключение: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        sleep(5);
    }
}

flock($lockHandle, LOCK_UN);
fclose($lockHandle);
logMsg("✅ Бот остановлен корректно");