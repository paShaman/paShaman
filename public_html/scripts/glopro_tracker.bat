@echo off
:: UTF-8 в консоли: иначе PHP-вывод с кириллицей превращается в «кракозябры»
chcp 65001 >nul

:: Запускаем листенер в фоновом режиме
start /b php d:\Work\paShaman.ru\site\public_html\scripts\glopro_tracker_bot.php --listen

:: Запускаем цикл
powershell -Command "while ($true) { php d:\Work\paShaman.ru\site\public_html\scripts\glopro_tracker_bot.php; Start-Sleep -Seconds 300 }"