<?php

include __DIR__.'/_env.php';

// Задаем таймзону, чтобы отчеты и сброс месяца совпадали с твоим временем
date_default_timezone_set('Europe/Moscow');

// --- НАСТРОЙКИ ---
$router_ip = getenv('KEENETIC_IP');
$user      = getenv('KEENETIC_USER');
$password  = getenv('KEENETIC_PASSWORD');
$botToken  = getenv('TG_TOKEN_STAT');
$chatId    = getenv('TG_CHAT_ID');

$cache_file = __DIR__ . '/wg_stats_cache.json';
$month_file = __DIR__ . '/wg_monthly_cache.json';

// --- 1. АВТОРИЗАЦИЯ ---
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, "");
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

curl_setopt($ch, CURLOPT_URL, "http://{$router_ip}/auth");
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
curl_setopt($ch, CURLOPT_URL, "http://{$router_ip}/rci/show/interface");
curl_setopt($ch, CURLOPT_POST, false);
$data_res = curl_exec($ch);
curl_close($ch);

$data = json_decode($data_res, true);
if (!$data) die("Ошибка данных.");

// --- 3. РАБОТА С КЕШАМИ ---
$currentMonth = date('Y-m'); // Используем Год-Месяц для надежности
$old_stats = file_exists($cache_file) ? json_decode(file_get_contents($cache_file), true) : [];
$month_stats = file_exists($month_file) ? json_decode(file_get_contents($month_file), true) : ['month' => $currentMonth, 'data' => []];

// Если наступил новый месяц — сбрасываем данные
if (!isset($month_stats['month']) || $month_stats['month'] !== $currentMonth) {
    $month_stats = ['month' => $currentMonth, 'data' => []];
}

$new_stats = [];
$currentDate = date('d.m.Y H:i');
$currentMonthName = date('F');
$report = "📊 *Отчет Wireguard (Keenetic) за {$currentMonthName}*\n";
$report .= "{$currentDate}\n\n";
$has_updates = false;

foreach ($data as $iface) {
    $ifId = $iface['id'] ?? '';
    if ($ifId !== 'Wireguard1') continue;

    if (isset($iface['wireguard']['peer']) && is_array($iface['wireguard']['peer'])) {
        foreach ($iface['wireguard']['peer'] as $peer) {
            $id = $peer['public-key'];
            $name = !empty($peer['description']) ? $peer['description'] : substr($id, 0, 8);

            $current = (float)($peer['rxbytes'] ?? 0) + (float)($peer['txbytes'] ?? 0);
            $new_stats[$id] = $current;

            if (isset($old_stats[$id])) {
                $diff = $current - $old_stats[$id];
                // Если роутер перезагрузился, считаем весь текущий трафик как новый кусок
                if ($diff < 0) $diff = $current;

                $delta_mb = round($diff / 1024 / 1024, 2);
                if ($delta_mb > 0) {
                    $month_stats['data'][$id] = ($month_stats['data'][$id] ?? 0) + $delta_mb;

                    if ($delta_mb > 0.1) { // Порог для уведомления
                        $has_updates = true;
                        $total_month_gb = round($month_stats['data'][$id] / 1024, 2);
                        $report .= "👤 *{$name}*\n📈 +{$delta_mb} MB | 📅 Месяц: *{$total_month_gb} GB*\n\n";
                    }
                }
            }
        }
    }
}

file_put_contents($cache_file, json_encode($new_stats));
file_put_contents($month_file, json_encode($month_stats));

// --- 4. ОТПРАВКА ---
if ($has_updates) {
    $tg_url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $p = ['chat_id' => $chatId, 'text' => $report, 'parse_mode' => 'Markdown'];
    $tx = curl_init($tg_url);
    curl_setopt($tx, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($tx, CURLOPT_POSTFIELDS, $p);
    curl_exec($tx);
    curl_close($tx);
}