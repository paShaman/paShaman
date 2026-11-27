# Тайный Санта — Telegram Serverless

Бот + Mini App для обмена подарками. Кампания создаётся в боте, участники заполняют
вишлист и общаются анонимно в приложении, жеребьёвка и админка — в боте.

Код готов к локальным проверкам; деплой (`push`/`migrate`) выполняет пользователь.

## Что уже есть

- Бот: `/start`, `/new`, `/my`, `/help`, `/cancel`, `/privacy`, `/id`, `/admin`, `/stats`,
  `/ban`, `/unban`, `/setcommands`.
- Визард кампании, код приглашения, дашборд, участники, исключения.
- Жеребьёвка: превью, замена получателя у одной пары, полная пересборка, подтверждение.
- Рассылка по подтверждённым участникам с превью, лимитом и отчётом.
- Endpoints: кампания, правила, участие, вишлист, фото, claims, пара, чат, жалобы,
  статус подарка, профиль.
- Mini App: онбординг, кампании, лобби с формой доставки, вишлист с фото, получатель,
  чат, подарок, профиль.
- Чистая логика покрыта `node --test` (42 теста).

## Структура

```
tgcloud/            код Serverless (schema, lib, handlers, endpoints)
miniapp/            Vue 3 + Vite + Tailwind 4, сборка в miniapp/dist
tests/              node --test, не деплоится
docs/               SDK, аватары/превью
scripts/            optimize-assets.ps1 (Windows, System.Drawing)
```

## Настройка

В `tgcloud/lib/config.js`:

- `ADMIN_IDS` — Telegram ID супер-админов: `115462629`;
- `BOT_USERNAME` — `pod_elochku_bot`, для инвайт-ссылок;
- `APP_URL` — `https://app8782869988.tgcloud.ai/`.

Инвайт-ссылки имеют вид `https://t.me/pod_elochku_bot?start=join_<код>`.
Mini App уже отдаётся по указанному URL; после правки `APP_URL` нужен повторный
`npx tgcloud push`, чтобы бот начал показывать кнопку приложения.

## Локальные проверки

```powershell
node --test "tests/*.test.js"
Get-ChildItem tgcloud -Recurse -Filter *.js | ForEach-Object { node --check $_.FullName }
npm --prefix miniapp install
npm --prefix miniapp run build
npm --prefix miniapp run dev   # демо-режим вне Telegram
```

`npm --prefix miniapp run dev` без Telegram включает mock: данные в памяти, endpoint-ы
не вызываются. Внутри Telegram используется `Telegram.WebApp.Serverless.call`.

## Деплой

Выполняет пользователь:

```powershell
npm install
npx tgcloud login
npm --prefix miniapp run build
npx tgcloud push          # печатает URL Mini App
npx tgcloud migrate --dry-run
npx tgcloud migrate
npx tgcloud status        # Working directory clean
npx tgcloud webhook       # in sync
```

После `push`:

1. `APP_URL` уже записан; после изменений конфига повторить `npx tgcloud push`.
2. Поставить аватар из `miniapp/public/avatar-640.jpg`.
3. В BotFather настроить Mini App / кнопку меню на URL.
4. В боте выполнить `/setcommands` (супер-админ).
5. Прогнать сценарий на тестовых аккаунтах.

`tgcloud pull` не запускать: он перезаписывает локальные файлы. `push` не собирает
фронтенд.

## Ограничения и что проверить на реальном боте (этап 1 плана)

- Лимит JSON-тела и ответа endpoint не измерен: от него зависит `MAX_PHOTO_BYTES`
  (сейчас 320 КБ, клиент сжимает до 1280 px).
- `push`/`migrate` уже выполнялись (ревизия 9, `webhook` in sync); после правок схемы
  `migrate` запускает пользователь.
- Синхронная рассылка ограничена 100 получателями; кампания — 500 участниками.
- Автоматических напоминаний нет: только ручная рассылка организатора.
- Дизайн проверен в Chrome на 320/390/768/1280 и в светлой/тёмной теме; реальный
  Telegram WebView и Windows scaling ещё нужно проверить.

## Исходники дизайна

`docs/avatar.png` и `docs/preview.jpg` — исходники. Производные лежат в
`miniapp/src/assets/` и `miniapp/public/`; пересобрать:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/optimize-assets.ps1
```
