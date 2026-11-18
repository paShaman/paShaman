<?php

include __DIR__.'/_env.php';
require_once __DIR__ . '/clients/telegram_client.php';

// =========================================================================
// 0. CLI-ОПЦИИ
// =========================================================================

$cliOptions = [];
if (PHP_SAPI === 'cli') {
    foreach (array_slice($argv ?? [], 1) as $arg) {
        if (str_starts_with($arg, '--')) {
            $parts = explode('=', substr($arg, 2), 2);
            $cliOptions[$parts[0]] = $parts[1] ?? true;
        }
    }
}

// Режим сырого дампа: забираем операции из API и складываем в JSON-файл,
// без агрегации и без отправки в Telegram.
define('ALFA_RAW', isset($cliOptions['raw']));

// Режим диагностики (--debug): выполняем один запрос к API и собираем полный
// отчёт об обмене (дата/время с часовым поясом, метод, URI, заголовки и тело
// запроса, код и заголовки ответа включая X-TraceId, IP-адреса) — чтобы
// отправить эти детали в техподдержку Альфа-Банка.
define('ALFA_DIAG', isset($cliOptions['debug']));

// Маскировать секреты (Bearer-токены, client_secret, коды авторизации) в
// отчёте --debug. Отключается флагом --no-mask, если поддержка просит «как есть».
define('ALFA_MASK', !isset($cliOptions['no-mask']));

if (isset($cliOptions['help'])) {
    echo "Использование:\n";
    echo "  php alfa_stats.php                        — обычный отчёт в Telegram\n";
    echo "  php alfa_stats.php --raw                  — сырой дамп операций в JSON-файл\n";
    echo "  php alfa_stats.php --debug                — диагностический отчёт для поддержки Альфы\n\n";
    echo "Опции режима --raw:\n";
    echo "  --from=YYYY-MM-DD          начало периода (по умолчанию 1-е число текущего месяца)\n";
    echo "  --to=YYYY-MM-DD            конец периода (по умолчанию сегодня)\n";
    echo "  --direction=ALL|EXPENSE|INCOME  направление операций (по умолчанию ALL)\n";
    echo "  --out=path/to/file.json    путь к файлу (по умолчанию scripts/alfa_raw/alfa_raw_<from>_<to>_<ts>.json)\n";
    echo "\nОпции режима --debug:\n";
    echo "  --from=YYYY-MM-DD          начало периода запроса (по умолчанию 1-е число месяца)\n";
    echo "  --to=YYYY-MM-DD            конец периода запроса (по умолчанию сегодня)\n";
    echo "  --out=path/to/file.txt     путь к отчёту (по умолчанию scripts/alfa_debug/alfa_debug_<ts>.txt)\n";
    echo "  --no-mask                  не маскировать токены и client_secret в отчёте\n";
    exit(0);
}

/**
 * Возвращает строковое значение CLI-опции (null для флагов без значения).
 */
function cliOption(array $options, string $name): ?string
{
    $value = $options[$name] ?? null;
    return is_string($value) && $value !== '' ? $value : null;
}

// Параметры сырого дампа проверяем сразу, до авторизации и запросов в API
if (ALFA_RAW) {
    $rawFrom = cliOption($cliOptions, 'from') ?? date('Y-m-01');
    $rawTo   = cliOption($cliOptions, 'to') ?? date('Y-m-d');

    foreach (['from' => $rawFrom, 'to' => $rawTo] as $label => $value) {
        $parsedDate = DateTime::createFromFormat('!Y-m-d', $value);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $value) {
            fwrite(STDERR, "❌ Неверная дата --{$label}={$value}. Ожидается формат YYYY-MM-DD.\n");
            exit(1);
        }
    }

    if ($rawFrom > $rawTo) {
        fwrite(STDERR, "❌ --from ({$rawFrom}) больше --to ({$rawTo}).\n");
        exit(1);
    }

    $rawDirectionName = strtoupper(cliOption($cliOptions, 'direction') ?? 'ALL');
    $rawDirections = ['ALL' => null, 'EXPENSE' => 'EXPENSE', 'INCOME' => 'INCOME'];
    if (!array_key_exists($rawDirectionName, $rawDirections)) {
        fwrite(STDERR, "❌ Неверное --direction={$rawDirectionName}. Допустимо: ALL, EXPENSE, INCOME.\n");
        exit(1);
    }

    $rawDump = [
        'from'      => $rawFrom,
        'to'        => $rawTo,
        'direction' => $rawDirections[$rawDirectionName],
        'out'       => cliOption($cliOptions, 'out'),
    ];
}

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

