# План разработки: «Тайный Санта» — Telegram Mini App

Статус: реализовано локально (этапы 0–6, 8 частично), деплой и замеры платформы ещё не выполнялись.
Платформа: Telegram Serverless (`tgcloud`: V8 isolate, SQLite, статика Mini App).
Фронтенд: Vite + Vue 3 + Tailwind 4 (отдельный npm-проект внутри `miniapp/`).
Дата: 2026-11-26. Версия плана: 2 (переработан по практике `ny2027-tgcloud` и общим правилам `serverless/AGENTS.md`).

Документ описывает продукт, платформенные ограничения, структуру кода, модель данных,
дизайн-систему по ассетам из `docs/`, API, этапы работ и проверки. Реализация начинается
с этапа 0; до него ничего в `tgcloud/` не создаётся.

Содержание:

0. Что изменилось относительно черновика
1. Продукт
2. Практики, которые перенимаем у `ny2027-tgcloud`
3. Платформа: ограничения, которые формируют архитектуру
4. Структура проекта
5. Модель данных
6. Статусы и переходы
7. Жеребьёвка
8. Бот: команды, сценарии, callback-и
9. Endpoints Mini App
10. Веб-апп
11. Дизайн-система по ассетам из `docs/`
12. Фото вишлиста
13. Уведомления
14. Безопасность и приватность
15. Этапы разработки
16. Проверки и тесты
17. Деплой
18. Риски
19. Открытые вопросы
20. Что нужно от пользователя перед стартом
21. Definition of Done
22. Статус реализации (2026-11-26)

Для быстрого чтения: разделы 1–14 — решения, 15–17 — как делать и проверять, 18–21 —
риски, вопросы и критерии готовности.

## 0. Что изменилось относительно черновика

- Правила платформы вынесены в отдельный раздел и превращены в инженерные требования
  (лимиты callback_data, размеры сообщений, отсутствие FK, запрет `push`/`migrate` агентом).
- Структура повторяет проверенную схему `ny2027-tgcloud`: тонкие handlers, `lib/config`,
  `lib/utils`, `lib/telegram`, `lib/store`, доменные модули без SDK.
- Добавлены практики, которые в `ny2027` уже отработали: HTML→plain fallback при отправке,
  разбиение длинных сообщений, `answerCallbackQuery` до тяжёлой работы, TTL сессий,
  токен против устаревших кнопок, лимит и отчёт рассылки, обработка `403`/`deactivated`,
  `InputFile` вместо внешних URL, `web_app` с fallback на `url`, `/setcommands`.
- Зафиксированы реальные лимиты Telegram: `callback_data` ≤ 64 байт, текст сообщения ≤ 4096,
  фото ≤ 10 МБ, `getFile` ≤ 20 МБ — они влияют на схему callback-ов и пайплайн фото.
- Тесты вынесены в `tests/` в корне проекта: всё, что лежит в `tgcloud/`, деплоится,
  поэтому тестовые файлы там держать нельзя.
- Дизайн-система выведена из `docs/avatar.png` и `docs/preview.jpg`: палитра, типографика,
  характер иллюстраций, мотивы и правила анимации.
- Добавлены ограничения v1 (участники, рассылка, размер фото) и явные ворота проверки
  на каждом этапе.

## 1. Продукт

«Тайный Санта» для одной компании, семьи или круга друзей: организатор создаёт кампанию,
участники вступают по ссылке, заполняют вишлист, бот проводит жеребьёвку, участники
анонимно общаются и дарят подарки. Вся админка — в боте, участническая часть — в Mini App.

Проект полностью автономен в `serverless/secret-santa-tgcloud/`; корневое Laravel-приложение
и публичный сайт не меняются.

### Зафиксированные решения

| Вопрос | Решение |
|---|---|
| Кто создаёт кампанию | Любой пользователь; он организатор своей кампании. Плюс супер-админ бота из конфига |
| Вступление | Deep link в бот (`?start=join_<код>`) и/или код кампании в веб-апп |
| Жеребьёвка | Бот собирает пары автоматически; организатор видит превью, может пересобрать отдельную пару, затем подтверждает |
| Условия участия | Принять актуальные правила + явное «Участвую» + минимум 1 пункт вишлиста |
| Вишлист | Название, ссылка, цена/диапазон, фото, комментарий, приватная отметка «куплено» |
| Анонимность | Санта анонимен до завершения кампании; переписка в обе стороны через бота |
| Бюджет и сроки | Фиксированный диапазон бюджета, дата конца набора и дата вручения |
| Подарки | Физические; встреча, доставка или гибрид |
| Видимость вишлиста | Только Санте после жеребьёвки; организатор видит прогресс, но не содержимое |
| Напоминания | Только ручная рассылка организатором; автоматических cron-задач на платформе нет |
| Веб-апп | Vite + Vue 3 + Tailwind 4, уютный Новый год по ассетам из `docs/` |
| Админка | Только в боте; в веб-апп админских экранов нет |
| Лимит участников | Продуктово без лимита; технический предел v1 — 500 подтверждённых участников на кампанию |
| Раскрытие Санты | После завершения кампании организатором, если включено в настройках кампании (по умолчанию включено) |

Отметка «куплено» — приватный чек-лист Санты. Получатель её не видит, организатор тоже;
она не влияет на других участников, потому что у получателя ровно один Санта.

### Что не входит в v1

- Автоматические напоминания и любые фоновые задачи по расписанию.
- Платежи, сбор денег, заказ подарков внутри приложения.
- Видео, голосовые сообщения, файлы в чате.
- Публичные кампании и поиск чужих кампаний.
- Несколько Санта-пар на одного участника, «цепочки» и командные подарки.
- Экспорт в CSV и админка в веб-апп.

### Критерий успеха v1

Полный сценарий в реальном Telegram без ручных правок БД: создание кампании → вступление
по ссылке → правила → вишлист → жеребьёвка → превью и подтверждение → получение пары →
анонимный чат → статус подарка → завершение и раскрытие. Все негативные сценарии из
раздела 14 дают понятный отказ, а не 500.

## 2. Практики, которые перенимаем у `ny2027-tgcloud`

Это не пожелания, а решения, проверенные в соседнем проекте. Ссылки — на рабочий код,
чтобы при реализации было откуда взять образец.

| Практика | Как применяем | Источник |
|---|---|---|
| Тонкие handlers | `handlers/message.js` и `callback_query.js` только вызывают `lib/bot/*` | `ny2027-tgcloud/tgcloud/handlers/message.js:1` |
| Один слой Bot API | Все `api.*` вызовы, fallback, edit/answer — в `lib/telegram.js` | `tgcloud/lib/telegram.js` |
| HTML→plain fallback | Если `sendMessage` с HTML упал — повтор без разметки, один раз | `telegram.js:6-46` |
| Разбиение длинных текстов | `splitMessage` по 4096 с переносом по строкам, клавиатура только на последнем чанке | `telegram.js:25-45`, `telegram.js:6-19` |
| `answerCallbackQuery` первым | Сначала подтверждаем callback, потом читаем БД и правим сообщение | `lib/bot.js:287` |
| Редактирование вместо спама | Навигация по дашборду через `editMessageText`, при ошибке — fallback на новое сообщение | `telegram.js:48-67`, `bot.js:834-837` |
| TTL сессий | Сессия живёт 1800–3600 с, истекает лениво при чтении, `/cancel` чистит | `lib/store.js:214-242` |
| Защита от устаревших кнопок | В callback зашит токен версии; несовпадение → «данные изменились» | `lib/bot.js:778-810` |
| Рассылка с превью | Текст → превью → подтверждение → отчёт «отправлено/заблокировали/ошибки» | `lib/bot.js:843-962` |
| Обработка блокировок | `403` и `chat not found`/`deactivated` помечают пользователя заблокировавшим | `telegram.js:142-166` |
| Лимит получателей | Синхронная рассылка ограничена `MAX_BROADCAST`; сверх лимита — отказ с объяснением | `lib/config.js:23`, `bot.js:920-926` |
| Файлы без внешних URL | `InputFile` с байтами из модуля; при ошибке — текстовая версия | `telegram.js:102-126` |
| `web_app` с fallback | Кнопка Mini App, при отказе — обычная `url`-кнопка на тот же адрес | `lib/game.js:8-48` |
| Меню команд через `/setcommands` | Админская команда вызывает `setMyCommands`; список живёт в `lib/telegram.js` | `telegram.js:79-100`, README:185-199 |
| Идемпотентные записи | `onConflictDoUpdate` по уникальному ключу, повторный клик не создаёт дубль | `store.js:34-48`, `store.js:222-237` |
| Все запросы с `await` | Даже `.run()` и `db.$count()`; забытый `await` — баг | `serverless/AGENTS.md` |
| Индексы под реальные запросы | Составные индексы по кампании/статусу/дате, а не «на всякий случай» | `schema.js:29-33` |
| Отсутствие FK | Целостность в коде: сначала дети, потом родитель, сироты вычищаются | `serverless/AGENTS.md` |
| Безопасность деплоя | Агент делает только `status`/`diff`/`migrate --dry-run`/`run`; `push`/`migrate`/`pull` — пользователь | `serverless/AGENTS.md` |
| Windows-обходы | `tgcloud init` на этой машине падает — проект собирается вручную; `run` на PowerShell — JSON5 в одинарных кавычках | README:213-220, 276-281 |
| Проверка синтаксиса | `node --check` по всем деплоимым `.js` перед прогонами | README:203-211 |
| Документы рядом с кодом | `AGENTS.md` проекта + копия `docs/tgcloud-sdk.md` как справочник SDK | `ny2027-tgcloud/AGENTS.md`, `docs/tgcloud-sdk.md` |

Что **не** переносим:

- Хранение `file_id` и внешних URL для брендовой картинки: у нас ассеты лежат в проекте,
  бот отправляет их через `InputFile`.
- Хранение дат строками `sv-SE`: в новом проекте используем `integer(..., { mode: 'timestamp' })`.
- Синхронные массовые циклы без ограничения: у нас есть явные пределы и отчёт.
- Отсутствие тестов: `ny2027` обходился `node --check`, но у «Тайного Санты» есть чистая
  логика (жеребьёвка, коды, переходы), которую обязательно покрываем `node --test`.

## 3. Платформа: ограничения, которые формируют архитектуру

Рантайм и SDK подробно описаны в `docs/tgcloud-sdk.md` (копируется из
`serverless/ny2027-tgcloud/docs/tgcloud-sdk.md` на этапе 0). Здесь — только то, что
напрямую влияет на решения.

### Код и модули

- V8 isolate: нет `node_modules` и файловой системы; доступны `sdk` и модули проекта.
  Фронтенд — обычный браузерный код с npm-пакетами, но он не импортируется из `tgcloud/`.
- Импорт своего модуля — относительный путь с `.js`: `import { campaigns } from '../schema.js'`.
  SDK — по имени: `from 'sdk'`, `from 'sdk/db'`. Импорт без `.js` для относительного пути падает.
