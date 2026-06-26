<?php

error_reporting(E_ALL & ~E_DEPRECATED); // Отключаем вывод Deprecated, чтобы не забивать консоль
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once __DIR__ . '/../../vendor/autoload.php';
include __DIR__ . '/_env.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;

$userId = (int)getenv('TG_CHAT_ID'); // ID твоего друга (или сделать ввод из консоли)

$settings = new Settings();
$appInfo = new AppInfo();
$appInfo->setApiId((int)getenv('TG_APP_ID'));
$appInfo->setApiHash((string)getenv('TG_APP_HASH'));
$settings->setAppInfo($appInfo);

$MadelineProto = new API('session_' . $userId, $settings);
// Запустится интерактивный ввод телефона и кода из Telegram прямо в консоли
$MadelineProto->start();

echo "Сессия для пользователя {$userId} успешно создана и готова!";