# NY2027 — Telegram Serverless (BotFather)

Версия бота для **Telegram Serverless** (BotFather → Serverless), а не для
Cloudflare. Проект уже разложен по структуре `tgcloud/`, связан с ботом и
задеплоен.

PHP-версия удалена вместе с файлами, логами и данными. Актуальна только эта
Serverless-версия; она живёт в этом каталоге и обновляется своим CLI.

## Текущее состояние

- Проект привязан к `app9204350653`.
- Локальные изменения: правки `bot.js`/`telegram.js` и новые модули игры.
  После `push` проверь: `tgcloud status` — clean, `tgcloud webhook` — in sync,
  `tgcloud migrate --dry-run` — no schema changes.
- Картинка приветствия встроена в код (`tgcloud/lib/goat-image.js`), внешних
  URL/file_id нет.
- Меню команд: `/start`, `/my`, `/ticket`, `/game`, `/help`, `/cancel`.
- Локально добавлена мини-игра `/game` (HTML-виджет Bot API 10.4). Чтобы она
  появилась в боте, нужен `push`; после него — админский `/setcommands`.
- PHP-батник и его файлы/данные удалены; запускать нечего. Webhook управляется
  Serverless-платформой.

## Что внутри

- `tgcloud/handlers/message.js` — обработчик сообщений;
- `tgcloud/handlers/callback_query.js` — обработчик inline-кнопок;
- `tgcloud/lib/` — конфиг, утилиты, Telegram API, БД и логика бота;
- `tgcloud/lib/game-widget.js` — HTML мини-игры (Bot API 10.4 widget);
- `tgcloud/lib/game.js` — отправка виджета и сохранение рекорда;
- `tgcloud/lib/goat-image.js` — встроенная картинка приветствия (JPEG 720×900,
  base64, ~233 КБ модуля);
- `assets/goat.jpg` — та же картинка отдельным файлом для просмотра (не деплоится);
- `assets/goat-source.png` — исходный PNG, сохранённый из удалённой PHP-версии;
- `tgcloud/schema.js` — таблицы SQLite (`users`, `registrations`, `sessions`,
  `broadcast_drafts`, `tickets`);
- `docs/tgcloud-sdk.md` — официальный справочник SDK;
- `AGENTS.md` — правила проекта от Telegram.

Функциональность: приветствие с фото, анкета, `/my`, удаление своей анкеты,
билеты на матч (`/ticket`), мини-игра `/game` (HTML-виджет Bot API 10.4),
админские `/stats`, `/list`, `/export`, `/delete`, `/deleteall`, `/tickets`,
рассылка с превью и оформлением «Сообщение от GloPro».

## Как обновлять

```bash
cd serverless/ny2027-tgcloud
npx tgcloud status
npx tgcloud push
npx tgcloud webhook
```

- `push` заливает изменённые `handlers/`, `lib/` и `config.js`.
- После `push` в `status` должно быть `Working directory clean`.
- `webhook` должен показывать `in sync with your deployed handlers`.
- Если менял список команд в `setBotCommands`, после `push` выполни админский
  `/setcommands` (один раз) — он вызывает `setMyCommands`.
- `tgcloud pull` не запускай: он перезапишет локальные модули облачным
  состоянием. Если `push` попросит snapshot — `npx tgcloud fetch` (он не трогает
  рабочие файлы), затем снова `push`.

### Если меняется схема БД

```bash
npx tgcloud push
npx tgcloud migrate --dry-run
npx tgcloud migrate
```

`push` только выкладывает `schema.js` и показывает, что изменится; `migrate`
применяет изменения. Добавление таблиц/колонок — автоматически, удаление — только
через `.deprecated()` в схеме.

### Если меняется картинка

Картинка лежит в `tgcloud/lib/goat-image.js` как base64 JPEG и отправляется через
`InputFile` — без внешнего URL. Чтобы заменить:

1. положить новый JPEG в `assets/goat.jpg` (для себя);
2. пересобрать `tgcloud/lib/goat-image.js` с новым base64;
3. `npx tgcloud push`.