- Деплоится только `tgcloud/schema.js`, `tgcloud/lib/**`, `tgcloud/handlers/*.js`,
  `tgcloud/endpoints/*.js` и папка статики из `tgcloud.jsonc`. Всё остальное — локальное.
- `handlers/` и `endpoints/` — ровно один уровень вложенности, имена как у типов обновлений
  Telegram или camelCase для endpoints.
- Handler получает payload обновления первым аргументом, полный `Update` — в `ctx.update`.
  Endpoint получает `(input, ctx)`, где `input` — JSON-объект, `ctx.initData.user` — вызывающий.

### База

- SQLite: все вызовы асинхронные, `await` обязателен.
- Внешних ключей нет: `.references()` и `foreignKey()` падают при объявлении. Целостность —
  в коде; удаление детей до родителя; периодическая чистка сирот.
- Удаление таблиц/колонок — только через `.deprecated('причина')`. Удаление объявления
  ничего не дропает.
- Типы колонок: `integer(..., { mode: 'timestamp' })` даёт `Date` в JS и unix-секунды в БД;
  `json()` автоматически сериализует; `blob()` — `Uint8Array`.
- `db.$count(table, where?)`, `onConflictDoUpdate`, `RETURNING` — использовать вместо
  ручных `SELECT COUNT(*)` и «прочитать-потом-записать».

### Telegram Bot API

- `callback_data` — максимум **64 байта**. Схема callback-ов проектируется под этот лимит,
  а не «как получится».
- Текст сообщения — 4096 символов; длинные тексты режем `splitMessage`.
- Загрузка: 50 МБ на файл, **10 МБ на фото**; `getFile`/`getFileContent` — до **20 МБ**.
- `getFileContent(file_id)` возвращает `Uint8Array`; внешний `file_path` не принимается.
- `api.*` возвращает развёрнутый `result` и бросает `BotApiError` с `.code`, `.description`,
  `.parameters`; `429` содержит `retry_after`, `403` — блокировку.
- `web_app`-кнопки требуют HTTPS-адрес; платформа выдаёт его после `push`.

### Mini App

- Статика публикуется из папки, указанной в `tgcloud.jsonc` → `"static": { "source": "miniapp/dist", "spa": true }`.
- `push` не собирает фронтенд: сборка выполняется до `push`.
- Сайт живёт в корне домена; `base` у Vite — `/`. Нельзя ставить `X-Frame-Options` и
  ограничивающий `frame-ancestors`: Telegram открывает Mini App в iframe.
- Запросы к серверу — только `Telegram.WebApp.Serverless.call('<name>', input, cb)`.
  Свои `fetch` с initData не пишем.
- Валидный initData проверяет платформа; без него endpoint не запускается (`401`).
- Отказ endpoint — `throw new EndpointError(description, { code })` → `400`; любой другой
  exception → `500` с обезличенным текстом.

### Ограничения, которые нужно измерить

- Лимит размера JSON-тела endpoint неизвестен. От него зависит пайплайн фото; измеряется
  на этапе 1 и фиксируется в `lib/config.js` как `PHOTO_MAX_BYTES`.
- Лимит времени выполнения handler/endpoint неизвестен. Поэтому v1 ограничивает кампанию
  500 участниками, а рассылку — 100 получателями за один запуск; при таймауте пользователь
  видит понятную ошибку, а не зависание.
- Нет локального рантайма: `npx tgcloud run` выполняется на сервере и использует реальные
  БД и Bot API. Локально проверяется только чистая логика обычным Node.

## 4. Структура проекта

```
serverless/secret-santa-tgcloud/
  AGENTS.md                  # правила проекта, ссылка на ../AGENTS.md
  README.md                  # запуск, деплой, ассеты, ограничения
  package.json               # @tgcloud/cli, скрипты status/run/deploy/migrate
  tgcloud.jsonc              # static: { source: "miniapp/dist", spa: true }
  .gitignore                 # .tgcloud/, node_modules/, miniapp/dist/
  tgcloud/
    schema.js                # все таблицы и индексы
    lib/
      config.js              # ADMIN_IDS, лимиты, TZ, APP_URL, флаги
      texts.js               # тексты бота и уведомлений (RU)
      keyboards.js           # сборка inline-клавиатур
      utils.js               # esc, splitMessage, даты, валидаторы, коды
      telegram.js            # send/edit/answer/sendPhoto, fallback, setMyCommands
      store.js               # вся работа с БД, без бизнес-правил
      access.js              # роль в кампании, проверки прав, EndpointError-хелперы
      brand-image.js         # встроенный JPEG для приветствия (InputFile)
      domain/
        campaign.js          # статусы, переходы, код кампании, правила
        draw.js              # чистый алгоритм жеребьёвки (без SDK)
        membership.js        # участие, подтверждение, прогресс
        wishlist.js          # валидация и сортировка пунктов (без SDK)
        chat.js              # rate limit, видимость сообщений (без SDK)
        matching-errors.js   # типы ошибок жеребьёвки
      bot/
        router.js            # разбор команд/текста, вызовы сценариев
        wizard.js            # создание и редактирование кампании
        dashboard.js         # дашборд кампании и навигация
        members.js           # инвайты, список, пагинация
        draw-flow.js         # превью, пересборка, подтверждение
        broadcast.js         # пресеты, свой текст, отчёт
        moderation.js        # жалобы, скрытие, блокировка переписки
        superadmin.js        # кампании, жалобы, блокировки пользователей
        session.js           # FSM поверх store.js
    handlers/
      message.js           # 1 строка: export default из lib/bot/router.js
      callback_query.js    # 1 строка
    endpoints/
      bootstrap.js
      joinCampaign.js
      leaveCampaign.js
      getCampaign.js
      acceptRules.js
      confirmParticipation.js
      updateDeliveryInfo.js
      getMyWishlist.js
      saveWishlistItem.js
      deleteWishlistItem.js
      reorderWishlist.js
      uploadItemPhoto.js
      deleteItemPhoto.js
      setItemClaimed.js
      getAssignment.js
      getItemPhoto.js
      getThread.js
      sendChatMessage.js
      reportMessage.js
      markThreadRead.js
      markGiftStatus.js
      updateProfile.js
  miniapp/
    package.json
    vite.config.js
    index.html
    src/
      main.js
      App.vue
      router.js
      api/client.js         # Promise-обёртка над Serverless.call
      api/mock.js           # mock initData и ответы для dev, не попадает в prod-бандл
      api/errors.js
      telegram/webapp.js    # ready/expand/BackButton/theme/safe-area
      telegram/theme.js     # синхронизация токенов с Telegram
      composables/
        useApi.js
        useCampaign.js
        useReveal.js
      design/
        tokens.css
        base.css
      components/
        ui/                 # Button, Card, Field, Sheet, Skeleton, EmptyState, Toast
        campaign/           # Timeline, MemberList, BudgetBadge, RulesCard
        wishlist/           # ItemCard, ItemEditor, PhotoPicker
        chat/               # Thread, MessageBubble, Composer, ReportSheet
        reveal/             # GiftReveal, RecipientCard
      pages/
        Onboarding.vue
        Campaigns.vue
        Lobby.vue
        Wishlist.vue
        Assignment.vue
        Chat.vue
        GiftStatus.vue
        Profile.vue
        NotFound.vue
      assets/               # оптимизированные производные от docs/
    public/
      fonts/                # self-hosted woff2 (Cyrillic)
  docs/
    plan.md                 # этот файл
    tgcloud-sdk.md          # копия справочника SDK
    avatar.png              # исходник 1800x1800
    preview.jpg             # исходник 2560x1664
  tests/
    draw.test.js            # node --test
    codes.test.js
    campaign.test.js
    wishlist.test.js
    chat.test.js
    fixtures/
  scripts/
    optimize-assets.ps1     # локальная генерация производных из docs/ (не деплоится)
```

Правила структуры:

- Фронтенд — JavaScript, как в корневом Laravel-проекте; TypeScript не вводим.
- `lib/store.js` не знает про Telegram и не формирует тексты; `lib/telegram.js` не знает
  про БД; `lib/bot/*` связывает их и содержит сценарии.
- `lib/domain/draw.js` не импортирует `sdk` и БД — только чистые функции и `rng`.
- `tests/` в корне, не в `tgcloud/`: иначе тесты уедут в облако.
- Все пользовательские строки — в `lib/texts.js`, чтобы правки копирайта не трогали логику.

## 5. Модель данных

Все таблицы — named exports в `tgcloud/schema.js`. Даты — `integer(..., { mode: 'timestamp' })`.
Идентификаторы Telegram храним как `integer`; наружу из endpoints они не отдаются, кроме
своего `bootstrap`.

### users

| Поле | Тип | Примечание |
|---|---|---|
| `tgId` | integer PK | Telegram user id |
| `username` | text default '' | обновляется при каждом входе |
| `firstName`, `lastName` | text default '' | для отображения |
| `lang` | text default 'ru' | пока только ru |
| `banned` | boolean default false | блокировка супер-админом |
| `blocked` | boolean default false | бот заблокирован пользователем |
| `createdAt`, `updatedAt` | timestamp | |

Индексы: `blocked`, `banned`.

### campaigns

| Поле | Тип | Примечание |
|---|---|---|
| `id` | integer PK autoincrement | внутренний id |
| `code` | text unique | 6 символов, человекочитаемый |
| `title` | text | 3–80 символов |
| `description` | text default '' | до 1000 |
| `rules` | text | текст правил, до 3000 |
| `rulesVersion` | integer default 1 | растёт при правке правил |
| `budgetMin`, `budgetMax` | integer | целые рубли, min ≤ max |
| `currency` | text default 'RUB' | |
| `status` | text | см. раздел 6 |
| `deliveryMode` | text | `meetup` / `shipping` / `hybrid` |
| `revealSanta` | boolean default true | раскрывать Санту после завершения |
| `organizerId` | integer | Telegram id организатора |
| `registrationEndsAt` | timestamp | конец набора |
| `giftDate` | timestamp | дата вручения |
| `lockedAt`, `drawnAt`, `completedAt`, `cancelledAt` | timestamp nullable | |
| `version` | integer default 1 | токен для callback-ов |
| `createdAt`, `updatedAt` | timestamp | |

Индексы: unique(`code`), (`organizerId`, `status`), (`status`, `registrationEndsAt`).

### memberships

| Поле | Тип | Примечание |
|---|---|---|
| `id` | integer PK autoincrement | |
| `campaignId` | integer | |
| `userId` | integer | |
| `role` | text | `organizer` / `participant` |
| `status` | text | `joined` / `rules_accepted` / `confirmed` / `declined` / `removed` |
| `rulesAcceptedAt` | timestamp nullable | |
| `rulesVersionAccepted` | integer nullable | |
| `confirmedAt` | timestamp nullable | |
| `deliveryMethod` | text nullable | `meetup` / `shipping` |
| `createdAt`, `updatedAt` | timestamp | |

Unique(`campaignId`, `userId`); индексы (`campaignId`, `status`), (`userId`, `status`).
Организатор — тоже membership с ролью `organizer` и обычным участием в жеребьёвке.

