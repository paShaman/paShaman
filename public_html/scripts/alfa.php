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
$state        = uniqid();

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
    $tokens = json_decode(file_get_contents($tokenStorage), true);

    // Если текущий токен еще живой (с запасом в 1 минуту)
    if (isset($tokens['access_token']) && isset($tokens['expires_at']) && $tokens['expires_at'] > time() + 60) {
        echo "Использую действующий access_token из кэша.\n";
        $accessToken = $tokens['access_token'];
    }
    // Если протух — обновляем через refresh_token
    elseif (isset($tokens['refresh_token'])) {
        echo "Access token истек. Обновляю через refresh_token...\n";
        $accessToken = requestTokens([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $tokens['refresh_token']
        ]);

        if (!$accessToken) {
            $errorMsg = "❌ Ошибка: Не удалось обновить токен через refresh_token. Завершаю работу.";
            sendToTelegram($errorMsg);
            die($errorMsg . "\n");
        }
    }
}

// Сценарий 2: Первичный запуск (токенов нет, берем код авторизации из ENV)
if (!$accessToken && !empty(getenv('ALFA_AUTH_CODE'))) {
    echo "Обмениваю полученный из ENV код на токены...\n";
    $accessToken = requestTokens([
        'grant_type'   => 'authorization_code',
        'code'         => getenv('ALFA_AUTH_CODE'),
        'redirect_uri' => $redirectUri,
        'scope'        => $scope
    ]);

    if (!$accessToken) {
        $errorMsg = "❌ Ошибка: Не удалось обменять код из ENV на токен. Завершаю работу.";
        sendToTelegram($errorMsg);
        die($errorMsg . "\n");
    }
}

// Сценарий 3: Если вообще ничего нет — генерируем ссылку для ручного получения кода
if (!$accessToken) {
    $link = $authorizeUrl . '?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'scope'         => $scope,
            'state'         => $state
        ]);

    echo "\n\n=== ПЕРВИЧНАЯ НАСТРОЙКА ===\n";
    echo "1. Открой ссылку в браузере:\n" . $link . "\n\n";
    echo "2. Авторизуйся, скопируй 'code' из адресной строки.\n";
    echo "3. Добавь его в свой _env.php: putenv('ALFA_AUTH_CODE=твоя_строка_кода');\n";
    echo "4. Запусти скрипт снова.\n\n";
    exit;
}

// =========================================================================
// ШАГ 3: Запрос выписки по счету
// =========================================================================
echo "Делаю запрос за выпиской по счету...\n";

// Текущий месяц: с 1 числа по вчера
$monthStart = date('Y-m-01');
$monthEnd   = date('Y-m-d', strtotime('-1 day'));

// Вчерашний день
$yesterday = date('Y-m-d', strtotime('-1 day'));

/**
 * Выполняет запрос к API Альфа-Банка за операциями за указанный период
 */
$fetchOperations = function ($dateFrom, $dateTo) use ($apiUrl, $accessToken, $certPath, $keyPath, $passphrase, $caChainPath) {
    $queryParams = [
        'dateFrom'           => $dateFrom,
        'dateTo'             => $dateTo,
        'operationDirection' => 'EXPENSE',
        'limit' => 100,
        'offset' => 0,
    ];

    $queryApiUrl = $apiUrl . '?' . http_build_query($queryParams);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $queryApiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ],
        CURLOPT_SSLCERT        => $certPath,
        CURLOPT_SSLKEY         => $keyPath,
        CURLOPT_SSLKEYPASSWD   => $passphrase,
        CURLOPT_CAINFO         => $caChainPath,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $apiResponse = curl_exec($ch);

    if (curl_errno($ch)) {
        $errorMsg = "❌ Ошибка cURL при запросе к Alfa API: " . curl_error($ch);
        sendToTelegram($errorMsg);
        die($errorMsg . "\n");
    }
    curl_close($ch);

    // Логируем сырой ответ выписки, если включен флаг
    if (ALFA_DEBUG) {
        $logTimestamp = date('Y-m-d H:i:s');
        $logContent = "=== [{$logTimestamp}] ЗАПРОС ВЫПИСКИ ({$dateFrom} – {$dateTo}) ===\n";
        $logContent .= "URL: {$queryApiUrl}\n";
        $logContent .= "ОТВЕТ СЕРВЕРА:\n{$apiResponse}\n";
        $logContent .= "─────────────────────────────────────────────────────────\n\n";
        file_put_contents(__DIR__ . '/alfa_debug.log', $logContent, FILE_APPEND);
        echo "Сырой ответ выписки ({$dateFrom} – {$dateTo}) записан в лог.\n";
    }

    return json_decode($apiResponse, true);
};

// Запрашиваем данные за текущий месяц и за вчерашний день
$monthData     = $fetchOperations($monthStart, $monthEnd);
$yesterdayData = $fetchOperations($yesterday, $yesterday);

// =========================================================================
// ШАГ 4: Группировка по категориям, форматирование и отправка в Телеграм
// =========================================================================
echo "Группирую операции по категориям и генерирую аналитику...\n";

