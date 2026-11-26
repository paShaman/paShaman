# AGENTS.md — secret-santa-tgcloud

Правила проекта. Общие правила платформы и деплоя — в [`../AGENTS.md`](../AGENTS.md);
справочник SDK — в [docs/tgcloud-sdk.md](docs/tgcloud-sdk.md).

## Что это

Бот «Тайный Санта» на Telegram Serverless: организатор создаёт кампанию в боте,
участники заполняют вишлист и общаются в Mini App (Vue 3 + Vite), жеребьёвка и
админка — в боте. Отдельного сервера нет.

## Устройство

| Путь | Что там |
|---|---|
| `tgcloud/schema.js` | Все таблицы SQLite; FK нет, связи в коде. |
| `tgcloud/lib/config.js` | `ADMIN_IDS`, `BOT_USERNAME`, `APP_URL`, лимиты, правила по умолчанию. |
| `tgcloud/lib/store.js` | Вся работа с БД; без текстов и Telegram. |
| `tgcloud/lib/telegram.js` | Bot API, HTML→plain fallback, фото, рассылка, `/setcommands`. |
| `tgcloud/lib/access.js` | Проверки пользователя/участника/Санты для endpoints. |
| `tgcloud/lib/domain/` | Чистая логика: жеребьёвка, коды, вишлист, чат. Без `sdk`. |
| `tgcloud/lib/bot/` | Сценарии бота и роутер; handlers — тонкие делегаты. |
| `tgcloud/endpoints/` | API Mini App; авторизация только через `ctx.initData.user`. |
| `miniapp/` | Vue 3 + Vite + Tailwind; сборка в `miniapp/dist`. |
| `tests/` | `node --test`; **не** в `tgcloud/`, иначе уедет в облако. |

## Домен

- Кампания: `recruiting → locked → drawn → gifting → completed`, либо `cancelled`.
- Участие: `joined → rules_accepted → confirmed`; в жеребьёвку попадают только `confirmed`.
- Вишлист и фото видит владелец и его Санта; адрес — только Санта; claims — только Санта.
- Переписка анонимна; `senderUserId` никогда не отдаётся клиенту.
- `delivery_info` и `claims` удаляются при завершении/отмене; чат остаётся для жалоб.
- Жеребьёвка: Хопкрофт–Карп, исключения и self запрещены; «заменить получателя» — обмен двух пар.

## UI

Иконки — только через `miniapp/src/components/ui/AppIcon.vue` (инлайн-SVG, `currentColor`),
без текстовых символов вместо иконок (`←`, `↑`, `✓`, `×`, `+`/`−`). Размер и толщина —
пропсами `size` и `stroke-width`.

## Проверки

```powershell
node --test "tests/*.test.js"
Get-ChildItem tgcloud -Recurse -Filter *.js | ForEach-Object { node --check $_.FullName }
npm --prefix miniapp run build
```

`npx tgcloud run` использует реальные БД/Bot API: смотреть, что отправляется, и не нажимать
опасные подтверждения в тестах.

## Деплой

`push`, `migrate`, `pull`, `webhook sync`, `login`, `npm run deploy` — **только пользователь**.
Агент может: править файлы, `status`, `diff`, `migrate --dry-run`, `run` read-only модулей,
`node --check`, `node --test`, `npm --prefix miniapp run build`.

`push` не собирает фронтенд: сначала `npm --prefix miniapp run build`, потом `push`.
После первого `push` записать напечатанный URL в `APP_URL` и юзернейм бота в `BOT_USERNAME`.
`tgcloud init` на этой Windows-машине падает — проект создаётся вручную.