### delivery_info

| Поле | Тип | Примечание |
|---|---|---|
| `id` | integer PK autoincrement | |
| `campaignId`, `userId` | integer | |
| `recipientName` | text | кому адресовано |
| `address` | text | |
| `phone` | text | |
| `comment` | text default '' | |
| `updatedAt` | timestamp | |

Unique(`campaignId`, `userId`). Читает только Санта этой пары; организатор и остальные — нет.
Строки удаляются при завершении/отмене кампании.

### exclusions

| Поле | Тип | Примечание |
|---|---|---|
| `id` | integer PK autoincrement | |
| `campaignId` | integer | |
| `userA`, `userB` | integer | всегда `userA < userB` |
| `createdAt` | timestamp | |

Unique(`campaignId`, `userA`, `userB`), индекс по `campaignId`. Ограничение симметричное:
хранится одна нормализованная пара, проверка идёт в обе стороны.

### wishlist_items

| Поле | Тип | Примечание |
|---|---|---|
| `id` | integer PK autoincrement | |
| `campaignId`, `userId` | integer | |
| `title` | text | 1–120 |
| `url` | text default '' | только `http(s)`, до 500 |
| `priceMin`, `priceMax` | integer nullable | рубли, 0…1 000 000 |
| `currency` | text default 'RUB' | |
| `comment` | text default '' | до 500 |
| `photoFileId` | text nullable | Telegram file_id |
| `photoVersion` | integer default 0 | растёт при замене фото |
| `sortOrder` | integer default 0 | шаг 1000 |
| `createdAt`, `updatedAt` | timestamp | |

Индекс (`campaignId`, `userId`, `sortOrder`). Unique-ограничения на количество пунктов нет,
но endpoint ограничивает 50 пунктами на участника.

### claims

| Поле | Тип | Примечание |
|---|---|---|
| `itemId` | integer PK | |
| `campaignId`, `santaId` | integer | |
| `assignmentId` | integer | |
| `claimedAt` | timestamp | |

Отдельная таблица нужна, чтобы отметка не утекала получателю вместе с его же пунктом:
получатель читает `wishlist_items` без join с `claims`. Санта видит только свои claims.

### assignments

| Поле | Тип | Примечание |
|---|---|---|
| `id` | integer PK autoincrement | |
| `campaignId` | integer | |
| `giverId`, `receiverId` | integer | |
| `status` | text | `draft` / `confirmed` |
| `giftStatus` | text | `pending` / `sent` / `received` |
| `sentAt`, `receivedAt`, `revealedAt` | timestamp nullable | |
| `version` | integer default 1 | токен для callback-ов |
| `createdAt`, `updatedAt` | timestamp | |

Unique(`campaignId`, `giverId`), unique(`campaignId`, `receiverId`), индекс
(`campaignId`, `status`). Draft-строки видит только организатор; участникам они не отдаются.

### chat_messages

| Поле | Тип | Примечание |
|---|---|---|
| `id` | integer PK autoincrement | |
| `campaignId`, `assignmentId` | integer | |
| `senderRole` | text | `santa` / `recipient` |
| `senderUserId` | integer | хранится, клиенту не отдаётся |
| `clientMsgId` | text | для дедупликации повторов |
| `text` | text | 1–1000 |
| `createdAt` | timestamp | |
| `readAt`, `reportedAt`, `hiddenAt` | timestamp nullable | |
| `hiddenBy` | integer nullable | организатор или супер-админ |

Индексы: (`assignmentId`, `createdAt`), (`campaignId`, `reportedAt`),
unique(`assignmentId`, `clientMsgId`), (`assignmentId`, `hiddenAt`).

### sessions

| Поле | Тип | Примечание |
|---|---|---|
| `userId` | integer PK | |
| `step` | text | шаг FSM |
| `data` | json | черновик |
| `updatedAt` | integer (unix sec) | TTL проверяется лениво |

### rate_limits

| Поле | Тип | Примечание |
|---|---|---|
| `key` | text PK | `chat1m:<assignmentId>:<userId>`, `chat1h:<assignmentId>:<userId>`, `join:<userId>`, `wish:<userId>` |
| `windowStart` | integer | unix sec, фиксированное окно |
| `count` | integer | |

### broadcasts

| Поле | Тип | Примечание |
|---|---|---|
| `id` | integer PK autoincrement | |
| `campaignId`, `authorId` | integer | |
| `kind` | text | `preset` / `custom` |
| `text` | text | |
| `recipients`, `sent`, `blocked`, `failed` | integer | |
| `createdAt` | timestamp | |

### audit_log

| Поле | Тип | Примечание |
|---|---|---|
| `id` | integer PK autoincrement | |
| `campaignId` | integer nullable | |
| `actorId` | integer | |
| `action` | text | `campaign.create`, `draw.confirm`, `report.hide`, `broadcast.send`, … |
| `payload` | json | без PII и без текста вишлиста |
| `createdAt` | timestamp | |

Индекс (`campaignId`, `createdAt`), (`action`, `createdAt`).

### Инварианты, которые проверяются в коде

1. У кампании ровно один организатор, и он член своей кампании.
2. `membership` уникален по паре (кампания, пользователь).
3. Подтверждённых участников при жеребьёвке не меньше 3 и не больше 500.
4. Каждый участник имеет ровно одного получателя и ровно одного дарителя после подтверждения.
5. Нет пары «сам себе» и нет пар из `exclusions`.
6. `wishlist_item` принадлежит участнику этой кампании.
7. `claim` ссылается на пункт получателя этого Санты.
8. `chat_message` принадлежит паре, отправитель — одна из её сторон.
9. Удаление кампании/пользователя удаляет детей в правильном порядке (FK нет).
10. `delivery_info` не переживает завершение/отмену кампании.

## 6. Статусы и переходы

### Кампания

```
recruiting → locked → drawn → gifting → completed
                      ↘ cancelled
```

| Переход | Кто | Условия |
|---|---|---|
| публикация (строки ещё нет → `recruiting`) | организатор | заполнены название, бюджет, даты, правила; даты валидны; генерируется код |
| `recruiting → locked` | организатор | явное подтверждение; после блокировки новые участники не вступают |
| `locked → drawn` | организатор | подтверждённые пары, ≥3 участников, все приняли актуальные правила, у всех ≥1 пункт вишлиста |
| `drawn → gifting` | организатор | после даты вручения или вручную, когда первая пара отметила «отправлено» |
| `gifting → completed` | организатор | явное завершение; чистится `delivery_info`, включается раскрытие |
| любой до `completed` → `cancelled` | организатор или супер-админ | подтверждение; данные сохраняются для аудита, PII чистится |

`draft` — не статус строки в БД, а черновик в сессии визарда: кампания появляется
в таблице только при публикации, сразу в `recruiting`. Даты не запускают переходы
автоматически: cron на платформе нет. Бот показывает организатору в `/my` и на дашборде
подсказку «набор закончился, пора закрыть», если `registrationEndsAt` в прошлом, но
статус всё ещё `recruiting`.

### Участник

```
joined → rules_accepted → confirmed
   ↘ declined
любой до locked → removed (организатором)
```

- `joined` — вступление по коду/ссылке.
- `rules_accepted` — принята текущая версия правил.
- `confirmed` — явное «Участвую» + ≥1 пункт вишлиста. Только `confirmed` попадают в жеребьёвку.
- Если организатор изменил правила, у кого `rulesVersionAccepted < rulesVersion`, статус
  откатывается в `joined` до повторного принятия; жеребьёвка блокируется, пока такие есть.
- Организатор не может выйти из своей кампании: только отменить её.
- Удаление участника и правка исключений — только до `locked`; после блокировки набор закрыт.

### Пара и подарок

```
assignment: draft → confirmed
gift:       pending → sent → received
```

- `draft` — превью организатора, участникам не видно.
- `confirmed` — после подтверждения жеребьёвки; уведомления отправлены.
- `pending → sent` ставит Санта. `sent → received` ставит получатель. Получатель может
  поставить `received` напрямую из `pending` с подтверждением «подарок пришёл», если Санта
  забыл отметить отправку.
- Оба действия идемпотентны: повтор не меняет дату и не рассылает уведомление второй раз.
- `revealedAt` ставится при завершении кампании, если `revealSanta = true`.

## 7. Жеребьёвка

### Алгоритм

1. Берём только `confirmed` участников кампании.
2. Строим двудольный граф: слева дарители, справа получатели, одно и то же множество людей.
3. Убираем рёбра «сам себе» и все `exclusions` (симметрично).
4. Перемешиваем списки смежности сидированным `rng`, чтобы результат не был предсказуемым,
   но тесты были воспроизводимы.
5. Ищем совершенное паросочетание алгоритмом Хопкрофта–Карпа. Если его нет — бросаем
   `MatchingError('NO_PERFECT_MATCHING', { blockedUsers })`.
6. Валидируем: перестановка, нет self, нет exclusions, каждый ровно один раз.
7. Возвращаем пары `{ giverId, receiverId }` в стабильном порядке по `giverId`.

Сложность O(E√V), E ≤ V². При 500 участниках это верхняя граница 250 000 рёбер — приемлемо
для isolate; при больших кампаниях v1 отказывает с понятным сообщением.

### Пересборка одной пары

Задача — поменять пару одному дарителю, не трогая остальные. Полный пересчёт остатка
для этого не подходит: он может изменить половину пар, а организатор просил поправить одну.

- Для пары `g → r` ищем другого дарителя `g2` с парой `g2 → r2`.
- Пробуем обмен получателями: `g → r2`, `g2 → r`.
- Проверяем: `g ≠ r2`, `g2 ≠ r`, нет исключений `g–r2` и `g2–r`.
- Если обмен валиден — меняются ровно две пары, остальные остаются как были.
- Кандидаты `g2` перебираются в случайном порядке; до 20 попыток, затем сообщаем, что
  обмен невозможен (например, все остальные варианты запрещены исключениями).
- В этом случае предлагаем «Пересобрать всё» — полный новый матчинг — или оставить пару.

«Пересобрать всё» удаляет все draft-строки и строит новый матчинг целиком; это быстрый
путь, когда организатор не против, что изменятся и другие пары.

### Чистый API (тестируется без SDK)

```js
buildMatching({ participants, exclusions, rng })
swapReceiver({ pairs, giverId, exclusions, rng }) // новые пары или null
validateMatching({ participants, exclusions, pairs })
makeRng(seed) // mulberry32
```

- `participants` — массив `{ userId }` или просто id.
- `exclusions` — массив `[userA, userB]`, нормализуется внутри.
- `rng` — функция `() => number` в `[0, 1)`; в тестах — `makeRng(42)`.
- Ошибки: `NO_PERFECT_MATCHING`, `TOO_FEW_PARTICIPANTS`, `TOO_MANY_PARTICIPANTS`,
  `INVALID_PAIR`, `SELF_PAIR`, `EXCLUDED_PAIR`, `DUPLICATE_GIVER`, `DUPLICATE_RECEIVER`.

