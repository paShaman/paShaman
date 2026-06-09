<?php

include __DIR__.'/_env.php';

date_default_timezone_set('Europe/Moscow');

// --- НАСТРОЙКИ ---
$botToken  = getenv('TG_TOKEN_STAT');
$chatId    = getenv('TG_CHAT_ID');
$interface = "awg0";
$serverConfig = "/etc/amnezia/amneziawg/awg0.conf";
$historyFile = "/root/awg_history.json";
$monthlyLimitGB = false;

function formatBytes($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

// 1. Авто-сброс статистики 1-го числа
if (file_exists($historyFile)) {
    $currentMonth = date('Y-m');
    $lastModifiedMonth = date('Y-m', filemtime($historyFile));

    if ($lastModifiedMonth !== $currentMonth) {
        copy($historyFile, $historyFile . ".bak_" . $lastModifiedMonth);
        $history = json_decode(file_get_contents($historyFile), true);

        // Получаем текущие системные счетчики, чтобы вычесть их в новом месяце
        $dump = shell_exec("awg show $interface dump");
        $lines = explode("\n", trim($dump));
        array_shift($lines);

        foreach ($lines as $line) {
            $data = explode("\t", $line);
            if (count($data) < 7) continue;
            $pubKey = $data[0];

            // Начинаем месяц с "чистого листа"
            $history[$pubKey] = [
                'rx'             => 0,
                'tx'             => 0,
                'last_total_rx'  => (int)$data[5],
                'last_total_tx'  => (int)$data[6],
                'prev_run_total' => (int)$data[5] + (int)$data[6]
            ];
        }
        file_put_contents($historyFile, json_encode($history));
    }
}

// 2. Получаем текущие данные
$dump = shell_exec("awg show $interface dump");
$lines = explode("\n", trim($dump));
array_shift($lines);

$history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];

// 3. Парсим конфиг для имен (оставляем без изменений)
$clientNames = [];
if (file_exists($serverConfig)) {
    $content = file_get_contents($serverConfig);
    $peers = explode('[Peer]', $content);
    foreach ($peers as $peerBlock) {
        if (preg_match('/PublicKey\s*=\s*([^\s#]+)/', $peerBlock, $keyMatches)) {
            $pubKey = trim($keyMatches[1]);
            if (preg_match('/#_Name\s*=\s*(.+)/', $peerBlock, $nameMatches)) {
                $clientNames[$pubKey] = trim($nameMatches[1]);
            }
        }
    }
}

// 4. Обработка данных
$message = "📊 *Отчет AmneziaWG за " . date('F') . "*\n" . date('d.m.Y H:i') . "\n\n";
$totalServerTraffic = 0;
$activeCount = 0;

foreach ($lines as $line) {
    $data = explode("\t", $line);
    if (count($data) < 7) continue;

    $pubKey = $data[0];
    $name = $clientNames[$pubKey] ?? substr($pubKey, 0, 8) . "...";
    $currentRx = (int)$data[5];
    $currentTx = (int)$data[6];
    $currentRawTotal = $currentRx + $currentTx;

    if (!isset($history[$pubKey])) {
        $history[$pubKey] = ['rx' => 0, 'tx' => 0, 'last_total_rx' => $currentRx, 'last_total_tx' => $currentTx, 'prev_run_total' => $currentRawTotal];
    }

    // Если интерфейс перезагрузился (счетчики в дампе обнулились)
    if ($currentRawTotal < ($history[$pubKey]['last_total_rx'] + $history[$pubKey]['last_total_tx'])) {
        $history[$pubKey]['rx'] += $history[$pubKey]['last_total_rx'];
        $history[$pubKey]['tx'] += $history[$pubKey]['last_total_tx'];
        $history[$pubKey]['prev_run_total'] = 0;
    }

    // Трафик за этот месяц = (Текущий дамп - Дамп на начало месяца) + Накопленное при перезагрузках
    $totalRx = ($currentRx - $history[$pubKey]['last_total_rx']) + $history[$pubKey]['rx'];
    $totalTx = ($currentTx - $history[$pubKey]['last_total_tx']) + $history[$pubKey]['tx'];
    // Упростим: для отчета используем дельту между запусками
    $totalCumulative = $totalRx + $totalTx;

    $delta = 0;
    if ($history[$pubKey]['prev_run_total'] > 0) {
        $delta = $currentRawTotal - $history[$pubKey]['prev_run_total'];
        if ($delta < 0) $delta = $currentRawTotal;
    }

    $history[$pubKey]['prev_run_total'] = $currentRawTotal;
    $totalServerTraffic += $totalCumulative;

    $lastHandshake = (int)$data[4];
    $isOnline = (time() - $lastHandshake < 300) && ($lastHandshake > 0);
    if ($isOnline) $activeCount++;

    if ($totalCumulative > 0) {
        $message .= ($isOnline ? "🟢" : "⚪") . " *$name*\n";
        $message .= "├ 📥 " . formatBytes($totalTx) . " | 📤 " . formatBytes($totalRx) . "\n";
        $message .= "└ 🔄 Всего: " . formatBytes($totalCumulative);
        if ($delta > 10240) $message .= " (📈 +" . formatBytes($delta) . ")";
        $message .= "\n\n";
    }
}

file_put_contents($historyFile, json_encode($history));

// Итоги (остальной код отправки без изменений)
$totalGB = $totalServerTraffic / (1024 ** 3);

if ($monthlyLimitGB !== false) {
    $percentUsed = round(($totalGB / $monthlyLimitGB) * 100, 1);
    $message .= "---\n🌍 Общий трафик: " . formatBytes($totalServerTraffic) . "\n🔋 Лимит: $percentUsed% из {$monthlyLimitGB}GB";
} else {
    $message .= "---\n🌍 Общий трафик: " . formatBytes($totalServerTraffic);
}

$postData = ['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'Markdown'];
$ch = curl_init("https://api.telegram.org/bot$botToken/sendMessage");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_exec($ch);
curl_close($ch);