// Окружение, сертификаты и адреса эндпоинтов
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

$tokenStorage = __DIR__ . '/alfa_stats_tokens.json';

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

// =========================================================================
// 2.5. ДИАГНОСТИКА ИСХОДЯЩЕГО СОЕДИНЕНИЯ (--debug)
// =========================================================================
// Выполняем ровно один обмен с API и формируем отчёт для техподдержки:
// дата/время с часовым поясом, метод и URI, заголовки и тело запроса,
// полученная ошибка, заголовки ответа (в первую очередь X-TraceId) и IP-адрес,
// с которого ушёл запрос. Выполняется после авторизации, но до сценария 3
// (ручная авторизация), чтобы диагностировать именно сбойный обмен.
if (ALFA_DIAG) {
    $diagFrom = cliOption($cliOptions, 'from') ?? date('Y-m-01');
    $diagTo   = cliOption($cliOptions, 'to') ?? date('Y-m-d');

    foreach (['from' => $diagFrom, 'to' => $diagTo] as $label => $value) {
        $parsedDate = DateTime::createFromFormat('!Y-m-d', $value);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $value) {
            fwrite(STDERR, "❌ Неверная дата --{$label}={$value}. Ожидается формат YYYY-MM-DD.\n");
            exit(1);
        }
    }

    if ($diagFrom > $diagTo) {
        fwrite(STDERR, "❌ --from ({$diagFrom}) больше --to ({$diagTo}).\n");
        exit(1);
    }

    exit(runAlfaDiag(
        $cliOptions,
        $diagFrom,
        $diagTo,
        $accessToken,
        $tokenUrl,
        $apiUrl,
        $tokenStorage,
        $clientId,
        $clientSecret,
        $certPath,
        $keyPath,
        $passphrase,
        $caChainPath
    ));
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

// Определяем период и заголовок месяца
// Если сегодня 1-е число — берём ВЕСЬ прошлый месяц, иначе — текущий месяц с 1-го по вчера
if (date('d') === '01') {
    $monthStart = date('Y-m-01', strtotime('-1 month'));
    $monthEnd   = date('Y-m-t', strtotime('-1 month'));
    $reportMonth = date('F Y', strtotime('-1 month')) . ' <i>итог за месяц</i>';
    $reportMonthDays = '1 по ' . date('t', strtotime('-1 month'));
} else {
    $monthStart = date('Y-m-01');
    $monthEnd   = date('Y-m-d', strtotime('-1 day'));
    $reportMonth = date('F Y');
    $reportMonthDays = '1 по ' . date('d', strtotime('-1 day'));
}

// Вчерашний день
$yesterday = date('Y-m-d', strtotime('-1 day'));

// =========================================================================
// ШАГ 2.5: РЕЖИМ СЫРОГО ДАМПА (--raw)
// =========================================================================
if (ALFA_RAW) {
    exit(dumpAlfaRaw($rawDump['from'], $rawDump['to'], $rawDump['direction'], $rawDump['out']));
}

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
$htmlReport .= "<p>📅 " . $reportMonth . " (с " . $reportMonthDays . " число)</p>\n";

if (empty($allCategories)) {
    $htmlReport .= "<p>📭 Операций по картам не обнаружено.</p>\n";
    if (isset($monthData['error_description'])) {
        $htmlReport .= "<p>⚠️ Контекст ошибки: <code>" . htmlspecialchars($monthData['error_description']) . "</code></p>\n";
    }
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
}