### UX жеребьёвки в боте

- Превью: список «А → Б» с пагинацией по 10, кнопки «Заменить получателя» для пары,
  «Пересобрать всё», «Подтвердить».
- Своя собственная пара в превью не показывается: организатор видит только «твоя пара
  собрана», иначе он заранее узнает своего Санту. Остальные пары видны для проверки.
- У каждой пары свой callback с токеном версии draft-набора; после пересборки старые кнопки
  отвечают «пары изменились».
- Подтверждение — двойное: кнопка + текст «Пары будут зафиксированы, отменить нельзя».
- После подтверждения пары скрываются из дашборда (сюрприз для организатора тоже);
  посмотреть их может только супер-админ для разбора инцидента.
- Участникам уходит уведомление «Жеребьёвка прошла, открой приложение».

## 8. Бот: команды, сценарии, callback-и

Бот работает только в личных сообщениях: в группах и каналах он отвечает коротким
«Откройте меня в личном чате» и ничего не создаёт. При каждом входе обновляем
`users.username/firstName/lastName` через upsert, снимаем `blocked`, если пользователь
снова написал, и не трогаем БД, если ничего не изменилось.

### Команды

| Команда | Роль | Назначение |
|---|---|---|
| `/start [payload]` | все | приветствие; `join_<code>` — вступление, `c_<id>` — открыть кампанию |
| `/new` | все | визард создания кампании |
| `/my` | все | мои кампании со статусами и подсказками |
| `/help` | все | помощь и список команд по роли |
| `/cancel` | все | отменить текущий диалог/рассылку |
| `/id` | все | свой Telegram id (для настройки супер-админа) |
| `/privacy` | все | что храним, зачем, сколько и как удалить |
| `/setcommands` | супер-админ | обновить меню команд через `setMyCommands` |
| `/admin` | супер-админ | кампании, жалобы, блокировки |
| `/stats` | супер-админ | сводка по боту |

Организатор не получает отдельные команды: его действия — кнопки на дашборде кампании.

Deep link `?start=join_<code>` бот обрабатывает сам: проверяет код, создаёт membership
через общий `membership.join` (тот же код используется endpoint-ом `joinCampaign`),
показывает карточку кампании и кнопку «Открыть приложение». Повторный переход идемпотентен:
пользователь остаётся в кампании и видит её текущий статус.

Код кампании: 6 символов из алфавита `ABCDEFGHJKMNPQRSTUVWXYZ23456789` (без `I`, `L`,
`O`, `0`, `1`), ввод нормализуется в верхний регистр и без пробелов. Коллизия при
генерации — до 5 повторов, затем ошибка с просьбой создать кампанию ещё раз.

### Визард `/new`

Шаги (FSM, `sessions.data` хранит черновик):

1. Название (3–80).
2. Описание (0–1000, `/skip`).
3. Бюджет: «от» и «до» (целые рубли, `от ≤ до`, максимум 1 000 000).
4. Дата конца набора (`ДД.ММ.ГГГГ`, не раньше сегодня).
5. Дата вручения (не раньше конца набора).
6. Режим доставки: встреча / доставка / гибрид (кнопки).
7. Правила: шаблон по умолчанию или свой текст (0–3000, `/skip` = шаблон).
8. Исключения: «не дарить друг другу» — список участников появится после вступления,
   поэтому на этом шаге только подсказка; сами исключения добавляются в дашборде.
9. Превью и «Опубликовать» → `recruiting`, генерируется код.

На каждом шаге есть «Назад», «Отмена», валидация с человеческим текстом. Сессия живёт
3600 с; `/start` и `/cancel` её очищают.

### Дашборд кампании

Одно сообщение, которое редактируется при навигации:

- код и инвайт-ссылка (`t.me/<bot>?start=join_<code>`), кнопка «Поделиться»;
- статус, даты, бюджет, режим доставки;
- участники: подтверждённые / всего, пагинация по 10, прогресс вишлистов (счётчики, без
  содержимого);
- исключения: добавить/удалить пару из подтверждённых участников;
- жеребьёвка: превью / пересборка / подтверждение;
- рассылка: пресеты и свой текст;
- статистика: участие, вишлисты, адреса, отправлено/получено, жалобы;
- правила: изменить (с предупреждением о повторном принятии);
- завершение и отмена кампании;
- «Открыть приложение» (`web_app` + fallback `url`).

### Callback-данные

Жёсткий бюджет: **≤ 64 байта**. Формат:

```
ss:<domain><action>:<campaignId36>[:<arg>][:<token>]
```

- `ss` — префикс проекта.
- `domain`: `c` кампания, `w` визард, `m` участники, `x` исключения, `d` жеребьёвка,
  `b` рассылка, `r` жалобы, `a` супер-админ.
- `action`: 1–2 символа (`o` open, `p` page, `v` preview, `r` rematch, `k` confirm,
  `n` next, `s` send, `h` hide, `z` cancel).
- `campaignId36` — `id.toString(36)`, до 4 символов при разумных объёмах.
- `token` — `version.toString(36)`, 1–4 символа, только для опасных действий.

Примеры: `ss:co:12`, `ss:mp:12:2`, `ss:dv:12:3`, `ss:dk:12:3`, `ss:bh:12:7:1`.

Правила:

- Сначала `answerCallbackQuery`, потом работа: Telegram ждёт ответ быстро, а жеребьёвка
  и рассылка могут занять время.
- Неизвестный/устаревший callback → `answerCallbackQuery` с текстом и без изменения сообщения.
- Опасные действия (подтверждение жеребьёвки, удаление участника, отмена кампании,
  массовая рассылка) всегда имеют отдельный шаг подтверждения и токен версии.
- Данные callback-а не являются авторизацией: роль всегда перепроверяется по БД.

### Работа с текстом

- Любой пользовательский текст в HTML-сообщениях проходит через `esc()`.
- Длинные тексты режутся `splitMessage`; клавиатура — только на последнем чанке.
- `disable_web_page_preview: true` по умолчанию; ссылки в вишлисте открываются в приложении.
- Ошибки Bot API логируются с `method`, `code`, `description`; секреты и тексты вишлиста
  в логи не попадают.

### Рассылка

- Получатели — только `confirmed` участники кампании, кроме отправителя.
- Пресеты: «Заполните вишлист», «Скоро жеребьёвка», «Подарок отправлен?», «Кампания
  завершается».
- Свой текст 1–3800 символов; превью + подтверждение.
- Лимит 100 получателей за запуск; больше — отказ с просьбой разбить кампанию.
- `403` и `deactivated` → пометить `blocked`, не считать ошибкой; `429` → подождать
  `retry_after` и повторить один раз; прочие — в счётчик ошибок.
- Итоговый отчёт: отправлено / заблокировали / ошибки / всего; пишется в `broadcasts`
  и `audit_log`.

### Модерация

- Жалоба приходит из Mini App на сообщение чата.
- Уведомление организатору и супер-админу содержит текст жалобы и имя отправителя
  (для разбора), но не раскрывает всю переписку.
- Действия: скрыть сообщение, предупредить, заблокировать переписку в этой паре,
  заблокировать пользователя (супер-админ).
- Скрытое сообщение не видно обеим сторонам; в аудит пишется решение, без текста.

### Супер-админ

- Список кампаний со статусами и числом участников.
- Жалобы across кампаний, сортировка по свежести.
- Блокировка пользователя (`banned`): бот отвечает «Доступ ограничен», endpoint-ы — `BANNED`.
- Отмена любой кампании с подтверждением и записью в аудит.

## 9. Endpoints Mini App

Общие правила:

- Авторизация только через `ctx.initData.user`; присланные клиентом id игнорируются.
- `input` валидируется по типам и длинам; неизвестные поля игнорируются.
- Все отказы — `EndpointError` с машинным кодом в `parameters.code`.
- Ответы не содержат чужих Telegram id, кодов инвайтов других кампаний, пар и адресов,
  кроме получателя у его Санты.
- Для участника без доступа к кампании — `NOT_MEMBER`, а не `404` с деталями.
- Никаких секретов в ответах; `APP_URL` и лимиты могут отдаваться в `bootstrap`.

| Endpoint | Кто | Вход | Возвращает | Основные ошибки |
|---|---|---|---|---|
| `bootstrap` | все | `{}` | пользователь, `lang`, флаг супер-админа, `startParam`, мои кампании (кратко), лимиты, версия правил бота | `NO_USER`, `BANNED` |
| `joinCampaign` | все | `{ code }` | `{ campaignId, status }` | `BAD_CODE`, `NOT_FOUND`, `CAMPAIGN_CLOSED`, `RATE_LIMITED` |
| `leaveCampaign` | участник | `{ campaignId }` | `{ ok: true }` | `NOT_MEMBER`, `CAMPAIGN_LOCKED`, `ORGANIZER_CANNOT_LEAVE` |
| `getCampaign` | участник | `{ campaignId }` | статус, таймлайн, правила, бюджет, участники (имена), мой статус, прогресс, счётчики | `NOT_MEMBER`, `BANNED` |
| `acceptRules` | участник | `{ campaignId, rulesVersion }` | `{ status: 'rules_accepted' }` | `RULES_CHANGED`, `CAMPAIGN_LOCKED` |
| `confirmParticipation` | участник | `{ campaignId }` | `{ status: 'confirmed' }` | `WISHLIST_EMPTY`, `RULES_NOT_ACCEPTED`, `CAMPAIGN_LOCKED` |
| `updateDeliveryInfo` | подтверждённый | `{ campaignId, recipientName, address, phone, comment, method }` | `{ ok: true }` | `VALIDATION`, `CAMPAIGN_CLOSED` |
| `getMyWishlist` | участник | `{ campaignId }` | пункты без claims, права на редактирование | `NOT_MEMBER` |
| `saveWishlistItem` | участник | `{ campaignId, itemId?, title, url?, priceMin?, priceMax?, comment? }` | пункт | `VALIDATION`, `LIMIT_REACHED`, `CAMPAIGN_CLOSED` |
| `deleteWishlistItem` | участник | `{ campaignId, itemId }` | `{ ok: true }` | `NOT_FOUND`, `CAMPAIGN_CLOSED` |
| `reorderWishlist` | участник | `{ campaignId, itemIds }` | `{ ok: true }` | `VALIDATION`, `NOT_FOUND` |
| `uploadItemPhoto` | владелец пункта | `{ campaignId, itemId, dataBase64, mime }` | `{ version }` | `PHOTO_TOO_BIG`, `UNSUPPORTED_TYPE`, `RATE_LIMITED`, `NOT_FOUND` |
| `deleteItemPhoto` | владелец пункта | `{ campaignId, itemId }` | `{ ok: true }` | `NOT_FOUND`, `CAMPAIGN_CLOSED` |
| `setItemClaimed` | Санта | `{ campaignId, itemId, claimed }` | `{ claimed }` | `NOT_SANTA`, `NOT_FOUND` |
| `getAssignment` | Санта после жеребьёвки | `{ campaignId }` | получатель (имя, username опционально), вишлист с claims, доставка, статус подарка, ссылка на чат | `NOT_DRAWN`, `NOT_SANTA`, `CAMPAIGN_CANCELLED` |
| `getGiftStatus` | участник пары | `{ campaignId }` | роль, статус подарка, имя партнёра (Санта — только после раскрытия), доступность чата | `NO_ASSIGNMENT`, `CAMPAIGN_CANCELLED` |
| `getItemPhoto` | владелец пункта или Санта | `{ campaignId, itemId }` | `{ dataUrl, version }` | `NOT_ALLOWED`, `NO_PHOTO`, `PHOTO_UNAVAILABLE` |
| `getThread` | участник пары | `{ campaignId, afterId? }` | сообщения без `senderUserId`, мой статус прочтения | `NO_ASSIGNMENT`, `CHAT_BLOCKED` |
| `sendChatMessage` | участник пары | `{ campaignId, text, clientMsgId }` | сообщение (повтор с тем же `clientMsgId` возвращает существующее) | `CHAT_BLOCKED`, `RATE_LIMITED`, `VALIDATION` |
| `reportMessage` | участник пары | `{ campaignId, messageId, reason }` | `{ ok: true }` | `NOT_FOUND` |
| `markThreadRead` | участник пары | `{ campaignId, upToId }` | `{ ok: true }` | `NO_ASSIGNMENT` |
| `markGiftStatus` | Санта / получатель | `{ campaignId, status }` | новый статус | `INVALID_TRANSITION`, `NOT_SANTA`, `NOT_RECIPIENT` |
| `updateProfile` | все | `{ lang?, firstName? }` | профиль | `VALIDATION` |