## Команды

Пользователь:

- `/start` — приветствие и анкета;
- `/my` — своя анкета;
- `/game` — новогодняя мини-игра «Новогодний заезд»;
- `/cancel` — отменить заполнение;
- `/id` — свой Telegram ID;
- кнопка «🗑 Удалить анкету» — удалить свою анкету с подтверждением.

Админ:

- `/stats` — активные/удалённые и размеры;
- `/list` — активные анкеты с номерами + количество удалённых;
- `/export` — CSV активных и удалённых (статусы «Активна»/«Удалена»);
- `/delete <номер из /list | Telegram ID>` — удалить одну анкету;
- `/deleteall` — удалить все активные анкеты;
- `/setcommands` — обновить меню «/» (`start`/`my`/`ticket`/`game`/`help`/`cancel`);
- `/ticket &lt;Telegram ID&gt;` — выдать билет пользователю на матч;
- `/tickets` — статистика билетов на матч (сколько всего, выдано, осталось);
- `/broadcast` или кнопка «✉️ Рассылка» в `/help` — рассылка с превью,
  оформлением «Сообщение от GloPro», отчётом и исключением отправителя.

Админское удаление:

- показывает подтверждение с ФИО/компанией/размером;
- переносит анкету в архив (`status = deleted`, `deletedAt`), у пользователя она
  исчезает;
- очищает сессию пользователя; `/deleteall` очищает все сессии;
- в `/export` удалённые остаются со статусом «Удалена»;
- защита от устаревшей кнопки: в callback зашит токен от `createdAt`; если анкету
  удалили и заполнили заново, старая кнопка не удалит новую;
- логи: каждая удалённая анкета, действие админа и итог массового удаления.

**Важно:** кнопки «Да, удалить» / «Удалить все» в тестовых сообщениях — живые.
Нажатие реально удалит текущие анкеты Serverless-базы. «Отмена» безопасна.

## Билеты на матч

Билеты — уникальные PDF-файлы, не персональные, но выдаются по одному в руки.
Каждый билет уходит только одному пользователю и больше не повторяется.
Все операции с билетами доступны только администраторам.

- Админ загружает билеты: присылает боту PDF-файл в личном чате — бот сохраняет
  его `file_id` в таблицу `tickets`. Не-ПDF файлы отвергаются.
- `/ticket &lt;Telegram ID&gt;` — выдать первый свободный билет указанному
  пользователю как документ в Telegram. Если билетов кончились — отказ.
- `/tickets` — статистика: всего / выдано / осталось.

## Мини-игра `/game` (Bot API 10.4)

`/game` отправляет не Mini App, а **HTML-виджет** — интерактивный блок прямо
внутри сообщения (`sendWidget`, Bot API 10.4). Внутри — раннер в духе динозавра
Chrome: машина едет по ночному городу, тап или клик — прыжок, препятствия —
колонки АЗС. Скорость растёт, счёт идёт, рекорд сохраняется.

- HTML лежит в `tgcloud/lib/game-widget.js` (inline CSS/JS, **без canvas**:
  DOM + inline SVG + CSS-трансформы). Сейчас ~9.1 КБ исходника и ~3.8 КБ после
  zlib — в лимитах виджета (32 КБ HTML / 4 КБ zlib).
- Производительность: игровой цикл ограничен 30 fps, рендер идёт только во
  время заезда, без аллокаций `filter` каждый кадр. Снежинок 6, колонок в пуле 5,
  зданий 12; трансформа машины меняется при прыжке и минимальном покачивании (±0.5 px).
- Игровое поле 360×220 масштабируется целиком через `transform: scale()`;
  машина/колонки двигаются CSS-трансформами, фон — статичный SVG, снег — CSS
  keyframes. Canvas-рендер Telegram Desktop не участвует, поэтому нет его
  полос/скоса на десктопе и нет пиксельной нагрузки на телефон.