// --- Блок «Вчера» (для Hermes Agent) ---
// Вынесен за пределы if/else, чтобы отправляться всегда
$htmlReport .= "<details>\n";
$htmlReport .= "<summary><b>📌 Вчера: " . date('d.m.Y', strtotime('-1 day')) . "</b></summary>\n";

if (empty($yesterdayCategories)) {
    $htmlReport .= "<p>📭 За вчера операций не было.</p>\n";
} else {
    // Сортируем категории за вчера по сумме (по убыванию)
    $yesterdaySorted = array_keys($yesterdayCategories);
    usort($yesterdaySorted, function ($a, $b) use ($yesterdayCategories) {
        return $yesterdayCategories[$b]['sum'] <=> $yesterdayCategories[$a]['sum'];
    });

    $htmlReport .= "<pre>\n";
    $htmlReport .= date('d.m.Y', strtotime('-1 day')) . "\n\n";
    foreach ($yesterdaySorted as $cat) {
        $ySum   = $yesterdayCategories[$cat]['sum'];
        $yCount = $yesterdayCategories[$cat]['count'];
        $sumFmt = number_format($ySum, 0, '.', ' ');
        $htmlReport .= htmlspecialchars($cat) . ": {$sumFmt} {$currencySign}";
        if ($yCount > 1) {
            $htmlReport .= " ({$yCount} шт.)";
        }
        $htmlReport .= "\n";
    }
    $htmlReport .= "---\n";
    $htmlReport .= "Всего: " . number_format($yesterdayTotal, 0, '.', ' ') . " {$currencySign} | Транзакций: {$yesterdayTotalCount}\n";
    $htmlReport .= "</pre>\n";
}

$htmlReport .= "</details>\n";

$sent = sendRichMessageToTelegram($htmlReport);

if ($sent) {
    // good
}

// =========================================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// =========================================================================

/**
 * Забирает операции за период страницами и сохраняет их без изменений в JSON-файл.
 * Ничего не агрегирует и не отправляет в Telegram.
 *
 * @param string      $dateFrom  начало периода (YYYY-MM-DD)
 * @param string      $dateTo    конец периода (YYYY-MM-DD)
 * @param string|null $direction направление операций (null — все)
 * @param string|null $outPath   путь к файлу (null — scripts/alfa_raw/…)
 * @return int код выхода процесса
 */