Ответы всегда минимальны: `{ campaignId }` — внутренний id, который и так знает участник;
имена участников отдаются как отображаемые строки. Никаких `tgId` других людей.

`updateDeliveryInfo`: адрес и телефон обязательны только при `method = shipping` или
`hybrid`; при `meetup` достаточно имени и комментария. `getAssignment` отдаёт блок доставки
только если он заполнен, и только этому Санте.

### Коды ошибок (единый словарь)

`NO_USER`, `BANNED`, `NOT_MEMBER`, `NOT_ORGANIZER`, `NOT_SANTA`, `NOT_RECIPIENT`,
`NO_ASSIGNMENT`, `NOT_DRAWN`, `CAMPAIGN_CLOSED`, `CAMPAIGN_LOCKED`, `CAMPAIGN_CANCELLED`,
`RULES_CHANGED`, `RULES_NOT_ACCEPTED`, `WISHLIST_EMPTY`, `LIMIT_REACHED`, `VALIDATION`,
`NOT_FOUND`, `BAD_CODE`, `RATE_LIMITED`, `CHAT_BLOCKED`, `PHOTO_UNAVAILABLE`, `NO_PHOTO`,
`INVALID_TRANSITION`, `INTERNAL`, `PHOTO_TOO_BIG`, `UNSUPPORTED_TYPE`, `NOT_ALLOWED`,
`ORGANIZER_CANNOT_LEAVE`.

Клиент показывает текст из `err.message`, но решения принимает по `err.parameters.code`;
неизвестный код → общий экран ошибки с кнопкой «Повторить».

### Идемпотентность

- `confirmParticipation`, `acceptRules`, `markGiftStatus` — повтор возвращает текущее
  состояние без новых уведомлений.
- `setItemClaimed` — upsert/delete, повтор безопасен.
- `sendChatMessage` — `clientMsgId` unique(`assignmentId`, `clientMsgId`); повтор с тем же
  id возвращает существующее сообщение, но не создаёт второе.
- `reportMessage` — повторная жалоба на то же сообщение возвращает `{ ok: true }` и не
  создаёт вторую запись.
- `joinCampaign` — повтор тем же пользователем возвращает текущий статус, не ошибку.

## 10. Веб-апп

### Экраны

| Маршрут | Экран | Содержимое | Главное действие |
|---|---|---|---|
| `/` | Онбординг | иллюстрация из `preview.jpg`, короткое объяснение, ввод кода | «Войти по коду» |
| `/campaigns` | Мои кампании | карточки: название, статус, даты, бюджет, роль | открыть кампанию / `/new` в боте |
| `/c/:id` | Лобби | таймлайн, участники, правила, бюджет, мой статус, прогресс | принять правила → участвовать |
| `/c/:id/wishlist` | Вишлист | карточки, drag-reorder, фото, цены, приватность | добавить/сохранить |
| `/c/:id/assignment` | Получатель | reveal-карточка, профиль, вишлист, доставка | «Понятно» |
| `/c/:id/chat` | Чат | тред, composer, жалоба | отправить |
| `/c/:id/gift` | Подарок | статус, чек-лист, финал | «Отправлено» / «Получено» |
| `/profile` | Профиль | имя, язык, уведомления, выход | сохранить |
| `*` | 404 | спокойный экран | на главную |

### Навигация и Telegram

- `Telegram.WebApp.ready()`, `expand()`, `disableVerticalSwipes()` **не** вызываем на
  прокручиваемых экранах; вертикальный свайп нужен для контента.
- `BackButton`: показываем на вложенных экранах, скрываем на корне; аппаратная/жестовая
  «назад» не должна закрывать приложение с середины сценария.
- `ClosingConfirmation` включаем только при несохранённых изменениях в редакторе вишлиста.
- Тема: читаем `colorScheme` и `themeParams`, но брендовые поверхности задаём своими
  токенами; `setHeaderColor`, `setBackgroundColor`, `setBottomBarColor` — только если метод есть.
- Safe area: `env(safe-area-inset-*)`, `viewportStableHeight`; фиксированные панели не
  перекрывают контент.
- Haptics: `impactOccurred('light')` на reveal и «куплено», если доступно; не обязательны.
- Внешние ссылки — `openLink` с подтверждением для незнакомых доменов.

### Данные и состояния

- `api/client.js`: Promise-обёртка над `Serverless.call`, таймаут, один retry для
  идемпотентных GET-подобных вызовов, нормализация ошибок.
- `bootstrap` вызывается один раз при старте; кэш кампаний в памяти + `localStorage` для
  последней открытой кампании.
- Каждый экран имеет состояния: skeleton, пусто, ошибка сети, нет доступа, кампания
  завершена/отменена.
- Оптимистичные обновления только для обратимых действий (claim, read); подтверждения
  и жеребьёвка — после ответа сервера.
- Никаких прямых `fetch` к Bot API; фото приходят только через `getItemPhoto`.

### Локальная разработка

- `npm run dev` в `miniapp/`; при отсутствии Telegram SDK включается `api/mock.js`
  (только dev-сборка), который отдаёт фикстуры и имитирует задержки.
- Mock initData: фиксированный пользователь + переключатель роли (организатор/Санта/получатель).
- Реальные endpoint-ы проверяются `npx tgcloud run endpoints/<name> '{}' --ctx '{...}'`
  после деплоя пользователем.
- Сборка: `npm run build` → `miniapp/dist`; проверить, что mock не попал в прод-бандл.

## 11. Дизайн-система по ассетам из `docs/`

### Что есть

| Файл | Размер | Что это | Куда используем |
|---|---|---|---|
| `docs/avatar.png` | 1800×1800, 1.2 МБ | ёлка с гирляндой и подарком на кремовом фоне | аватар бота, иконка Mini App, компактные пустые состояния, favicon |
| `docs/preview.jpg` | 2560×1664, 2.2 МБ | зимняя сцена: эльф, сундук с подарками, ёлка, месяц, снег | hero онбординга, обложка README, карточка приглашения |

Исходники большие: в бандл они не попадают как есть. Производные генерируются
`scripts/optimize-assets.ps1` (Windows, System.Drawing — без лишних зависимостей) и коммитятся:

- `hero-1920.webp`, `hero-1280.webp`, `hero-640.webp` + `hero-1280.jpg` fallback;
- `tree-512.webp`, `tree-192.png` для аватара и пустых состояний;
- favicon 32/180 PNG из аватара.

### Характер

Тёплый рисованный Новый год: мягкие округлые формы, никакого чистого чёрного,
контуры — приглушённый сине-зелёный, а не графитный. Свет тёплый, золотистый, рассеянный.
Снег — не белая заливка, а тёплый кремовый с холодными лавандовыми тенями.
Иллюстрации наивные и ручные; интерфейс — спокойный, чтобы не спорить с ними.

Часть значений снята с ассетов: кремовый фон `#F6EEE2`, хвоя `#536F6B` и `#758A7D`,
контур `#42535A`, сугробы `#EAE7EE`, бордо сундука `#72333E`, месяц `#FFF5E9`.
Остальные токены выведены из них и проверены на контраст.

Чего избегаем: градиентных «стеклянных» карточек, неона, 3D-рендеров, эмодзи вместо
иконок, чистого белого фона, кислотного красного, стоковых «праздничных» конфетти
поверх каждого экрана.

### Токены

Светлая тема (основная):

| Токен | Значение | Использование |
|---|---|---|
| `--paper` | `#F6EEE2` | фон приложения |
| `--paper-raised` | `#FCF7EE` | карточки, листы |
| `--paper-sunken` | `#EFE4D4` | поля, скелетоны |
| `--ink` | `#2E3B36` | основной текст |
| `--ink-soft` | `#62706A` | вторичный текст |
| `--outline` | `#42535A` | контуры иконок и иллюстраций |
| `--pine-900` | `#2E4A44` | заголовки, тёмные кнопки |
| `--pine-700` | `#536F6B` | иконки, акценты |
| `--pine-500` | `#758A7D` | декоративные элементы |
| `--pine-200` | `#A8C2B4` | заливки бейджей |
| `--berry-700` | `#72333E` | pressed-состояние основной кнопки |
| `--berry-600` | `#9E2732` | основная кнопка, важные акценты |
| `--berry-400` | `#C9525A` | ошибки, мягкие акценты |
| `--gold-500` | `#E7B96F` | гирлянда, звезда, прогресс |
| `--gold-300` | `#F2D8A8` | подсветка, focus-ring |
| `--moon` | `#FFF5E9` | самые светлые блики |
| `--rose-300` | `#DBC3C3` | небо в иллюстрациях, тёплые подложки |
| `--snow` | `#F7F5F0` | «снежные» поверхности |
| `--frost` | `#EAE7EE` | холодные тени |
| `--line` | `rgba(66, 83, 90, 0.14)` | границы |
| `--shadow` | `rgba(66, 83, 90, 0.12)` | тени |

Тёмная тема (ночь):

| Токен | Значение |
|---|---|
| `--paper` | `#0F1A17` |
| `--paper-raised` | `#16241F` |
| `--paper-sunken` | `#0B1412` |
| `--ink` | `#F5EADB` |
| `--ink-soft` | `#B9C6BD` |
| `--outline` | `#A9BBB3` |
| `--pine-900` | `#8FB3A4` |
| `--gold-500` | `#F0CD90` |
| `--berry-600` | `#D06A70` |
| `--line` | `rgba(245, 234, 219, 0.12)` |

