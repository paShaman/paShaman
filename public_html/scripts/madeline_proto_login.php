<?php

error_reporting(E_ALL & ~E_DEPRECATED); // Отключаем вывод Deprecated, чтобы не забивать консоль
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once __DIR__ . '/../../vendor/autoload.php';
include __DIR__ . '/_env.php';
require_once __DIR__ . '/clients/madeline_client.php';

$userId = (int)getenv('TG_CHAT_ID'); // ID твоего друга (или сделать ввод из консоли)

// При первом входе start() запустит интерактивный ввод телефона и кода из Telegram прямо в консоли
(new MadelineClient())->start($userId);

echo "Сессия для пользователя {$userId} успешно создана и готова!";