function dumpAlfaRaw(string $dateFrom, string $dateTo, ?string $direction, ?string $outPath): int
{
    global $apiUrl, $accessToken, $certPath, $keyPath, $passphrase, $caChainPath;

    $limit      = 100;
    $offset     = 0;
    $pagesCount = 0;
    $operations = [];
    $error      = null;
    $errorPage  = null;

    do {
        $queryParams = [
            'dateFrom' => $dateFrom,
            'dateTo'   => $dateTo,
            'limit'    => $limit,
            'offset'   => $offset,
        ];
        if ($direction !== null) {
            $queryParams['operationDirection'] = $direction;
        }

        $queryApiUrl = $apiUrl . '?' . http_build_query($queryParams);

        $apiResponse = alfaCurl($queryApiUrl, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
            ],
        ], $certPath, $keyPath, $passphrase, $caChainPath);

        if ($apiResponse === null) {
            $error = "cURL-ошибка при запросе {$queryApiUrl}";
            break;
        }

        $pageData = json_decode($apiResponse, true);
        if (!is_array($pageData)) {
            $errorPage = ['raw' => $apiResponse];
            $error = 'Ответ API не является JSON';
            break;
        }

        if (!isset($pageData['operations']) || !is_array($pageData['operations'])) {
            $errorPage = $pageData;
            $error = 'Ответ API без ключа operations: '
                . ($pageData['error_description'] ?? $pageData['error'] ?? 'подробности в error_page');
            break;
        }

        $pagesCount++;
        $operations = array_merge($operations, $pageData['operations']);
        $offset += $limit;

    } while (count($pageData['operations']) >= $limit);

    $payload = [
        'meta' => [
            'fetched_at'  => date('c'),
            'environment' => ALFA_PROD ? 'prod' : 'sandbox',
            'date_from'   => $dateFrom,
            'date_to'     => $dateTo,
            'direction'   => $direction ?? 'ALL',
            'pages'       => $pagesCount,
            'operations'  => count($operations),
            'api_url'     => $apiUrl,
        ],
        'operations' => $operations,
    ];

    if ($errorPage !== null) {
        $payload['error_page'] = $errorPage;
    }
    if ($error !== null) {
        $payload['error'] = $error;
    }

    if ($outPath === null) {
        $outDir = __DIR__ . '/alfa_raw';
        if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
            fwrite(STDERR, "❌ Не удалось создать каталог {$outDir}\n");
            return 1;
        }
        $outPath = $outDir . '/alfa_raw_' . $dateFrom . '_' . $dateTo . '_' . date('Ymd-His') . '.json';
    }

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        fwrite(STDERR, "❌ Не удалось сериализовать данные: " . json_last_error_msg() . "\n");
        return 1;
    }

    if (file_put_contents($outPath, $json) === false) {
        fwrite(STDERR, "❌ Не удалось записать файл {$outPath}\n");
        return 1;
    }

    echo "✅ Сырой дамп сохранён: {$outPath}\n";
    echo "   Период: {$dateFrom} – {$dateTo}, направление: " . ($direction ?? 'ALL') . "\n";
    echo "   Страниц: {$pagesCount}, операций: " . count($operations) . "\n";

    if ($error !== null) {
        fwrite(STDERR, "⚠️ Ошибка API: {$error}\n");
        return 1;
    }

    return 0;
}

/**
 * Выполняет cURL-запрос с mTLS-сертификатами Альфы.
 * Возвращает тело ответа или null при ошибке cURL.
 */
function alfaCurl($url, array $extraOptions, $certPath, $keyPath, $passphrase, $caChainPath): ?string
{
    $diag = diagRequest('GET', $url, $extraOptions, $certPath, $keyPath, $passphrase, $caChainPath, 'alfaCurl');

    if ($diag['curl_errno'] !== 0) {
        $msg = "❌ Ошибка cURL [{$url}]: {$diag['curl_error']}";
        echo $msg . "\n";
        sendToTelegram($msg);
        return null;
    }

    return $diag['body'];
}

/**
 * Выполняет диагностический cURL-запрос и возвращает все детали обмена:
 * время с часовым поясом, метод/URI, заголовки и тело запроса, код и
 * заголовки ответа (включая X-TraceId), IP-адреса соединения и ошибку cURL.
 *
 * @param string $method HTTP-метод (GET или POST)
 * @param string $url    полный URL запроса
 * @param array<int, mixed> $extraOptions  опции cURL (CURLOPT_*)
 * @param string      $certPath    путь к mTLS-сертификату
 * @param string      $keyPath     путь к приватному ключу
 * @param string      $passphrase  пароль ключа
 * @param string      $caChainPath путь к цепочке CA
 * @param string      $label       метка для заголовка блока в отчёте
 * @return array{
 *     label: string, timestamp: string, method: string, url: string,
 *     req_headers: array<int, string>, req_body: ?string,
 *     http_code: int, curl_errno: int, curl_error: ?string,
 *     resp_headers: array<int, string>, trace_id: ?string,
 *     body: ?string, remote_ip: ?string, local_ip: ?string, total_time: float
 * }
 */
