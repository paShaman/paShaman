<?php

include __DIR__.'/_env.php';

// =========================================================================
// 1. НАСТРОЙКИ ТЕЛЕГРАМА
// =========================================================================
define('TG_BOT_TOKEN', getenv('TG_TOKEN_STAT'));
define('TG_CHAT_ID', getenv('TG_CHAT_ID'));
define('ALFA_DEBUG', getenv('ALFA_DEBUG') === 'true');
define('ALFA_PROD', getenv('ALFA_PROD') === 'true');

// =========================================================================
// 2. НАСТРОЙКИ АЛЬФА-БАНКА
// =========================================================================
$clientId     = getenv(ALFA_PROD ? 'ALFA_CLIENT_ID_PROD' : 'ALFA_CLIENT_ID');
$clientSecret = getenv(ALFA_PROD ? 'ALFA_CLIENT_SECRET_PROD' : 'ALFA_CLIENT_SECRET');
$passphrase   = getenv(ALFA_PROD ? 'ALFA_KEY_PASSPHRASE_PROD' : 'ALFA_KEY_PASSPHRASE');
$redirectUri  = 'https://pashaman.dev/alfa/connect/';
$scope        = 'openid accounts cards operations-history';
$state        = uniqid('', true);

if (ALFA_PROD) {
    $certPath = __DIR__ . '/cert/alfa/prod/IP_Nikitin_PV_2026.cer';
    $keyPath = __DIR__ . '/cert/alfa/prod/ip_nikitin_pavel_viktorovich.key';
    $caChainPath = __DIR__ . '/cert/alfa/prod/apica_2022_chain.cer';

    $authorizeUrl = 'https://id.alfabank.ru/oidc/authorize';
    $tokenUrl     = 'https://baas.alfabank.ru/oidc/token';
    $apiUrl       = 'https://baas.alfabank.ru/api/pp/v1/operations';

    /*
     * https://developers.alfabank.ru/products/alfa-api/documentation/articles/alfa-id/articles/acf/articles/get-auth-code/v1/get-auth-code
     * https://developers.alfabank.ru/products/alfa-api/documentation/articles/alfa-id/articles/acf/articles/get-access-token/v1/get-access-token
     * https://developers.alfabank.ru/products/alfa-api/documentation/articles/operations-history/articles/operations-list/v1/operations-list
     */
} else {
    $certPath = __DIR__ . '/cert/alfa/test/sandbox_cert_2026.cer';
    $keyPath = __DIR__ . '/cert/alfa/test/sandbox_key_2026.key';
    $caChainPath = __DIR__ . '/cert/alfa/test/apica_2022_chain.cer';

    $authorizeUrl = 'https://id-sandbox.alfabank.ru/oidc/authorize';
    $tokenUrl = 'https://sandbox.alfabank.ru/api/token';
    $apiUrl = 'https://sandbox.alfabank.ru/api/pp/v1/operations';
}

$tokenStorage = __DIR__ . '/tokens.json';

// =========================================================================
// ЛОГИКА АВТОРИЗАЦИИ И ОБНОВЛЕНИЯ ТОКЕНОВ (ДЛЯ SYNOLOGY CRON)
// =========================================================================

$accessToken = null;

// Сценарий 1: Основной режим работы для Cron (проверяем сохраненные токены)
if (file_exists($tokenStorage)) {
    $tokens = json_decode(file_get_contents($tokenStorage), true) ?? [];

    // Если текущий токен ещё живой (с запасом в 1 минуту)
    if (!empty($tokens['access_token']) && ($tokens['expires_at'] ?? 0) > time() + 60) {
        $accessToken = $tokens['access_token'];
    }
    // Если протух — обновляем через refresh_token
    elseif (!empty($tokens['refresh_token'])) {
        $accessToken = requestTokens([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $tokens['refresh_token'],
        ]);
    }
}

// Сценарий 2: Первичный запуск (токенов нет, берём код авторизации из ENV)
if (!$accessToken && !empty(getenv('ALFA_AUTH_CODE'))) {
    $accessToken = requestTokens([
        'grant_type'   => 'authorization_code',
        'code'         => getenv('ALFA_AUTH_CODE'),
        'redirect_uri' => $redirectUri,
        'scope'        => $scope,
    ]);
}

