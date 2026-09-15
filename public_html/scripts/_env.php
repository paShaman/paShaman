<?php

loadEnv(__DIR__ . '/../../.env');

/**
 * Загрузка переменных окружения из .env файла
 */
function loadEnv($path): void {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Пропускаем строки-комментарии (начинающиеся с #)
        $trimmedLine = trim($line);
        if ($trimmedLine === '' || $trimmedLine[0] === '#') {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Убираем комментарии в конце строки (всё после #, если # не внутри кавычек)
            $value = preg_replace('/\s*#.*$/', '', $value);
            $value = trim($value);

            // Убираем кавычки если есть
            $value = trim($value, '"\'');

            // Устанавливаем переменную окружения
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

/**
 * Опции cURL для запросов к Telegram Bot API через прокси.
 *
 * Прокси задаётся одной переменной TG_PROXY с полным URL, напр.:
 *   socks5h://user:pass@proxy.example:1080
 *   http://proxy.example:3128
 *
 * Пустой массив в ответе означает «TG_PROXY не задан» — соединение прямое.
 *
 * @return array<int, mixed>
 */
function tgProxyCurlOptions(): array
{
    $proxy = trim((string) getenv('TG_PROXY'));
    if ($proxy === '') {
        return [];
    }

    $scheme = strtolower((string) parse_url($proxy, PHP_URL_SCHEME));

    return match ($scheme) {
        'socks4'          => [CURLOPT_PROXY => $proxy, CURLOPT_PROXYTYPE => CURLPROXY_SOCKS4],
        'socks4a'         => [CURLOPT_PROXY => $proxy, CURLOPT_PROXYTYPE => CURLPROXY_SOCKS4A],
        // socks5h — DNS резолвится на стороне прокси (нужно, если api.telegram.org недоступен у хоста)
        'socks5h'         => [CURLOPT_PROXY => $proxy, CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME],
        'socks5', 'socks' => [CURLOPT_PROXY => $proxy, CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5],
        // HTTP(S)-прокси: для https:// целевого сайта нужен CONNECT-туннель
        'http', 'https'   => [CURLOPT_PROXY => $proxy, CURLOPT_HTTPPROXYTUNNEL => true],
        default           => [CURLOPT_PROXY => $proxy],
    };
}

/**
 * Опции прокси только для адресов на api.telegram.org — остальные URL идут напрямую.
 *
 * @return array<int, mixed>
 */
function tgProxyCurlOptionsForUrl(string $url): array
{
    return str_starts_with($url, 'https://api.telegram.org/') ? tgProxyCurlOptions() : [];
}