function diagRequest(string $method, string $url, array $extraOptions, $certPath, $keyPath, $passphrase, $caChainPath, string $label = 'request'): array
{
    $requestHeaders = [];
    $requestBody    = null;

    // Метод определяем по фактическим опциям cURL: CURLOPT_POST надёжнее
    // переданного параметра (alfaCurl() может отправить и POST, и GET).
    $method = strtoupper($method);
    if (!empty($extraOptions[CURLOPT_POST])) {
        $method = 'POST';
    }

    $baseOptions = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSLCERT        => $certPath,
        CURLOPT_SSLKEY         => $keyPath,
        CURLOPT_SSLKEYPASSWD   => $passphrase,
        CURLOPT_CAINFO         => $caChainPath,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HEADER         => true,
        CURLOPT_NOBODY         => false,
    ];

    if (!empty($extraOptions[CURLOPT_POST])) {
        $method = 'POST';
    }

    // Заголовки запроса собираем отдельно, чтобы зафиксировать их в отчёте
    if (isset($extraOptions[CURLOPT_HTTPHEADER]) && is_array($extraOptions[CURLOPT_HTTPHEADER])) {
        $requestHeaders = $extraOptions[CURLOPT_HTTPHEADER];
    }

    if (isset($extraOptions[CURLOPT_POSTFIELDS])) {
        $requestBody = $extraOptions[CURLOPT_POSTFIELDS];
        if (is_string($requestBody)) {
            parse_str($requestBody, $parsedBody);
            $requestBody = $parsedBody ?: $requestBody;
        }
    }

    // Заголовки ответа перехватываем через header-функцию
    $responseHeaders = [];
    $baseOptions[CURLOPT_HEADERFUNCTION] = function ($ch, $header) use (&$responseHeaders) {
        $trimmed = trim($header);
        if ($trimmed !== '') {
            $responseHeaders[] = $trimmed;
        }
        return strlen($header);
    };

    $options = $extraOptions + $baseOptions;

    $ch = curl_init();
    curl_setopt_array($ch, $options);

    $rawResponse = curl_exec($ch);

    $curlErrno   = curl_errno($ch);
    $curlError   = $curlErrno !== 0 ? curl_error($ch) : null;
    $httpCode    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $remoteIp    = curl_getinfo($ch, CURLINFO_PRIMARY_IP) ?: null;
    $localIp     = curl_getinfo($ch, CURLINFO_LOCAL_IP) ?: null;
    $totalTime   = (float) curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $headerSize  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    unset($ch);

    // Отделяем тело от заголовков: при CURLOPT_HEADER=true cURL отдаёт
    // заголовки вместе с телом, CURLINFO_HEADER_SIZE говорит, сколько байт
    // занимают заголовки (включая финальный \r\n\r\n).
    $body = $rawResponse;
    if ($rawResponse !== false && $headerSize > 0) {
        $body = substr($rawResponse, $headerSize);
    } elseif ($rawResponse === false) {
        $body = null;
    }

    // Ищем X-TraceId среди заголовков ответа (регистронезависимо)
    $traceId = null;
    foreach ($responseHeaders as $h) {
        if (preg_match('/^x-traceid\s*:\s*(.+)$/i', $h, $m)) {
            $traceId = trim($m[1]);
            break;
        }
    }

    return [
        'label'        => $label,
        'timestamp'    => date('c'),
        'method'       => $method,
        'url'          => $url,
        'req_headers'  => array_map(
            static fn(string $h) => ALFA_MASK ? maskSensitiveHeader($h) : $h,
            $requestHeaders
        ),
        'req_body'     => $requestBody === null ? null : (ALFA_MASK ? maskSensitiveBody($requestBody) : $requestBody),
        'http_code'    => $httpCode,
        'curl_errno'   => $curlErrno,
        'curl_error'   => $curlError,
        'resp_headers' => $responseHeaders,
        'trace_id'     => $traceId,
        'body'         => $body,
        'remote_ip'    => $remoteIp,
        'local_ip'     => $localIp,
        'total_time'   => $totalTime,
    ];
}

