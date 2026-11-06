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
 * Опции cURL для прокси из переменной PROXY.
 *
 * Поддерживаются socks5h/socks5/socks4(a) и http(s). Пустая переменная
 * означает «прокси не задан» — возвращается пустой массив (прямое соединение).
 *
 * @return array<int, mixed>
 */
function proxyCurlOptions(): array
{
    $proxy = trim((string) getenv('PROXY'));
    if ($proxy === '') {
        return [];
    }

    $scheme = strtolower((string) parse_url($proxy, PHP_URL_SCHEME));

    return match ($scheme) {
        'socks4'          => [CURLOPT_PROXY => $proxy, CURLOPT_PROXYTYPE => CURLPROXY_SOCKS4],
        'socks4a'         => [CURLOPT_PROXY => $proxy, CURLOPT_PROXYTYPE => CURLPROXY_SOCKS4A],
        // socks5h — DNS резолвится на стороне прокси
        'socks5h'         => [CURLOPT_PROXY => $proxy, CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME],
        'socks5', 'socks' => [CURLOPT_PROXY => $proxy, CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5],
        // HTTP(S)-прокси: для https:// целевого сайта нужен CONNECT-туннель
        'http', 'https'   => [CURLOPT_PROXY => $proxy, CURLOPT_HTTPPROXYTUNNEL => true],
        default           => [CURLOPT_PROXY => $proxy],
    };
}
