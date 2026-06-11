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

$queryParams = [
    //'dateFrom' => date('Y-m-d', time() - 60*60*24*2),
    'operationDirection' => 'EXPENSE',
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
    CURLOPT_SSL_VERIFYPEER => false, // <-- ОТКЛЮЧИЛИ ДЛЯ ПЕСОЧНИЦЫ
    CURLOPT_SSL_VERIFYHOST => 0,     // <-- ОТКЛЮЧИЛИ ДЛЯ ПЕСОЧНИЦЫ
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
    $logContent = "=== [{$logTimestamp}] ЗАПРОС ВЫПИСКИ ===\n";
    $logContent .= "URL: {$queryApiUrl}\n";
    $logContent .= "ОТВЕТ СЕРВЕРА:\n{$apiResponse}\n";
    $logContent .= "─────────────────────────────────────────────────────────\n\n";
    file_put_contents(__DIR__ . '/alfa_debug.log', $logContent, FILE_APPEND);
    echo "Сырой ответ выписки записан в лог (alfa_debug.log).\n";
}

/*
    "operations": [
        {
            "id": "0866BCIMSJ00100340817",
            "dateTime": "2024-10-09T08:50:34.285Z",
            "title": "Пятёрочка",
            "amount": {
                "value": 2199,
                "currency": "RUR",
                "minorUnits": 100
            },
            "direction": "EXPENSE",
            "fee": 0,
            "isAnotherClient": false,
            "cashout": false,
            "comment": null,
            "mcc": "5411",
            "category": {
                "id": "00041",
                "name": "Продукты"
            },
            "loyalty": {
                "title": "Кэшбэк",
                "percent": null,
                "amount": null
            },
            "status": "HOLD",
            "type": null,
            "terminal": {
                "number": "6119",
                "name": "PYATEROCHKA 1000",
                "city": "SANKT PETERBURG",
                "countryCode": "RU"
            },
            "sender": {
                "name": null,
                "accountNumber": "40817810804230026597",
                "bankBik": null,
                "bankName": null,
                "phoneNumber": null,
                "maskedCardNumber": "000000++++++000"
            },
            "recipient": null,
            "reference": "HOLD"
        },
 */

// =========================================================================
// ШАГ 4: Группировка по категориям, форматирование и отправка в Телеграм
// =========================================================================
echo "Группирую операции по категориям и генерирую аналитику...\n";

$decodedResponse = json_decode($apiResponse, true);

// Собираем стартовую шапку сообщения
$tgMessage = "🤖 *Alfa API: Аналитика расходов*\n";
$tgMessage .= "───────────────────\n\n";

if (isset($decodedResponse['operations']) && is_array($decodedResponse['operations']) && count($decodedResponse['operations']) > 0) {

    $opCount = count($decodedResponse['operations']);

    // Массивы для агрегации сумм по категориям
    $categoryExpenses = [];
    $categoryIncome   = [];
    $totalExpenses    = 0;
    $totalIncome      = 0;
    $currencySign     = '₽'; // По умолчанию рубль

    // -----------------------------------------------------------------
    // ЭТАП 1: Агрегируем суммы по категориям
    // -----------------------------------------------------------------
    foreach ($decodedResponse['operations'] as $op) {
        $categoryName = $op['category']['name'] ?? 'Разное';
        $direction    = $op['direction'] ?? 'EXPENSE';

        // Считаем сумму с учетом копеек
        $rawValue    = $op['amount']['value'] ?? 0;
        $minorUnits  = $op['amount']['minorUnits'] ?? 100;
        $amountValue = $rawValue / $minorUnits;

        // Определяем знак валюты по ходу дела
        if (isset($op['amount']['currency']) && $op['amount']['currency'] !== 'RUR') {
            $currencySign = $op['amount']['currency'];
        }

        if ($direction === 'INCOME') {
            $categoryIncome[$categoryName] = ($categoryIncome[$categoryName] ?? 0) + $amountValue;
            $totalIncome += $amountValue;
        } else {
            $categoryExpenses[$categoryName] = ($categoryExpenses[$categoryName] ?? 0) + $amountValue;
            $totalExpenses += $amountValue;
        }
    }

    // -----------------------------------------------------------------
    // ЭТАП 2: Формируем блок сводки по категориям
    // -----------------------------------------------------------------
    $tgMessage .= "📊 *СВОДКА ПО КАТЕГОРИЯМ*\n\n";

    if (!empty($categoryExpenses)) {
        $tgMessage .= "🔻 *Расходы по категориям:*\n";
        arsort($categoryExpenses); // Сортируем: от самых больших трат к меньшим
        foreach ($categoryExpenses as $cat => $sum) {
            $tgMessage .= "• {$cat}: *" . number_format($sum, 2, '.', ' ') . " {$currencySign}*\n";
        }
        $tgMessage .= "🛑 Всего расходов: *" . number_format($totalExpenses, 2, '.', ' ') . " {$currencySign}*\n\n";
    }

    if (!empty($categoryIncome)) {
        $tgMessage .= "🔺 *Доходы по категориям:*\n";
        arsort($categoryIncome); // Сортируем доходы по убыванию
        foreach ($categoryIncome as $cat => $sum) {
            $tgMessage .= "• {$cat}: *" . number_format($sum, 2, '.', ' ') . " {$currencySign}*\n";
        }
        $tgMessage .= "❇️ Всего доходов: *" . number_format($totalIncome, 2, '.', ' ') . " {$currencySign}*\n\n";
    }

} else {
    $tgMessage .= "📭 Новых операций по картам не обнаружено.\n";
    if (isset($decodedResponse['error_description'])) {
        $tgMessage .= "⚠️ Контекст ошибки: `{$decodedResponse['error_description']}`\n";
    }
}

// Отправляем готовую аналитику
if (sendToTelegram($tgMessage)) {
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