- Машина — фирменный синий `#0074dd`, на двери логотип GP. Пути из присланного
  SVG запечены в координатах 28×12, без transform/viewBox-скейла — так логотип
  виден и в desktop-клиенте.
- Прохождение смягчено: прыжок по `pointerdown` без задержки `click`, выше
  прыжок, меньше хитбоксы и длиннее пауза между колонками.
- `tgcloud/lib/game.js` вызывает `api.sendWidget` без запросов к боту: игре не
  нужен сетевой обмен. Рекорд хранится в `localStorage` этого сообщения и
  переживает сворачивание и пересоздание виджета на устройстве.
- Виджет нельзя заменить в уже отправленном сообщении: «Ещё раз» перезапускает
  игру в том же сообщении, `/game` отправляет новое.
- В production Bot API 10.4 виджеты работают только в личных чатах и не
  пересылаются. Бот и так работает только в личных сообщениях.
- Схема БД не меняется, `migrate` не нужен. После `push` выполни админский
  `/setcommands`, чтобы `/game` появился в меню «/».
- CLI `tgcloud` пока не принимает handler `widget_query` — в его whitelist нет
  этого типа update из Bot API 10.4. Поэтому серверное сохранение рекорда
  отключено; игра полностью клиентская. Когда CLI/платформа добавят
  `widget_query`, можно вернуть `TelegramWidget.request` и запись `chat_data`.

## Меню команд

Меню «/» задаётся Bot API `setMyCommands`. В Serverless это делает админская
команда `/setcommands` (сама в меню не показывается). Список:
`/start`, `/my`, `/ticket`, `/game`, `/help`, `/cancel`.

Выполнить один раз после деплоя:

```powershell
node node_modules\@tgcloud\cli\bin\tgcloud.js run handlers/message "{chat:{id:ВАШ_ID,type:'private'},from:{id:ВАШ_ID,first_name:'Admin'},text:'/setcommands'}"
```

Меню общее для всех приватных чатов (default scope). Если меняешь список —
поправь `setBotCommands` в `tgcloud/lib/telegram.js`, сделай `push` и снова
выполни `/setcommands`.

## Проверка и логи

Мини-игра проверена локально: HTML-виджет открыт в Chromium на ширинах
660 px (десктоп, игровое поле 660×403, `scale(1.833)`) и 360 px (телефон,
360×220, `scale(1)`). Синяя машина с логотипом, старт, прыжок через колонку,
столкновение, game over и «Ещё раз» работают, ошибок в консоли нет.
Размеры: ~9.2 КБ HTML, ~3.9 КБ zlib. `node --check` проходит для
`game-widget.js`, `game.js`, `bot.js`, `telegram.js`.

`run` выполняет модуль в облаке без деплоя, использует реальные API и БД и
отправляет настоящие сообщения в указанный чат. На Windows PowerShell обычный
JSON в аргументах ломается, поэтому используй JSON5 с одинарными кавычками и
прямой вызов CLI:

```powershell
node node_modules\@tgcloud\cli\bin\tgcloud.js run handlers/message "{chat:{id:ВАШ_ID,type:'private'},from:{id:ВАШ_ID,first_name:'Admin'},text:'/stats'}"
```

Примеры:

```powershell
# список
node node_modules\@tgcloud\cli\bin\tgcloud.js run handlers/message "{chat:{id:ВАШ_ID,type:'private'},from:{id:ВАШ_ID,first_name:'Admin'},text:'/list'}"

# подтверждение удаления одной (сам confirm не нажимать)
node node_modules\@tgcloud\cli\bin\tgcloud.js run handlers/message "{chat:{id:ВАШ_ID,type:'private'},from:{id:ВАШ_ID,first_name:'Admin'},text:'/delete 1'}"

# подтверждение удаления всех (сам confirm не нажимать)
node node_modules\@tgcloud\cli\bin\tgcloud.js run handlers/message "{chat:{id:ВАШ_ID,type:'private'},from:{id:ВАШ_ID,first_name:'Admin'},text:'/deleteall'}"
```

