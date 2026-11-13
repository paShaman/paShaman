# AGENTS.md — vacation-bot

Проектные заметки. Общие правила платформы, модулей и деплоя — в
[`../AGENTS.md`](../AGENTS.md) (обязательны: `push` и `migrate` делает только пользователь).

## Что это

Бот учёта отпусков/отсутствий на Telegram serverless platform. Mini App нет,
поэтому в `tgcloud.jsonc` нет блока `static`.

## Устройство

| Путь                 | Что там                                                              |
|----------------------|----------------------------------------------------------------------|
| `tgcloud/schema.js`  | Таблицы `users`, `records`, `sessions` (FK нет — связи в коде).       |
| `tgcloud/lib/bot.js` | Маршрутизация команд и текстовых шагов диалога.                      |
| `tgcloud/lib/store.js` | Вся работа с БД: пользователи, записи, сессии, остатки отпуска.    |
| `tgcloud/lib/config.js` | `ADMIN_ID`, `DEFAULT_ANNUAL_DAYS`, `QUOTA_TYPES`, `SESSION_TTL`, типы отсутствий. |
| `tgcloud/handlers/`  | `message.js`, `callback_query.js`.                                   |
| `tgcloud/endpoints/` | Эндпоинты Mini App — в этом боте не используются.                   |

## Домен

- `ADMIN_ID` в `tgcloud/lib/config.js` — единственный админ, `isAdmin` сравнивает строки.
- `annual_days` nullable; `null` → норма `DEFAULT_ANNUAL_DAYS` (28).
- В годовой остаток идут только типы из `QUOTA_TYPES` (сейчас `отпуск`).
- Сессии диалога живут `SESSION_TTL` (1800 с) и удаляются лениво при чтении.
