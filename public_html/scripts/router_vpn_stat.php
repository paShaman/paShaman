<?php

include __DIR__.'/_env.php';

// Задаем таймзону, чтобы отчеты и сброс месяца совпадали с твоим временем
date_default_timezone_set('Europe/Moscow');

// --- НАСТРОЙКИ ---
$routerIp  = getenv('KEENETIC_IP');
$user      = getenv('KEENETIC_USER');
$password  = getenv('KEENETIC_PASSWORD');
$botToken  = getenv('TG_TOKEN_STAT');
$chatId    = getenv('TG_CHAT_ID');

$cacheFile = __DIR__ . '/wg_stats_cache.json';
$monthFile = __DIR__ . '/wg_monthly_cache.json';

// --- 1. АВТОРИЗАЦИЯ ---
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, "");
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

curl_setopt($ch, CURLOPT_URL, "http://{$routerIp}/auth");
$resp = curl_exec($ch);
preg_match('/x-ndm-challenge: (.*)/i', $resp, $m);
preg_match('/x-ndm-realm: (.*)/i', $resp, $r);
$sha = hash('sha256', trim($m[1] ?? '') . md5($user . ':' . trim($r[1] ?? 'ndm') . ':' . $password));

curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['login' => $user, 'password' => $sha]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_exec($ch);

// --- 2. ПОЛУЧЕНИЕ ДАННЫХ ---
curl_setopt($ch, CURLOPT_URL, "http://{$routerIp}/rci/show/interface");
curl_setopt($ch, CURLOPT_POST, false);
$dataRes = curl_exec($ch);
curl_close($ch);

$data = json_decode($dataRes, true);
if (!$data) die("Ошибка данных.");

// --- 3. РАБОТА С КЕШАМИ ---
$currentMonth = date('Y-m'); // Используем Год-Месяц для надежности
$oldStats = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];
$monthStats = file_exists($monthFile) ? json_decode(file_get_contents($monthFile), true) : ['month' => $currentMonth, 'data' => []];

// Если наступил новый месяц — сбрасываем данные
if (!isset($monthStats['month']) || $monthStats['month'] !== $currentMonth) {
    $monthStats = ['month' => $currentMonth, 'data' => []];
}

$newStats = [];
$currentDate = date('d.m.Y H:i');
$currentMonthName = date('F');
$report = "📊 *Отчет Wireguard (Keenetic) за {$currentMonthName}*\n";
$report .= "{$currentDate}\n\n";
$hasUpdates = false;

foreach ($data as $iface) {
    $ifId = $iface['id'] ?? '';
    if ($ifId !== 'Wireguard1') continue; // сейчас такой id у домешнего впн

    if (isset($iface['wireguard']['peer']) && is_array($iface['wireguard']['peer'])) {
        foreach ($iface['wireguard']['peer'] as $peer) {
            $id = $peer['public-key'];
            $name = !empty($peer['description']) ? $peer['description'] : substr($id, 0, 8);

            $current = (float)($peer['rxbytes'] ?? 0) + (float)($peer['txbytes'] ?? 0);
            $newStats[$id] = $current;

            if (isset($oldStats[$id])) {
                $diff = $current - $oldStats[$id];
                // Если роутер перезагрузился, считаем весь текущий трафик как новый кусок
                if ($diff < 0) $diff = $current;

                $deltaMb = round($diff / 1024 / 1024, 2);
                //if ($deltaMb > 0) {
                    $monthStats['data'][$id] = ($monthStats['data'][$id] ?? 0) + $deltaMb;

                    //if ($deltaMb > 0.1) { // Порог для уведомления
                        $hasUpdates = true;
                        $totalMonthGb = round($monthStats['data'][$id] / 1024, 2);
                        $report .= "👤 *{$name}*\n📈 ". ($deltaMb ? '+' . $deltaMb . ' MB' : '-') ." | 📅 Месяц: *{$totalMonthGb} GB*\n\n";
                    //}
                //}
            }
        }
    }
}

file_put_contents($cacheFile, json_encode($newStats));
file_put_contents($monthFile, json_encode($monthStats));

// --- 4. ОТПРАВКА ---
if ($hasUpdates) {
    $tgUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $p = ['chat_id' => $chatId, 'text' => $report, 'parse_mode' => 'Markdown'];
    $tx = curl_init($tgUrl);
    curl_setopt($tx, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($tx, CURLOPT_POSTFIELDS, $p);
    curl_exec($tx);
    curl_close($tx);
}