Контраст: основной текст и кнопки — не ниже 4.5:1; золото и розовый — только декор и
крупные элементы, не текст на кремовом. Проверяется на этапе 8.

### Типографика

- Display: **Comfortaa** 600/700 — заголовки, название кампании, крупные цифры. Округлая,
  поддерживает кириллицу, совпадает с рисованным характером.
- Text: **Nunito** 400/600/700 — body, формы, кнопки. Хорошая кириллица, тёплые формы.
- Оба шрифта self-hosted (woff2, Cyrillic + Latin) в `miniapp/public/fonts/`; без Google Fonts,
  чтобы не зависеть от внешней сети и не мигать FOUT. Оба распространяются по OFL —
  лицензии кладём рядом в `public/fonts/`.
- Шкала: 12 / 14 / 16 / 18 / 22 / 28 / 36 px, fluid через `clamp()` для заголовков.
- Цифры дат и бюджета — `font-variant-numeric: tabular-nums`.
- Не используем ALL CAPS для русских фраз длиннее 2 слов: буквы теряют форму.

### Формы, тени, сетка

- Радиусы: 12 (поля), 18 (кнопки/чипы), 24 (карточки), 999 (пилюли, аватар).
- Границы 1 px `--line`; тени мягкие, цветные (`--shadow`), без чистого чёрного.
- Сетка: 4 px шаг; контейнер 560 px на мобильном, 720 px на планшете, 960 px на десктопе.
- Карточки не «парят» над всем: одна тень на экран, остальное — границы и тон поверхности.
- Кнопки: primary (berry, белый текст), secondary (pine-контур на paper), ghost (текст pine).
  Высота ≥ 44 px, полная ширина на мобильном для главного действия.

### Иллюстрации и мотивы

- Hero: `preview.jpg` как атмосферный фон, но текст на отдельной кремовой подложке —
  не поверх мелких деталей. На мобильном кадрируем на эльфа и сундук.
- Аватар: используем в онбординге как «печать» бота и в пустых состояниях.
- Мотив гирлянды — тонкая линия с тёплыми точками; рисуем SVG, не картинкой, чтобы
  масштабировалось и могло «мигать».
- Иконки: собственный набор 20×20/24×24, штрих 2 px, скруглённые концы, цвет `--pine-700`;
  не смешиваем с emoji и не тянем тяжёлый icon-pack.
- Декор снега — 6–10 частиц на экран, CSS, низкая плотность; на слабых устройствах отключается.

### Движение

- Длительности: 180 мс (микро), 260 мс (переходы), 420 мс (reveal).
- Easing: `cubic-bezier(0.22, 1, 0.36, 1)`; никаких bounce на каждом элементе.
- Два выраженных момента: вход в приложение (мягкое проявление hero + снег) и раскрытие
  получателя (карточка складывается/разворачивается, тёплый свет из-под неё).
- Quiet micro-interactions: нажатие кнопки, «куплено» (галочка), скелетоны.
- `prefers-reduced-motion: reduce` отключает снег, параллакс и 3D; reveal становится
  простым fade + scale 0.98→1.
- Никакой анимации на каждом скролле; параллакс — только на hero и только на устройствах
  с указателем.

### Доступность

- Тап-таргеты ≥ 44×44 px, отступы между ними ≥ 8 px.
- `:focus-visible` — 2 px `--gold-500` + 2 px offset; не убираем outline без замены.
- Формы: label + подсказка + ошибка рядом с полем, ошибка не только цветом.
- 320 px reflow без горизонтального скролла; проверка 390/768/1280.
- Windows scaling 125%/150%, Segoe UI fallback, реальные скроллбары.
- Тексты кнопок не обрезаются: проверка длинных русских строк.
- Скринридер: заголовки по уровням, `aria-live` для тостов, `aria-busy` для скелетонов.

### Копирайт

- Коротко, тепло, без канцелярита и без «магии ИИ».
- Обращение на «ты» в приложении, на «вы» — в системных сообщениях организатору.
- Примеры: «Твой получатель готов», «Подарок уже в пути?», «Пока никто не добавил
  желания — самое время начать».
- Ошибки объясняют, что делать: «Код не подошёл. Проверь 6 символов и попробуй снова».
- Эмодзи — только в боте, по одному на сообщение, не в интерфейсе.

## 12. Фото вишлиста

### Спайк (этап 1) — обязателен до кода

Измерить и записать в план фактические значения:

1. Максимальный размер JSON-тела endpoint: отправить 64 КБ, 128 КБ, 256 КБ, 512 КБ,
   1 МБ, 2 МБ base64-строкой на тестовый endpoint; зафиксировать первый отказ.
2. Максимальный размер JSON-ответа: `getItemPhoto` с data URL 100/200/400/800 КБ;
   если ответ режется, уменьшить целевой размер фото.
3. `api.sendPhoto` с `InputFile` из endpoint: получить `file_id`, проверить `getFileContent`.
4. Загрузка фото ботом: `message.photo` → `file_id`, размеры, доступность после рестарта.
5. Deep links: `?start=join_<code>`, `web_app` URL с query, `startapp`; проверить
   `ctx.initData.start_param` в endpoint.

Решение по умолчанию, если спайк не опровергнет:

- Клиент сжимает фото до 1280 px по длинной стороне, JPEG q0.8, цель ≤ 320 КБ;
- base64 ≤ ~430 КБ, что укладывается в 1 МБ JSON-тела; фактический лимит из спайка
  записывается в `config.PHOTO_MAX_BYTES`, клиент получает его из `bootstrap`;
- сервер валидирует MIME по сигнатуре (JPEG/PNG), размер, декодирует, отправляет
  `api.sendPhoto` в личный чат загрузившего, сохраняет `file_id`, удаляет сообщение;
- при ошибке загрузки — предложение отправить фото боту (fallback-путь), бот привязывает
  его к последнему редактируемому пункту через FSM.

### Отображение

- `getItemPhoto` проверяет, что вызывающий — владелец пункта или Санта этого получателя,
  и отдаёт `{ dataUrl, version }`. Остальным — `NOT_ALLOWED`.
- Клиент кэширует data URL в IndexedDB по ключу `itemId:version`; при смене фото версия
  растёт, кэш инвалидируется.
- Серверный BLOB-кэш в v1 не делаем: он раздувает SQLite и не нужен при 320 КБ.
- Если `file_id` протух — endpoint отвечает `PHOTO_UNAVAILABLE`, клиент показывает
  плейсхолдер, Санта видит «фото недоступно, попросите получателя обновить».

### Приватность и лимиты

- Одно фото на пункт; 50 пунктов на участника.
- Фото видит владелец пункта (свой вишлист) и Санта; организатор — никогда, кроме случая
  жалобы (и тогда только через модерацию, а не через вишлист).
- При удалении пункта фото не хранится отдельно: `file_id` удаляется вместе со строкой;
  сам файл остаётся в Telegram, но недоступен без id. Это фиксируем в политике.

## 13. Уведомления

Автоматических задач по расписанию нет. Уведомления только событийные, из handler-ов и
endpoint-ов.

| Событие | Кому | Текст (смысл) |
|---|---|---|
| Вступление в кампанию | организатор | «<Имя> присоединился к <Кампания>» + кнопка дашборда |
| Принятие правил / подтверждение | организатор | прогресс «подтверждено X из Y» (не чаще раза в час на кампанию) |
| Жеребьёвка подтверждена | все участники | «Пары готовы, открой приложение» + `web_app` |
| Новое сообщение в чате | другая сторона | «У тебя новое сообщение в Тайном Санте» (без текста и без имени) |
| Подарок отправлен | получатель | «Твой Санта отправил подарок» |
| Подарок получен | Санта | «Подарок доставлен» |
| Кампания завершена | все | «Кампания завершена»; если reveal — имена Сант |
| Кампания отменена | все | «Кампания отменена организатором» |
| Жалоба | организатор + супер-админ | текст жалобы и контекст только для разбора |
| Ручная рассылка | выбранные участники | пресет или свой текст |

Правила:

- Уведомления не раскрывают пару, вишлист и адрес.
- Если пользователь заблокировал бота, попытка помечает `blocked` и больше не повторяется.
- Дебаунс чата: не больше одного уведомления на пару в 5 минут; остальные сообщения
  подтягиваются при открытии.
- Все тексты — через `esc()`; длинные — `splitMessage`.
- Кнопки уведомлений ведут в Mini App через `web_app` с fallback на `url`.

## 14. Безопасность и приватность

### Матрица доступа

| Данные / действие | Участник | Санта | Организатор | Супер-админ |
|---|---|---|---|---|
| Свой профиль | чтение/запись | то же | то же | чтение |
| Свой вишлист | CRUD | — | — | — |
| Вишлист получателя | — | чтение | — | только по жалобе |
| Отметки «куплено» | — | CRUD | — | — |
| Список участников | имена/статусы | то же | имена/статусы/прогресс | всё |
| Пары | своя пара | своя пара | превью до подтверждения | всё |
| Адрес доставки | свой (ввод) | адрес получателя | — | по инциденту |
| Чат | своя пара | своя пара | только жалобы | только жалобы |
| Настройки кампании | — | — | CRUD до `drawn` | чтение/отмена |
| Жеребьёвка | — | — | превью/пересборка/подтверждение | чтение |
| Рассылка | — | — | CRUD | — |
| Бан пользователя | — | — | — | CRUD |

### Угрозы и меры

- **Подмена id.** Клиент присылает только `campaignId`/`itemId`; принадлежность всегда
  проверяется по `ctx.initData.user`. Незнакомый id → `NOT_FOUND`/`NOT_MEMBER`.
- **Перечисление кампаний.** Коды 6 символов из алфавита без похожих букв; rate limit
  на `joinCampaign` 5 попыток в минуту; ошибка не говорит, существует ли код, пока
  пользователь не ввёл валидный.
- **XSS.** В боте — `esc()` для любого пользовательского текста; во фронтенде — только
  `textContent`/интерполяция Vue, без `v-html`. Ссылки вишлиста — только `http(s)`,
  открываются через `openLink` с подтверждением.
- **SQL-инъекции.** Только query builder/`sql` с параметрами; никакой конкатенации.
- **Утечка анонимности.** `senderUserId` не сериализуется; имена Сант не отдаются до
  завершения; логи не содержат пар; ошибки не содержат чужих данных.
- **Абьюз чата.** Лимит 10 сообщений/мин и 60/час на пару, 1000 символов, жалоба,
  скрытие, блокировка переписки. Повторные жалобы эскалируются супер-админу.
- **PII адресов.** Отдельная таблица, доступ только Санте; удаление при завершении/отмене;
  в аудит не пишется; в логи не попадает.
- **Подделка initData.** Платформа проверяет; мы не принимаем id из тела.
- **Повтор опасных действий.** Токены версий в callback-ах, идемпотентные переходы,
  подтверждения для разрушительных операций.
- **Бан.** `banned` проверяется в `bootstrap`, `joinCampaign` и каждом endpoint-е через
  `access.requireUser`.