// Сценарий 3: Если вообще ничего нет — генерируем ссылку для ручного получения кода
if (!$accessToken) {
    $link = $authorizeUrl . '?' . http_build_query([
        'response_type' => 'code',
        'client_id'     => $clientId,
        'redirect_uri'  => $redirectUri,
        'scope'         => $scope,
        'state'         => $state,
    ]);

    $msg = "❌ Не удалось получить access_token. Требуется ручная авторизация.";
    sendToTelegram($msg);

    echo "\n=== ПЕРВИЧНАЯ НАСТРОЙКА ===\n";
    echo "1. Открой ссылку в браузере:\n{$link}\n\n";
    echo "2. Авторизуйся, скопируй 'code' из адресной строки.\n";
    echo "3. Добавь в _env.php: putenv('ALFA_AUTH_CODE=твоя_строка_кода');\n";
    echo "4. Запусти скрипт снова.\n\n";
    exit(1);
}

// =========================================================================
// ШАГ 3: Запрос выписки по счету
// =========================================================================

// Текущий месяц: с 1 числа по вчера
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-d', strtotime('-1 day'));

// Вчерашний день
$yesterday = date('Y-m-d', strtotime('-1 day'));

/**
 * Выполняет запрос к API Альфа-Банка за операциями за указанный период
 */
$fetchOperations = function ($dateFrom, $dateTo) use ($apiUrl, $accessToken, $certPath, $keyPath, $passphrase, $caChainPath) {
    $allOperations = [];
    $offset = 0;
    $limit = 100;

    do {
        $queryParams = [
            'dateFrom'           => $dateFrom,
            'dateTo'             => $dateTo,
            'operationDirection' => 'EXPENSE',
            'limit'              => $limit,
            'offset'             => $offset,
        ];

        $queryApiUrl = $apiUrl . '?' . http_build_query($queryParams);

        $apiResponse = alfaCurl($queryApiUrl, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
            ],
        ], $certPath, $keyPath, $passphrase, $caChainPath);

        if ($apiResponse === null) {
            return null;
        }

        // Логируем сырой ответ выписки, если включен флаг
        if (ALFA_DEBUG) {
            $logTimestamp = date('Y-m-d H:i:s');
            $logContent = "=== [{$logTimestamp}] ЗАПРОС ВЫПИСКИ ({$dateFrom} – {$dateTo}, offset={$offset}) ===\n";
            $logContent .= "URL: {$queryApiUrl}\n";
            $logContent .= "ОТВЕТ СЕРВЕРА:\n{$apiResponse}\n";
            $logContent .= str_repeat('─', 57) . "\n\n";
            file_put_contents(__DIR__ . '/alfa_debug.log', $logContent, FILE_APPEND);
            echo "Сырой ответ выписки ({$dateFrom} – {$dateTo}, offset={$offset}) записан в лог.\n";
        }

        $pageData = json_decode($apiResponse, true);

        // Если ключа operations нет — вероятно, это ошибка. Возвращаем весь ответ для обработки error_description.
        if (!isset($pageData['operations'])) {
            return $pageData;
        }

        $pageOperations = $pageData['operations'];
        $allOperations = array_merge($allOperations, $pageOperations);
        $offset += $limit;

    } while (count($pageOperations) >= $limit);

    return ['operations' => $allOperations];
};

// Запрашиваем данные за текущий месяц и за вчерашний день
$monthData     = $fetchOperations($monthStart, $monthEnd);
$yesterdayData = $fetchOperations($yesterday, $yesterday);

// =========================================================================
// ШАГ 4: Группировка по категориям, форматирование и отправка в Телеграм
// =========================================================================

/**
 * Агрегирует операции из ответа API в массив [категория => ['sum' => сумма, 'count' => количество]]
 */