/**
 * Агрегирует операции из ответа API в массив [категория => сумма]
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
        $categories[$categoryName] = ($categories[$categoryName] ?? 0) + $amountValue;
    }
    return $categories;
};

$monthCategories  = $aggregateCategories($monthData);
$yesterdayCategories = $aggregateCategories($yesterdayData);

// Собираем все категории из обоих наборов
$allCategories = array_unique(array_merge(array_keys($monthCategories), array_keys($yesterdayCategories)));

// Вычисляем общие суммы
$monthTotal     = array_sum($monthCategories);
$yesterdayTotal = array_sum($yesterdayCategories);

$currencySign = '₽';

// Определяем валюту из первой попавшейся операции
if (isset($monthData['operations'][0]['amount']['currency'])
    && $monthData['operations'][0]['amount']['currency'] !== 'RUR') {
    $currencySign = $monthData['operations'][0]['amount']['currency'];
}

$tgMessage = "🤖 *Alfa API: Аналитика расходов*\n";
$tgMessage .= "📅 " . date('F Y') . " (с 1 по " . date('d', strtotime('-1 day')) . " число)\n\n";

if (empty($allCategories)) {
    $tgMessage .= "📭 Операций по картам не обнаружено.\n";
    if (isset($monthData['error_description'])) {
        $tgMessage .= "⚠️ Контекст ошибки: `{$monthData['error_description']}`\n";
    }
} else {
    // Сортируем категории по сумме за месяц (по убыванию)
    $sortedCategories = $allCategories;
    usort($sortedCategories, function ($a, $b) use ($monthCategories) {
        return ($monthCategories[$b] ?? 0) <=> ($monthCategories[$a] ?? 0);
    });

    // --- Формируем Markdown-файл с таблицей ---
    $todayForFile = date('Y-m-d');
    $mdReport  = "# Аналитика расходов — " . date('F Y') . "\n\n";
    $mdReport .= "**Период:** с 1 по " . date('d', strtotime('-1 day')) . " число\n\n";
    $mdReport .= "| Категория | За месяц ({$currencySign}) | Вчера ({$currencySign}) |\n";
    $mdReport .= "|---|---:|---:|\n";

    $redCategories = []; // категории с 🔴 (>50%) для вывода в caption

    foreach ($sortedCategories as $cat) {
        $monthSum     = $monthCategories[$cat] ?? 0;
        $yesterdaySum = $yesterdayCategories[$cat] ?? 0;

        // Определяем эмодзи-пометку для категории
        $marker = '';
        if ($monthSum > 0 && $monthSum == $yesterdaySum) {
            // Категория появилась только вчера — все траты за месяц пришлись на вчера
            $marker = ' 🆕';
        } elseif ($monthSum > 0 && $yesterdaySum > 0) {
            $pct = ($yesterdaySum / $monthSum) * 100;
            if ($pct > 50) {
                $marker = ' 🔴'; // траты за вчера > 50% от месячных
                $redCategories[] = $cat;
            } elseif ($pct > 20) {
                $marker = ' 🟡'; // траты за вчера > 20% от месячных
            }elseif ($pct > 10) {
                $marker = ' 🟢'; // траты за вчера > 10% от месячных
            }
        }

        $monthFormatted     = number_format($monthSum, 0, '.', ' ');
        $yesterdayFormatted = $yesterdaySum > 0 ? number_format($yesterdaySum, 0, '.', ' ') : '—';

        $mdReport .= "| **{$cat}**{$marker} | {$monthFormatted} | {$yesterdayFormatted} |\n";
    }

    // Сохраняем md-файл
    $mdFilePath = __DIR__ . '/alfa_report_' . $todayForFile . '.md';
    file_put_contents($mdFilePath, $mdReport);

    // --- Краткая подпись (caption) к документу ---
    if (!empty($redCategories)) {
        $tgMessage .= "⚠️ *>50% месячных трат:*\n";
        foreach ($redCategories as $rcat) {
            $tgMessage .= "🔴 {$rcat}: " . number_format($yesterdayCategories[$rcat], 0, '.', ' ') . " {$currencySign}\n";
        }
        $tgMessage .= "\n";
    }

    $tgMessage .= "💸 *Всего расходов: " . number_format($monthTotal, 0, '.', ' ') . " {$currencySign}*";
    if ($yesterdayTotal > 0) {
        $tgMessage .= "\n📆 Вчера: +" . number_format($yesterdayTotal, 0, '.', ' ') . " {$currencySign}";
    }
}

// Отправляем документ с кратким итогом в подписи (всё одним сообщением)
if (!empty($mdFilePath) && file_exists($mdFilePath)) {
    $sent = sendDocumentToTelegram($mdFilePath, basename($mdFilePath), $tgMessage);
    unlink($mdFilePath); // удаляем временный файл
} else {
    // Если файла нет (нет данных) — отправляем просто текст
    $sent = sendToTelegram($tgMessage);
}

if ($sent) {
    echo "✅ Готово! Аналитический отчет успешно доставлен в Telegram.\n";
} else {
    echo "❌ Ошибка: Не удалось доставить сообщение.\n";
}

// =========================================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// =========================================================================

function requestTokens($postFields) {
    global $tokenUrl, $certPath, $keyPath, $passphrase, $caChainPath, $clientId, $clientSecret, $tokenStorage;

    $postFields['client_id']     = $clientId;
    $postFields['client_secret'] = $clientSecret;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $tokenUrl,
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'Content-Type: application/x-www-form-urlencoded'
        ],
        CURLOPT_POSTFIELDS     => http_build_query($postFields),
        CURLOPT_SSLCERT        => $certPath,
        CURLOPT_SSLKEY         => $keyPath,
        CURLOPT_SSLKEYPASSWD   => $passphrase,
        CURLOPT_CAINFO         => $caChainPath,
        CURLOPT_SSL_VERIFYPEER => false, // <-- ОТКЛЮЧИЛИ ДЛЯ ПЕСОЧНИЦЫ
        CURLOPT_SSL_VERIFYHOST => 0,     // <-- ОТКЛЮЧИЛИ ДЛЯ ПЕСОЧНИЦЫ
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $errorMsg = "❌ Системная ошибка cURL при запросе токена: " . curl_error($ch);
        sendToTelegram($errorMsg);
        echo $errorMsg . "\n";

        curl_close($ch);
        return null;
    }

    curl_close($ch);

    // Логируем ответ авторизации/обновления токенов, если включен флаг
    if (ALFA_DEBUG) {
        $logTimestamp = date('Y-m-d H:i:s');
        $logContent = "=== [{$logTimestamp}] ЗАПРОС ТОКЕНА ({$postFields['grant_type']}) ===\n";
        $logContent .= "ОТВЕТ СЕРВЕРА:\n{$response}\n";
        $logContent .= "─────────────────────────────────────────────────────────\n\n";
        file_put_contents(__DIR__ . '/alfa_debug.log', $logContent, FILE_APPEND);
    }

    $data = json_decode($response, true);

    if (isset($data['access_token'])) {
        $data['expires_at'] = time() + ($data['expires_in'] ?? 3600);
        file_put_contents($tokenStorage, json_encode($data, JSON_PRETTY_PRINT));
        return $data['access_token'];
    }

    echo "⚠️ Альфа-Банк вернул ошибку при запросе токена:\n";
    if ($data) {
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "Сырой ответ (Raw Response): " . $response . "\n";
    }

    sendToTelegram("❌ Ошибка работы с токенами. Ответ сервера Альфы: " . $response);
    return null;
}

/**
 * Отправляет документ (файл) в Telegram через sendDocument API.
 *
 * @param string $filePath Полный путь к файлу
 * @param string $fileName Имя файла для отображения в Telegram
 * @param string $caption  Подпись к документу
 * @return bool
 */