- **Секреты.** Токен бота живёт в `.tgcloud/` (gitignored) и не попадает в код/логи.
- **Политика.** `/privacy` в боте: какие данные храним, зачем, сколько, как удалить;
  согласие фиксируется фактом вступления.

### Хранение и удаление

- `delivery_info` удаляется при `completed`/`cancelled`.
- `claims` удаляются при завершении.
- `chat_messages` остаются для аудита жалоб, но после завершения доступны только
  супер-админу; при удалении кампании супер-админом — удаляются.
- `audit_log` хранит действия без PII и текстов.
- Пользователь может попросить удаление через организатора/супер-админа; v1 — ручная
  операция с записью в аудит, без отдельного endpoint-а.

## 15. Этапы разработки

Оценки грубые, в днях одного разработчика; включают проверку, но не ревью пользователем.

### Этап 0. Каркас проекта и документы (0.5 дня)

- [ ] Создать структуру папок из раздела 4.
- [ ] `package.json` с `@tgcloud/cli`, скрипты `status`/`run`/`deploy`/`migrate`/`test`.
- [ ] `tgcloud.jsonc` с `static: { source: "miniapp/dist", spa: true }`.
- [ ] `.gitignore`: `.tgcloud/`, `node_modules/`, `miniapp/dist/`, `miniapp/node_modules/`.
- [ ] `AGENTS.md` проекта: что это, структура, правила платформы, запрет `push`/`migrate`,
      тесты, ссылка на `../AGENTS.md`.
- [ ] Скопировать `docs/tgcloud-sdk.md` из `ny2027-tgcloud/docs/` без изменений.
- [ ] Добавить проект в таблицу `serverless/AGENTS.md`.
- [ ] `README.md` с командами, ограничениями и ссылкой на план.

Проверка: `Get-ChildItem` показывает структуру; `node --check` по пустым модулям не нужен;
`npx tgcloud status` либо показывает локальные изменения, либо просит `login`, если проект
ещё не связан с ботом — оба варианта ожидаемы.

### Этап 1. Спайк платформы (1 день)

- [ ] Замерить лимит JSON-тела endpoint (64 КБ → 2 МБ).
- [ ] Проверить `InputFile` + `sendPhoto` + `getFileContent` + удаление сообщения.
- [ ] Проверить загрузку фото пользователем боту и сохранение `file_id`.
- [ ] Проверить deep links и `start_param` для кнопки `web_app`.
- [ ] Записать результаты в раздел 12 и `lib/config.js` (`PHOTO_MAX_BYTES`, `APP_URL`).

Спайк требует деплоя: тестовые модули выкладывает пользователь (`push`), агент готовит
их и точные команды. После спайка тестовые endpoint-ы и handler-ы удаляются или
задокументированы, `status` снова чистый.

Проверка: таблица измерений с фактическими числами; решение по фото зафиксировано;
`npx tgcloud status` чистый после тестового модуля (тестовый модуль удалён или
задокументирован).

### Этап 2. Схема и чистое ядро (2 дня)

- [ ] `schema.js` со всеми таблицами, уникальными индексами и составными индексами.
- [ ] `lib/utils.js`: `esc`, `splitMessage`, `normalizeSpaces`, `validTitle`,
      `validUrl`, `validPrice`, `parseDateRu`, `formatDateRu`, `formatMoney`.
- [ ] `lib/domain/campaign.js`: генерация кода, статусы, `canTransition`, `assertTransition`.
- [ ] `lib/domain/membership.js`: прогресс, условия подтверждения, версии правил.
- [ ] `lib/domain/draw.js` + `lib/domain/matching-errors.js`.
- [ ] `lib/domain/wishlist.js`: валидация пункта, перестановка sortOrder.
- [ ] `lib/domain/chat.js`: `allowMessage`, `visibleMessages`, `normalizeChatText`.
- [ ] `tests/*.test.js` на всё чистое.

Проверка: `node --test` зелёный; тесты жеребьёвки покрывают 3/10/50 участников, пустые
исключения, невозможное паросочетание, обмен получателями (swap), self/excluded пары, сид.
Локально: `node --test tests/`.

### Этап 3. Админка бота (4 дня)

- [ ] `lib/config.js`, `lib/texts.js`, `lib/keyboards.js`.
- [ ] `lib/telegram.js`: send/edit/answer, fallback, split, `sendPhoto`, `setMyCommands`.
- [ ] `lib/store.js`: пользователи, сессии, кампании, участники, исключения, пары,
      рассылки, аудит; все запросы с `await`, upsert-ы.
- [ ] `lib/bot/session.js`, `router.js`, `wizard.js`.
- [ ] `lib/bot/dashboard.js`, `members.js`, `draw-flow.js`.
- [ ] `lib/bot/broadcast.js`, `moderation.js`, `superadmin.js`.
- [ ] Handlers — тонкие делегаты.

Проверка: `node --check` по всем файлам; `npx tgcloud run handlers/message` после
деплоя пользователем: `/start`, `/new`, публикация, инвайт, список, жеребьёвка,
подтверждение, рассылка, отмена. Проверить, что callback-и влезают в 64 байта
(тест-функция `callbackLength`).

### Этап 4. Участнические endpoints (3 дня)

- [ ] `lib/access.js`: `requireUser`, `requireMember`, `requireOrganizer`, `requireSanta`,
      `requireRecipient`, `requirePair`.
- [ ] Все endpoint-ы из таблицы раздела 9.
- [ ] Единый словарь ошибок и валидация входа.
- [ ] Rate limits: чат, join, вишлист.
- [ ] Идемпотентность и `clientMsgId`.
- [ ] `getItemPhoto` и пайплайн `file_id` (если спайк выбрал этот путь).
- [ ] `uploadItemPhoto` / `deleteItemPhoto`: валидация сигнатуры, размера и прав владельца.
- [ ] `senderRole` вычисляется на сервере по assignment; `clientMsgId` обязателен.

Проверка: `node --check`; `npx tgcloud run endpoints/<name> '{}' --ctx '{ initData: { user: { id: 1 } } }'`
для каждого endpoint-а с тестовыми данными (сначала ботом создаётся кампания и участники,
чтобы `id: 1` был реальным членом); матрица прав из раздела 14 вручную; негативные кейсы
возвращают ожидаемые коды, а не `500`.

### Этап 5. Каркас Mini App и дизайн-токены (1.5 дня)

- [ ] `miniapp/package.json`: `vue ^3.5`, `vue-router ^4`, `vite ^8.1`,
      `@vitejs/plugin-vue ^6`, `tailwindcss ^4.3`, `@tailwindcss/vite ^4.3`,
      `@vueuse/motion ^3` (если нужен для reveal).
- [ ] `vite.config.js`: `base: '/'`, outDir `dist`.
- [ ] `index.html`: viewport, safe-area, `telegram-web-app.js`, тема, шрифты.
- [ ] `tokens.css`, `base.css`, Tailwind-тема (цвета/радиусы/шрифты).
- [ ] UI-кит: Button, Card, Field, Sheet, Skeleton, EmptyState, Toast.
- [ ] `api/client.js`, `api/mock.js`, `telegram/webapp.js`, `telegram/theme.js`.
- [ ] Роутер и заглушки всех экранов.

Проверка: `npm run build` успешен; `npm run dev` открывается на 390 px; mock initData
работает; прод-бандл не содержит mock; размер JS ≤ 220 КБ gzip (бюджет).

### Этап 6. Экраны участника (5 дней)

- [ ] Онбординг и вход по коду/`start_param`.
- [ ] «Мои кампании».
- [ ] Лобби: таймлайн, правила, участники, прогресс, CTA.
- [ ] Вишлист: CRUD, drag-reorder, фото, приватность.
- [ ] Раскрытие получателя + reveal-анимация.
- [ ] Чат: тред, composer, жалоба, read-статусы; подсказка «не раскрывай себя» над полем.
- [ ] Подарок: статусы и финальный экран.
- [ ] Профиль.
- [ ] Все состояния: skeleton/пусто/ошибка/нет доступа/завершена.

Проверка: сквозной сценарий «вошёл → правила → вишлист → жеребьёвка → пара → чат →
подарок»; Playwright на 320/390/768/1280; контраст и тап-таргеты; reduced motion.

### Этап 7. Фото и полировка (2 дня)

- [ ] Пайплайн загрузки фото по результату спайка.
- [ ] Клиентское сжатие, прогресс, ошибки, fallback через бота.
- [ ] Кэш фото в IndexedDB и инвалидация по `photoVersion`.
- [ ] Hero и производные ассетов, favicon, аватар бота.
- [ ] Снег/гирлянда/reveal, reduced motion, светлая/тёмная тема.
- [ ] Пустые состояния с иллюстрациями из аватара.

Проверка: фото 1280 px загружается и открывается у Санты; лимит не превышен; при
отключённой сети — понятная ошибка; визуальная проверка на реальном телефоне.

### Этап 8. Безопасность и края (1.5 дня)

- [ ] Матрица прав: негативные тесты по каждому endpoint-у.
- [ ] Проверка утечек анонимности (grep по ответам: `senderUserId`, `giverId`).
- [ ] Отмена кампании, удаление участника, пересборка пар, PII-чистка.
- [ ] Идемпотентность и повторные клики.
- [ ] Лимиты и баны.
- [ ] Аудит-лог без PII.

Проверка: чек-лист негативных сценариев; ручной разбор ответов endpoint-ов; `node --test`;
`npx tgcloud migrate --dry-run` без неожиданных изменений.

### Этап 9. Верификация и деплой (1 день)

- [ ] `npm run build` в `miniapp/`.
- [ ] `npx tgcloud status`, `npx tgcloud diff`, `npx tgcloud migrate --dry-run`.
- [ ] Пользователь выполняет `push`, затем `migrate`.
- [ ] Пользователь проверяет `npx tgcloud webhook` — `in sync`.
- [ ] Пользователь выполняет `/setcommands` и настраивает Mini App в BotFather.
- [ ] Смоук-тест на реальном боте с двумя-тремя тестовыми аккаунтами.
- [ ] Отчёт «ПРОВЕРЕНО / НАЙДЕНО / НЕ ПРОВЕРЕНО / ВЕРДИКТ».

Проверка: чистый `status`, `webhook in sync`, сквозной сценарий, отсутствие ошибок в
логах BotFather.

## 16. Проверки и тесты

### Локальные (агент может запускать)

```powershell
# синтаксис всех деплоимых модулей
Get-ChildItem tgcloud -Recurse -Filter *.js | ForEach-Object { node --check $_.FullName }

# чистая логика
npm test        # node --test tests/

# фронтенд
npm run build   # в miniapp/
npm run dev     # в miniapp/, mock initData
```

### Серверные (после деплоя пользователем)

```powershell
npx tgcloud status
npx tgcloud diff
npx tgcloud migrate --dry-run

# PowerShell: JSON5 с одинарными кавычками и прямой вызов CLI
node node_modules\@tgcloud\cli\bin\tgcloud.js run handlers/message "{chat:{id:ID,type:'private'},from:{id:ID,first_name:'Test'},text:'/start'}"
npx tgcloud run endpoints/bootstrap '{}' --ctx '{ initData: { user: { id: 1 } } }'
```