$aggregateCategories = function ($data) {
    $categories = [];
    if (!isset($data['operations']) || !is_array($data['operations'])) {
        return $categories;
    }
    foreach ($data['operations'] as $op) {
        $categoryName = $op['category']['name'] ?? 'Разное';
        $direction    = $op['direction'] ?? 'EXPENSE';
        if ($direction !== 'EXPENSE') {
            continue;
        }
        if ($categoryName === 'Между своими счетами' || $categoryName === 'Переводы') {
            continue;
        }
        $rawValue    = $op['amount']['value'] ?? 0;
        $minorUnits  = $op['amount']['minorUnits'] ?? 100;
        $amountValue = $rawValue / $minorUnits;
        if (!isset($categories[$categoryName])) {
            $categories[$categoryName] = ['sum' => 0, 'count' => 0];
        }
        $categories[$categoryName]['sum'] += $amountValue;
        $categories[$categoryName]['count']++;
    }
    return $categories;
};

$monthCategories  = $aggregateCategories($monthData);
$yesterdayCategories = $aggregateCategories($yesterdayData);

// Собираем все категории из обоих наборов
$allCategories = array_unique(array_merge(array_keys($monthCategories), array_keys($yesterdayCategories)));

// Вычисляем общие суммы и количество
$monthTotal       = array_sum(array_column($monthCategories, 'sum'));
$monthTotalCount  = array_sum(array_column($monthCategories, 'count'));
$yesterdayTotal   = array_sum(array_column($yesterdayCategories, 'sum'));
$yesterdayTotalCount = array_sum(array_column($yesterdayCategories, 'count'));

$currencySign = '₽';

// Определяем валюту из первой попавшейся операции
if (isset($monthData['operations'][0]['amount']['currency'])
    && $monthData['operations'][0]['amount']['currency'] !== 'RUR') {
    $currencySign = $monthData['operations'][0]['amount']['currency'];
}

$todayForFile = date('Y-m-d');
$htmlReport = '';

$htmlReport .= "<b>🤖 Alfa API: Аналитика расходов</b>\n";
$htmlReport .= "<p>📅 " . date('F Y') . " (с 1 по " . date('d', strtotime('-1 day')) . " число)</p>\n";