Проверено живыми прогонами: `/stats`, `/delete 1`, `/deleteall`, `/export`,
`/setcommands` — команды выполнились, подтверждения, CSV и обновление меню ушли
в чат; confirm-кнопки не нажимались.

Логи анкет/удалений/рассылки идут через `console.log` и видны в
`tgcloud run` и в логах Serverless в BotFather:

```
Новый пользователь бота: ...
Анкета создана/обновлена: ...
Анкета удалена: ...
Массовое удаление анкет: ...
broadcast: admin=..., total=..., sent=..., blocked=..., failed=...
```

## Первичная настройка / пересоздание

Проект уже связан и задеплоен. Эти шаги нужны только на новой машине или при
пересоздании проекта.

1. В BotFather включён **Serverless**.
2. BotFather → Serverless → **CLI Access** → токен вида `app...:...`.
3. В каталоге проекта:

```bash
cd serverless/ny2027-tgcloud
npm install
npx tgcloud login
npx tgcloud fetch     # если push попросит snapshot
npx tgcloud push
npx tgcloud migrate
```

`login` сохраняет токен в `.tgcloud/` (каталог в `.gitignore`, в git не попадёт).
`push` выкладывает модули, `migrate` создаёт/обновляет таблицы. Вручную
`setWebhook` вызывать не нужно — платформа делает это сама, состояние показывает
`npx tgcloud webhook`.

Если `push` попросит snapshot — `npx tgcloud fetch`, затем `push`. `pull` не
запускать: он перезаписывает рабочие файлы.

### Если CLI ругается

На этой Windows-машине `tgcloud init` падал с `NotFound: FileSystem.access`,
поэтому проект собран вручную из шаблонов CLI. `init` запускать не нужно. Если
`login`/`push`/`migrate` упадут с той же ошибкой — запусти CLI в WSL/Linux или
загрузи модули через Serverless-раздел BotFather (Handlers / Library / Database).

## Настройки бота

`tgcloud/lib/config.js`:

- `ADMIN_IDS` — Telegram ID админов; сейчас `442705820` и `115462629`. Свой ID — `/id`.
- `MAX_BROADCAST` — максимум получателей одной рассылки (по умолчанию 100).
- Картинка приветствия встроена в `tgcloud/lib/goat-image.js`; внешних URL и
  `file_id` нет. Если встроенную отправить не удастся, бот пришлёт только текст.

## Данные

- PHP-версия, её скрипты, батник, логи, JSON/CSV и `ny2027_photo.json` удалены.
- Актуальная база — SQLite Serverless; `/export` собирает CSV из неё и служит
  резервной копией.
- Анкеты/пользователи из PHP-версии не переносились: если там были реальные
  данные, их уже нет. Текущие данные смотри в `/stats`, `/list`, `/export`.
- Оригинал картинки сохранён в `assets/goat-source.png`.
- `TG_GLOPRO_NY_27` в `.env` — это токен бота, а не PHP-кода; Serverless его не
  использует, но он может понадобиться для BotFather/API. Переменные `NY27_*`
  удалены.

## Ограничения

- Картинка встроена в модуль `goat-image.js` (~233 КБ). Это больше обычного
  модуля; если `push` упрётся в лимит размера, уменьши JPEG и пересобери модуль.
- Рассылка синхронная и ограничена `MAX_BROADCAST`; для больших списков увеличь
  `MAX_BROADCAST`.
- Нет локального CSV-файла: `/export` собирает CSV из SQLite и отправляет
  документом в Telegram.
- `tgcloud push` выкладывает код и `schema.js`, но не применяет изменения БД;
  `tgcloud migrate` применяет их.
- `tgcloud init` на этой машине падает; проект уже создан вручную, `init` не нужен.

## Что не используется

`serverless/ny2027-worker` — отдельный эксперимент под Cloudflare Workers, к
Telegram Serverless отношения не имеет. Его можно удалить.