`run` использует реальные БД и Bot API: тестовые сообщения уходят в чат. Для опасных
подтверждений (жеребьёвка, отмена, рассылка) в тестах нажимать только «Отмена».

### Ручной E2E в Telegram

1. Создать кампанию через `/new`, опубликовать.
2. Вступить вторым и третьим аккаунтом по ссылке.
3. Заполнить вишлисты, подтвердить участие.
4. Закрыть набор, собрать пары, заменить получателя одной паре, подтвердить.
5. Проверить уведомления и что пары не видны до reveal.
6. Открыть Mini App, получить получателя, написать в чат с обеих сторон.
7. Отметить подарок отправленным и полученным.
8. Завершить кампанию, проверить раскрытие и очистку адресов.
9. Отменить другую кампанию и проверить, что доступ закрыт, а данные не утекают.

### Визуальная проверка

- 320×568, 390×844, 768×1024, 1280×800; Telegram Web и мобильный клиент.
- Windows scaling 125%/150%, High Contrast, Safari/iPhone, Android Chrome.
- Тёмная тема Telegram, светлая тема, `prefers-reduced-motion`.
- Длинные русские подписи, пустые состояния, ошибки сети, медленная сеть.
- Скриншоты ключевых экранов — в `docs/screens/` (локально, не деплоятся).

### Формат отчёта

Каждый этап заканчивается отчётом:

- **ПРОВЕРЕНО** — что запускалось и что вернуло (реальный вывод, не «должно работать»).
- **НАЙДЕНО** — проблемы с `file:line`.
- **НЕ ПРОВЕРЕНО** — что нельзя было проверить и почему.
- **ВЕРДИКТ** — сделано / с оговорками / не сделано.

## 17. Деплой

Пользователь делает сам, агент только готовит изменения и команды:

1. BotFather → включить Serverless → CLI Access → токен.
2. В каталоге проекта: `npm install`, `npx tgcloud login`; в `miniapp/` — свой `npm install`.
3. Если `push` просит snapshot — `npx tgcloud fetch`, затем `push`. `pull` не запускать:
   он перезаписывает локальные файлы облачным состоянием.
4. `npm run build` в `miniapp/` (push не собирает фронтенд).
5. `npx tgcloud push` — выкладывает модули и статику, печатает URL Mini App.
   Если в `lib/config.js` ещё плейсхолдер `APP_URL`, записать напечатанный URL,
   пересобрать фронтенд и повторить `push` (адрес после первого `push` стабилен).
6. `npx tgcloud migrate --dry-run`, затем `npx tgcloud migrate` — применяет схему.
7. `npx tgcloud status` — `Working directory clean`; `npx tgcloud webhook` — `in sync`.
8. В BotFather поставить аватар из `docs/avatar.png` (производное 640×640) и настроить
   Mini App / кнопку меню на напечатанный URL; при необходимости задать короткое имя
   приложения для ссылок `t.me/<bot>/<app>?startapp=...`.
9. В боте: `/setcommands` (супер-админ), затем смоук-тест.

Ограничения:

- `tgcloud init` на этой Windows-машине падает; проект создаётся вручную, `init` не нужен.
- `push`/`migrate`/`pull`/`webhook sync`/`login` — только пользователь.
- Git-операции (commit, push, merge) выполняет пользователь; агент только читает историю.
- Агент может: править файлы, `status`, `diff`, `migrate --dry-run`, `run` read-only
  модулей, `node --check`, `node --test`, `npm run build` во фронтенде.

## 18. Риски

| Риск | Вероятность | Влияние | Митигация |
|---|---|---|---|
| Лимит JSON-тела/ответа меньше ожидаемого | средняя | фото не грузится | Спайк первым; сжатие до 320 КБ; fallback «фото боту» |
| Нет совершенного паросочетания из-за исключений | средняя | жеребьёвка не идёт | Понятная ошибка со списком «кого не удалось устроить»; организатор правит исключения |
| Жеребьёвка 500 участников упирается в время isolate | низкая | таймаут | Лимит 500; HK O(E√V); при таймауте — понятная ошибка и повтор |
| Синхронная рассылка упирается в лимит времени | средняя | часть не получила | Лимит 100; отчёт; повтор для неудавшихся |
| `callback_data` > 64 байт | средняя | кнопка ломается | Компактная схема + тест длины на этапе 3 |
| Утечка анонимности через ответы/логи | средняя | потеря доверия | Матрица прав, grep-проверки, негативные тесты, ревью |
| Организатор случайно отменяет/завершает | средняя | потеря кампании | Двойное подтверждение, токены версий, аудит |
| PII адресов остаётся после кампании | низкая | приватность | Чистка в обработчике завершения/отмены; тест на отсутствие строк |
| Фото теряет `file_id` | низкая | пустая карточка | `PHOTO_UNAVAILABLE` + просьба обновить фото; fallback |
| Тяжёлый hero/шрифты на слабом телефоне | средняя | медленный первый экран | WebP + srcset, self-hosted subset, бюджеты, ленивая загрузка |
| Telegram-тема ломает контраст | низкая | нечитаемый текст | Свои токены, проверка light/dark, не полагаться на `themeParams` для текста |
| `tgcloud init`/CLI падает на Windows | известная | блокирует старт | Ручной каркас из шаблонов; WSL/Linux как обходной путь |
| Нет локального рантайма | известная | медленный цикл | Чистая логика в тестах; ранние спайки; `run` после деплоя |

## 19. Открытые вопросы

1. **Раскрытие Санты.** По умолчанию включено после завершения кампании. Подтвердить.
2. **Валюта.** По умолчанию RUB; нужны ли другие валюты в v1?
3. **Telegram ID супер-админа.** Решено: `115462629` (записан в `lib/config.js`).
4. **Имя и юзернейм бота.** Решено: `@pod_elochku_bot` (записан в `lib/config.js`).
5. **Модерация вишлиста.** Сейчас организатор не видит содержимое, кроме жалобы.
   Оставить так?
6. **Правила по умолчанию.** Нужен ли юридический текст (возврат, персональные данные)
   или достаточно дружелюбного шаблона?
7. **Замена фото после жеребьёвки.** Разрешаем, но Санта увидит новую версию; если фото
   уже «куплено», отметка сохраняется. Подтвердить поведение.
8. **Взаимные пары.** v1 разрешает A→B и B→A. Нужен ли флаг «не дарить друг другу»
   (кроме явных исключений)?
9. **Уведомления организатору о каждом вступлении.** Оставить включёнными или сделать
   настройку «тихий режим»?
10. **Публичная ссылка на кампанию.** Нужен ли режим «кто угодно по ссылке» без
    подтверждения организатора? Сейчас вступление свободное по коду, но организатор
    видит список и может удалить.

## 20. Что нужно от пользователя перед стартом

- Создать бота в BotFather, включить Serverless, получить CLI-токен.
- Выполнить `npm install` и `npx tgcloud login` в проекте.
- Сообщить имя/юзернейм бота и Telegram ID супер-админа. (Сделано: `@pod_elochku_bot`, `115462629`.)
- Подтвердить валюту, раскрытие Санты и политику модерации вишлиста.
- Подтвердить, что ассеты из `docs/` можно использовать как бренд бота и Mini App.
- Быть готовым выполнить `push`/`migrate` и проверить смоук-тест в Telegram.

## 21. Definition of Done

- [ ] Сквозной сценарий из раздела 16 проходит на реальном боте.
- [ ] `node --check` и `node --test` зелёные; `npm run build` успешен.
- [ ] `npx tgcloud status` чистый, `webhook in sync`, миграции применены пользователем.
- [ ] Матрица прав и негативные сценарии проверены, утечек нет.
- [ ] `delivery_info` и `claims` чистятся при завершении/отмене.
- [ ] Экраны работают на 320/390/768/1280, в светлой/тёмной теме, с reduced motion.
- [ ] Контраст, фокус, тап-таргеты и reflow проверены.
- [ ] `AGENTS.md`, `README.md`, `docs/tgcloud-sdk.md` и этот план актуальны.
- [ ] Пользователь получил точные команды деплоя и отчёт о проверках.

## 22. Статус реализации (2026-11-26)

Сделано локально:

- [x] Каркас, `AGENTS.md`, `README.md`, копия SDK, запись в `serverless/AGENTS.md`.
- [x] `tgcloud/schema.js` (13 таблиц, уникальные и составные индексы).
- [x] Чистая логика: жеребьёвка (Хопкрофт–Карп + обмен пар), коды, статусы, вишлист, чат.
- [x] `node --test`: 32 теста, все зелёные.
- [x] Бот: визард, дашборд, участники, исключения, жеребьёвка, рассылка, модерация, супер-админ.
- [x] Endpoints: 23 файла, включая `getGiftStatus`; доступ через `ctx.initData.user`.
- [x] Mini App: Vue 3 + Vite, 8 экранов, светлая/тёмная тема, mock для dev.
- [x] Форма доставки в лобби: адрес/телефон видит только Санта, данные очищаются при завершении.
- [x] `npm --prefix miniapp run build` — успешен; JS 148.7 КБ (gzip 53 КБ).
- [x] Визуальная проверка Chrome: 320/390/768/1280, overflow 0, консоль без ошибок.
- [x] Ассеты: hero 1280/640, tree 512/192, avatar 640, self-hosted Cyrillic/Latin woff2.

Не сделано / требует пользователя:

- [ ] Этап 1: замер JSON-тела/ответа endpoint, `InputFile` + `getFileContent`, deep links.
- [ ] `npx tgcloud push` / `migrate` / `webhook` — только пользователь; реальная схема не проверена.
- [x] `APP_URL` = `https://app8782869988.tgcloud.ai/`; статика проверена (200, хеши совпадают с локальной сборкой).
- [ ] Повторный `push` после правки `APP_URL`, чтобы бот показывал кнопку приложения.
- [ ] Проверка в реальном Telegram WebView (iOS/Android), Windows scaling 125/150%.
- [ ] Полный E2E на трёх тестовых аккаунтах.
- [ ] Полировка: drag-reorder вишлиста (сейчас кнопки ↑/↓), fallback загрузки фото через бота, подтверждение внешних ссылок.

Отклонения от плана:

- `getGiftStatus` добавлен отдельно, чтобы получатель видел статус подарка, но не вишлист Санты.
- В `assignments` добавлены `chatBlocked/chatBlockedAt` для модерации.
- Отчёты хранятся в `chat_messages` (`reportedAt/reportedBy/reportReason`), отдельная таблица не понадобилась.
- Шрифты подключены вручную (woff2, только Cyrillic/Latin) вместо `@fontsource`-пакета.
- Ассеты генерирует `scripts/optimize-assets.ps1` (System.Drawing), а не `sharp`.
- `docs/tgcloud-sdk.md` скопирован из `ny2027-tgcloud`.