if (empty($allCategories)) {
    $htmlReport .= "<p>📭 Операций по картам не обнаружено.</p>\n";
    if (isset($monthData['error_description'])) {
        $htmlReport .= "<p>⚠️ Контекст ошибки: <code>" . htmlspecialchars($monthData['error_description']) . "</code></p>\n";
    }
    $sent = sendRichMessageToTelegram($htmlReport);
} else {
    // Сортируем категории по сумме за месяц (по убыванию)
    $sortedCategories = $allCategories;
    usort($sortedCategories, function ($a, $b) use ($monthCategories) {
        $sumA = $monthCategories[$a]['sum'] ?? 0;
        $sumB = $monthCategories[$b]['sum'] ?? 0;
        return $sumB <=> $sumA;
    });

    $redCategories = []; // категории с 🔴 (>50%) для алерта

    // --- Строим строки таблицы ---
    $tableRows = '';
    foreach ($sortedCategories as $cat) {
        $monthSum       = $monthCategories[$cat]['sum'] ?? 0;
        $monthCount     = $monthCategories[$cat]['count'] ?? 0;
        $yesterdaySum   = $yesterdayCategories[$cat]['sum'] ?? 0;
        $yesterdayCount = $yesterdayCategories[$cat]['count'] ?? 0;

        // Определяем эмодзи-пометку для категории
        $marker = '';
        if ($monthSum > 0 && $monthSum == $yesterdaySum) {
            $marker = ' 🆕';
        } elseif ($monthSum > 0 && $yesterdaySum > 0) {
            $pct = ($yesterdaySum / $monthSum) * 100;
            if ($pct > 50) {
                $marker = ' 🔴';
                $redCategories[] = $cat;
            } elseif ($pct > 20) {
                $marker = ' 🟡';
            } elseif ($pct > 10) {
                $marker = ' 🟢';
            }
        }

        $monthFormatted        = number_format($monthSum, 0, '.', ' ');
        $yesterdaySumFormatted = $yesterdaySum > 0 ? number_format($yesterdaySum, 0, '.', ' ') : '—';
        $yesterdayCountFormatted = $yesterdayCount > 0 ? (string)$yesterdayCount : '—';

        $tableRows .= "<tr><td><b>" . htmlspecialchars($cat) . "</b>{$marker}</td><td align=\"right\">{$monthFormatted}</td><td align=\"right\">{$monthCount}</td><td align=\"right\">{$yesterdaySumFormatted}</td><td align=\"right\">{$yesterdayCountFormatted}</td></tr>\n";
    }

    // --- Алерт по >50% категориям (видна всегда) ---
    if (!empty($redCategories)) {
        $htmlReport .= "<p>⚠️ <b>>50% месячных трат:</b></p>\n";
        foreach ($redCategories as $rcat) {
            $htmlReport .= "<p>🔴 " . htmlspecialchars($rcat) . ": " . number_format($yesterdayCategories[$rcat]['sum'], 0, '.', ' ') . " {$currencySign}</p>\n";
        }
    }

    // --- Итоговая сумма (видна всегда) ---
    $htmlReport .= "<p>💸 <b>Всего расходов: " . number_format($monthTotal, 0, '.', ' ') . " {$currencySign}</b></p>\n";
    if ($yesterdayTotal > 0) {
        $htmlReport .= "<p>📆 Вчера: +" . number_format($yesterdayTotal, 0, '.', ' ') . " {$currencySign}</p>\n";
    }

    // --- Таблица в сворачиваемом блоке <details> ---
    $htmlReport .= "<details>\n";
    $htmlReport .= "<summary><b>📊 Таблица расходов по категориям</b></summary>\n";
    $htmlReport .= "<table bordered>\n";
    $htmlReport .= "<tr><th>Категория</th><th align=\"right\">За месяц ({$currencySign})</th><th align=\"right\">(шт.)</th><th align=\"right\">Вчера ({$currencySign})</th><th align=\"right\">(шт.)</th></tr>\n";
    $htmlReport .= $tableRows;
    $htmlReport .= "</table>\n";

    // Итоговая информация о количестве транзакций
    $htmlReport .= "<p><b>Всего транзакций за месяц:</b> {$monthTotalCount}";
    if ($yesterdayTotalCount > 0) {
        $htmlReport .= " | <b>Вчера:</b> {$yesterdayTotalCount}";
    }
    $htmlReport .= "</p>\n";
    $htmlReport .= "</details>\n";

    $sent = sendRichMessageToTelegram($htmlReport);
}

if ($sent) {
    // good
}

// =========================================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// =========================================================================

/**
 * Выполняет cURL-запрос с mTLS-сертификатами Альфы.
 * Возвращает тело ответа или null при ошибке cURL.
 */
function alfaCurl($url, array $extraOptions, $certPath, $keyPath, $passphrase, $caChainPath): ?string
{
    $baseOptions = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSLCERT        => $certPath,
        CURLOPT_SSLKEY         => $keyPath,
        CURLOPT_SSLKEYPASSWD   => $passphrase,
        CURLOPT_CAINFO         => $caChainPath,
        CURLOPT_SSL_VERIFYPEER => false, // <-- ОТКЛЮЧИЛИ ДЛЯ ПЕСОЧНИЦЫ
        CURLOPT_SSL_VERIFYHOST => 0,     // <-- ОТКЛЮЧИЛИ ДЛЯ ПЕСОЧНИЦЫ
    ];

    $ch = curl_init();
    curl_setopt_array($ch, $extraOptions + $baseOptions); // $extraOptions имеют приоритет

    $response = curl_exec($ch);
    $error    = curl_errno($ch) ? curl_error($ch) : null;
    curl_close($ch);

    if ($error) {
        $msg = "❌ Ошибка cURL [{$url}]: {$error}";
        echo $msg . "\n";
        sendToTelegram($msg);
        return null;
    }

    return $response;
}