/**
 * Режим --debug: выполняет один диагностический обмен с API Альфы и
 * печатает (и при --out сохраняет) отчёт для техподдержки.
 *
 * Если access_token недоступен — диагностируется запрос токена, чтобы
 * зафиксировать фактическую ошибку авторизации.
 *
 * @param array<string, mixed> $cliOptions  разобранные CLI-опции
 * @param string $accessToken текущий токен или null
 * @return int код выхода процесса
 */
function runAlfaDiag(
    array $cliOptions,
    string $diagFrom,
    string $diagTo,
    ?string $accessToken,
    string $tokenUrl,
    string $apiUrl,
    string $tokenStorage,
    $clientId,
    $clientSecret,
    $certPath,
    $keyPath,
    $passphrase,
    $caChainPath
): int {
    if ($accessToken !== null && $accessToken !== '') {
        $diagMode = 'operations';
        $diagResult = diagRequest('GET', $apiUrl . '?' . http_build_query([
            'dateFrom' => $diagFrom,
            'dateTo'   => $diagTo,
            'limit'    => 1,
        ]), [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
            ],
        ], $certPath, $keyPath, $passphrase, $caChainPath);
    } else {
        // Токена нет — диагностируем запрос токена. Если в хранилище есть
        // refresh_token, используем его, иначе — authorization_code из ENV.
        $savedTokens = [];
        if (is_file($tokenStorage)) {
            $savedTokens = json_decode((string) file_get_contents($tokenStorage), true) ?: [];
        }

        $diagMode   = 'token';
        $tokenGrant = [
            'grant_type' => 'refresh_token',
        ];
        if (!empty($savedTokens['refresh_token'])) {
            $tokenGrant['refresh_token'] = $savedTokens['refresh_token'];
        } else {
            $tokenGrant = [
                'grant_type'   => 'authorization_code',
                'code'         => getenv('ALFA_AUTH_CODE') ?: '<нет ALFA_AUTH_CODE>',
                'redirect_uri' => 'https://pashaman.dev/alfa/connect/',
                'scope'        => 'openid accounts cards operations-history',
            ];
        }

        $diagResult = diagRequest('POST', $tokenUrl, [
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => http_build_query($tokenGrant + [
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
            ]),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ], $certPath, $keyPath, $passphrase, $caChainPath);
    }

    $diagReport = buildDiagReport($diagResult, $diagMode, (string) $clientId);
    echo $diagReport;

    $diagOutPath = cliOption($cliOptions, 'out');
    if ($diagOutPath === null) {
        $diagOutDir = __DIR__ . '/alfa_debug';
        if (!is_dir($diagOutDir) && !mkdir($diagOutDir, 0775, true) && !is_dir($diagOutDir)) {
            fwrite(STDERR, "❌ Не удалось создать каталог {$diagOutDir}\n");
            return 1;
        }
        $diagOutPath = $diagOutDir . '/alfa_debug_' . date('Ymd-His') . '.txt';
    }

    $diagDir = dirname($diagOutPath);
    if (!is_dir($diagDir) && !mkdir($diagDir, 0775, true) && !is_dir($diagDir)) {
        fwrite(STDERR, "❌ Не удалось создать каталог {$diagDir}\n");
        return 1;
    }

    if (file_put_contents($diagOutPath, $diagReport) === false) {
        fwrite(STDERR, "❌ Не удалось записать файл {$diagOutPath}\n");
        return 1;
    }

    echo "\n✅ Отчёт сохранён: {$diagOutPath}\n";

    return ($diagResult['curl_errno'] === 0 && $diagResult['http_code'] < 400) ? 0 : 1;
}

/**
 * Маскирует Bearer-токен в заголовке Authorization.
 */
function maskSensitiveHeader(string $header): string
{
    if (stripos($header, 'authorization:') === 0) {
        return preg_replace('/^(authorization:\s*bearer\s+).+$/i', '$1<*>' , $header);
    }
    return $header;
}

/**
 * Маскирует секретные поля в теле запроса (client_secret, refresh_token, code).
 *
 * @param array<string, mixed>|string $body
 * @return array<string, mixed>|string
 */
