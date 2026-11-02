@echo off
chcp 65001 >nul
title NY2027 bot
cd /d "%~dp0"

:loop
php glopro_ny2027_bot.php --listen
echo.
echo Bot stopped. Restarting in 5 seconds...
timeout /t 5 /nobreak >nul
goto loop