function requestTokens($postFields) {
    global $tokenUrl, $certPath, $keyPath, $passphrase, $caChainPath, $clientId, $clientSecret, $tokenStorage;

    $postFields['client_id']     = $clientId;
    $postFields['client_secret'] = $clientSecret;

    $response = alfaCurl($tokenUrl, [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => http_build_query($postFields),
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ], $certPath, $keyPath, $passphrase, $caChainPath);

    if ($response === null) {
        return null;
    }

    // Логируем ответ авторизации/обновления токенов, если включен флаг
    if (ALFA_DEBUG) {
        $logTimestamp = date('Y-m-d H:i:s');
        $logContent = "=== [{$logTimestamp}] ЗАПРОС ТОКЕНА ({$postFields['grant_type']}) ===\n";
        $logContent .= "ОТВЕТ СЕРВЕРА:\n{$response}\n";
        $logContent .= str_repeat('─', 57) . "\n\n";
        file_put_contents(__DIR__ . '/alfa_debug.log', $logContent, FILE_APPEND);
    }

    $data = json_decode($response, true);

    if (isset($data['access_token'])) {
        $data['expires_at'] = time() + ($data['expires_in'] ?? 3600);
        file_put_contents($tokenStorage, json_encode($data, JSON_PRETTY_PRINT));
        return $data['access_token'];
    }

    echo "⚠️ Альфа-Банк вернул ошибку при запросе токена:\n";
    echo $data
        ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
        : "Сырой ответ: {$response}\n";

    sendToTelegram("❌ Ошибка работы с токенами. Ответ сервера Альфы: " . $response);
    return null;
}

/**
 * Отправляет rich message в Telegram через sendRichMessage API.
 * Сообщение содержит HTML с таблицей в сворачиваемом блоке details.
 *
 * @param string $html HTML-содержимое rich message
 * @return bool
 */
function sendRichMessageToTelegram($html) {
    $url = "https://api.telegram.org/bot" . TG_BOT_TOKEN . "/sendRichMessage";

    $richMessage = [
        'html' => $html,
    ];

    $postFields = [
        'chat_id'      => TG_CHAT_ID,
        'rich_message' => json_encode($richMessage, JSON_UNESCAPED_UNICODE),
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo "❌ Системная ошибка cURL при отправке rich message в Telegram: " . curl_error($ch) . "\n";
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    $result = json_decode($response, true);

    if (isset($result['ok']) && $result['ok'] === true) {
        return true;
    }

    echo "❌ Telegram API вернул ошибку при отправке rich message: [" . ($result['error_code'] ?? '???') . "] " . ($result['description'] ?? 'Неизвестная ошибка') . "\n";

    // Авто-фолбек: если rich message не поддерживается — пробуем отправить обычным текстом
    if (isset($result['description']) && strpos($result['description'], 'rich') !== false) {
        echo "⚠️ Rich message не поддерживается, пробую отправить обычным текстом…\n";
        $plainText = "🤖 Alfa API: Аналитика расходов\n\n" . strip_tags($html);
        return sendToTelegram($plainText, null);
    }

    return false;
}

function sendToTelegram($text, $parseMode = 'Markdown') {
    $url = "https://api.telegram.org/bot" . TG_BOT_TOKEN . "/sendMessage";

    // Защита от превышения лимита ТГ (макс 4096 символов)
    if (mb_strlen($text) > 4000) {
        $text = mb_substr($text, 0, 3800) . "\n\n… [обрезано: превышен лимит Telegram в 4096 символов]";
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => [
            'chat_id'    => TG_CHAT_ID,
            'text'       => $text,
            'parse_mode' => $parseMode,
        ],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $error    = curl_errno($ch) ? curl_error($ch) : null;
    curl_close($ch);

    if ($error) {
        echo "❌ Ошибка cURL при отправке в Telegram: {$error}\n";
        return false;
    }

    $result = json_decode($response, true);

    if (!empty($result['ok'])) {
        return true;
    }

    $code = $result['error_code']  ?? '???';
    $desc = $result['description'] ?? 'Неизвестная ошибка';
    echo "❌ Telegram API вернул ошибку: [{$code}] {$desc}\n";

    // Авто-фолбек: если ТГ ругается на разметку — пробуем послать голым текстом
    if ($parseMode !== null && isset($result['description']) && strpos($result['description'], 'parse') !== false) {
        echo "⚠️ Пробую переотправить как plain text…\n";
        $plainText = str_replace(['```json', '```', '*', '`'], '', $text);
        return sendToTelegram($plainText, null);
    }

    return false;
}