function maskSensitiveBody(array|string $body): array|string
{
    if (!is_array($body)) {
        return $body;
    }

    $secretKeys = ['client_secret', 'refresh_token', 'code', 'assertion'];
    foreach ($secretKeys as $key) {
        if (isset($body[$key]) && is_string($body[$key]) && $body[$key] !== '') {
            $body[$key] = '<*>';
        }
    }

    return $body;
}

/**
 * Формирует человеко-читаемый отчёт для отправки в техподдержку Альфа-Банка.
 *
 * @param array<string, mixed> $result результат diagRequest()
 * @param string $mode   'operations' или 'token'
 * @param string $clientId идентификатор клиента (для контекста)
 */
function buildDiagReport(array $result, string $mode, string $clientId): string
{
    $lines = [];
    $lines[] = '=== ДИАГНОСТИКА ЗАПРОСА К АЛЬФА-БАНКУ ===';
    $lines[] = 'Режим: ' . ($mode === 'token' ? 'запрос токена' : 'операции (выписка)');
    $lines[] = 'Окружение: ' . (ALFA_PROD ? 'PROD' : 'SANDBOX');
    $lines[] = 'Клиент ID: ' . $clientId;
    $lines[] = '';

    $lines[] = '--- 1. ДАТА И ВРЕМЯ ЗАПРОСА ---';
    $lines[] = $result['timestamp'] . ' (' . date_default_timezone_get() . ', UTC' . date('P') . ')';
    $lines[] = '';

    $lines[] = '--- 2. МЕТОД И URI ---';
    $lines[] = $result['method'] . ' ' . $result['url'];
    $lines[] = '';

    $lines[] = '--- 3. ЗАГОЛОВКИ И ТЕЛО ЗАПРОСА ---';
    if (empty($result['req_headers'])) {
        $lines[] = '(заголовки не заданы явно — cURL добавит стандартные)';
    } else {
        foreach ($result['req_headers'] as $h) {
            $lines[] = $h;
        }
    }
    $lines[] = '';
    $lines[] = 'Тело запроса:';
    if ($result['req_body'] === null) {
        $lines[] = '(тело отсутствует)';
    } elseif (is_array($result['req_body'])) {
        $lines[] = http_build_query($result['req_body']);
    } else {
        $lines[] = $result['req_body'];
    }
    $lines[] = '';

    $lines[] = '--- 4. ПОЛУЧЕННАЯ ОШИБКА / КОД ОТВЕТА ---';
    $lines[] = 'HTTP-код: ' . ($result['http_code'] ?: 'нет ответа');
    if ($result['curl_errno'] !== 0) {
        $lines[] = 'Ошибка cURL [' . $result['curl_errno'] . ']: ' . $result['curl_error'];
    }
    if ($result['body'] !== null && $result['body'] !== '') {
        $lines[] = 'Тело ответа:';
        $lines[] = prettyJson($result['body']);
    }
    $lines[] = '';

    $lines[] = '--- 5. ЗАГОЛОВКИ ОТВЕТА (включая X-TraceId) ---';
    if (empty($result['resp_headers'])) {
        $lines[] = '(заголовки ответа не получены)';
    } else {
        foreach ($result['resp_headers'] as $h) {
            $lines[] = $h;
        }
    }
    $lines[] = '';
    $lines[] = 'X-TraceId: ' . ($result['trace_id'] ?? '(не найден)');
    $lines[] = '';

    $lines[] = '--- 6. IP-АДРЕСА СОЕДИНЕНИЯ ---';
    $publicIp = resolveEgressIp();
    $lines[] = 'Внешний IP (с которого уходит запрос в интернет): ' . ($publicIp ?? '—');
    $lines[] = 'Локальный IP (внутренний интерфейс, источник запроса): ' . ($result['local_ip'] ?? '—');
    $lines[] = 'Удалённый IP (Альфа-Банк): ' . ($result['remote_ip'] ?? '—');
    $lines[] = 'Время выполнения: ' . round($result['total_time'], 3) . ' сек';
    $lines[] = '';

    return implode("\n", $lines);
}