function sendDocumentToTelegram($filePath, $fileName, $caption = '') {
    $url = "https://api.telegram.org/bot" . TG_BOT_TOKEN . "/sendDocument";

    $postFields = [
        'chat_id'  => TG_CHAT_ID,
        'document' => new CURLFile($filePath, 'text/markdown', $fileName),
        'caption'  => $caption,
        'parse_mode' => 'Markdown',
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
        echo "❌ Системная ошибка cURL при отправке документа в Telegram: " . curl_error($ch) . "\n";
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    $result = json_decode($response, true);

    if (isset($result['ok']) && $result['ok'] === true) {
        return true;
    }

    echo "❌ Telegram API вернул ошибку при отправке документа: [" . ($result['error_code'] ?? '???') . "] " . ($result['description'] ?? 'Неизвестная ошибка') . "\n";
    return false;
}

function sendToTelegram($text, $parseMode = 'Markdown') {
    $url = "https://api.telegram.org/bot" . TG_BOT_TOKEN . "/sendMessage";

    // Защита от превышения лимита ТГ (макс 4096 символов)
    if (mb_strlen($text) > 4000) {
        $text = mb_substr($text, 0, 3800) . "\n\n... [Часть выписки обрезана, так как превышен лимит Telegram в 4096 символов] ...";
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
        CURLOPT_SSL_VERIFYPEER => false, // На случай если у сервера проблемы с SSL-цепочкой до ТГ
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo "❌ Системная ошибка cURL при отправке в Telegram: " . curl_error($ch) . "\n";
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    $result = json_decode($response, true);

    // Если всё улетело успешно
    if (isset($result['ok']) && $result['ok'] === true) {
        return true;
    }

    // Если Телеграм вернул ошибку — выводим её на чистую воду
    echo "❌ Telegram API вернул ошибку: [" . ($result['error_code'] ?? '???') . "] " . ($result['description'] ?? 'Неизвестная ошибка') . "\n";

    // Авто-фолбек: если ТГ ругается на разметку (Markdown parse error), пробуем послать голым текстом
    if ($parseMode !== null && isset($result['description']) && strpos($result['description'], 'parse') !== false) {
        echo "⚠️ Пробую переотправить отчет как чистый текст без разметки Markdown...\n";
        $plainText = str_replace(['```json', '```', '*', '`'], '', $text);
        return sendToTelegram($plainText, null);
    }

    return false;
}