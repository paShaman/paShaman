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