/**
 * Форматирует тело ответа: JSON — с отступами, остальное — как есть.
 */
function prettyJson(string $body): string
{
    $decoded = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $body;
    }

    $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $pretty === false ? $body : $pretty;
}

/**
 * Определяет внешний (публичный) IP, с которого уходят запросы.
 * Используется тот же прокси, что и для запросов к Альфе, поэтому адрес
 * отражает реальный egress-маршрут. При недоступности сервиса — null.
 */
function resolveEgressIp(): ?string
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://api.ipify.org?format=json',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    foreach (proxyCurlOptions() as $option => $value) {
        curl_setopt($ch, $option, $value);
    }

    $response = curl_exec($ch);
    $errno    = curl_errno($ch);
    unset($ch);

    if ($errno !== 0 || !is_string($response)) {
        return null;
    }

    $data = json_decode($response, true);
    $ip   = $data['ip'] ?? null;

    return is_string($ip) && $ip !== '' ? $ip : null;
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
    $richMessage = [
        'html' => $html,
    ];

    try {
        $result = (new TelegramClient(TG_BOT_TOKEN))->call('sendRichMessage', [
            'chat_id'      => TG_CHAT_ID,
            'rich_message' => $richMessage,
        ]);
    } catch (TelegramException $e) {
        echo "❌ Системная ошибка при отправке rich message в Telegram: " . $e->getMessage() . "\n";
        return false;
    }

    if ($result->ok()) {
        return true;
    }

    echo "❌ Telegram API вернул ошибку при отправке rich message: [" . ($result->errorCode() ?? '???') . "] " . ($result->description() ?? 'Неизвестная ошибка') . "\n";

    // Авто-фолбек: если rich message не поддерживается — пробуем отправить обычным текстом
    $description = $result->description();
    if ($description !== null && strpos($description, 'rich') !== false) {
        echo "⚠️ Rich message не поддерживается, пробую отправить обычным текстом…\n";
        $plainText = "🤖 Alfa API: Аналитика расходов\n\n" . strip_tags($html);
        return sendToTelegram($plainText, null);
    }

    return false;
}

function sendToTelegram($text, $parseMode = 'Markdown') {
    // В режиме диагностики и сырого дампа Telegram не трогаем — всё выводим в консоль
    if (ALFA_RAW || ALFA_DIAG) {
        $tag = ALFA_DIAG ? 'debug' : 'raw';
        echo "ℹ️ [{$tag}] Telegram отключён: " . strip_tags($text) . "\n";
        return false;
    }

    // Защита от превышения лимита ТГ (макс 4096 символов)
    if (mb_strlen($text) > 4000) {
        $text = mb_substr($text, 0, 3800) . "\n\n… [обрезано: превышен лимит Telegram в 4096 символов]";
    }

    try {
        $result = (new TelegramClient(TG_BOT_TOKEN))->sendMessage(TG_CHAT_ID, $text, $parseMode);
    } catch (TelegramException $e) {
        echo "❌ Ошибка отправки в Telegram: " . $e->getMessage() . "\n";
        return false;
    }

    if ($result->ok()) {
        return true;
    }

    $code = $result->errorCode()  ?? '???';
    $desc = $result->description() ?? 'Неизвестная ошибка';
    echo "❌ Telegram API вернул ошибку: [{$code}] {$desc}\n";

    // Авто-фолбек: если ТГ ругается на разметку — пробуем послать голым текстом
    if ($parseMode !== null && $result->description() !== null && strpos($result->description(), 'parse') !== false) {
        echo "⚠️ Пробую переотправить как plain text…\n";
        $plainText = str_replace(['```json', '```', '*', '`'], '', $text);
        return sendToTelegram($plainText, null);
    }

    return false;
}