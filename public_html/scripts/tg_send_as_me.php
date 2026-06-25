<?php

error_reporting(E_ALL & ~E_DEPRECATED); // Отключаем вывод Deprecated, чтобы не забивать консоль
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once __DIR__ . '/../../vendor/autoload.php';

use danog\MadelineProto\API;
// Импортируем классы настроек для нового синтаксиса
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;

// Создаем объект настроек вместо массива
$settings = new Settings();

$appInfo = new AppInfo();
$appInfo->setApiId(39225923);
$appInfo->setApiHash('59f2818f5415c8adea4fb27e9ac14d4c');

$settings->setAppInfo($appInfo);

try {
    // Передаем объект настроек $settings
    $MadelineProto = new API('session.madeline', $settings);

    // Запускаем сессию
    $MadelineProto->start();

    $chatId = -1001507435988;
    $message = "Привет всем! Это сообщение отправлено автоматически, но прямо от моего имени!";

    echo "Обновляем базу данных peer-ов для чата...\n";
    // Принудительно получаем информацию о чате, чтобы занести его в базу MadelineProto
    //$chatInfo = $MadelineProto->getInfo('https://t.me/+0bD52d3RwQ1iNGRi');

    $MadelineProto->messages->sendMessage(peer: $chatId, message: $message);

    echo "\n[Успех] Сообщение успешно отправлено от твоего имени!\n";
} catch (\Throwable $e) {
    echo "\n[Ошибка] Критический запуск прерван: " . $e->getMessage() . "\n";
    echo "В файле: " . $e->getFile() . " на строке " . $e->getLine() . "\n";
}