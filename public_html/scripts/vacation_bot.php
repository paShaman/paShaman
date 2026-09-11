<?php

include __DIR__ . '/_env.php';

/**
 * Бот учёта отсутствий (Telegram, webhook).
 *
 * Сотрудники вносят свои отсутствия (отпуск/отгул/больничный/учёба/прочее),
 * смотрят свои записи и остаток отпуска, а также общую картину по команде.
 *
 * Режим: webhook (аналог smart_checklist_ai_bot.php), без демона и cron.
 *
 * Установка вебхука (один раз):
 *   https://api.telegram.org/bot<VACATION_TG_TOKEN>/setWebhook?url=https://paShaman.dev/scripts/vacation_bot.php&secret_token=<VACATION_WEBHOOK_SECRET>
 * Проверка:
 *   https://api.telegram.org/bot<VACATION_TG_TOKEN>/getWebhookInfo
 *
 * Конфигурация (getenv, см. .env):
 *   VACATION_TG_TOKEN        — токен бота от @BotFather
 *   VACATION_WEBHOOK_SECRET  — секрет вебхука (заголовок X-Telegram-Bot-Api-Secret-Token)
 *   VACATION_ADMIN_ID        — Telegram ID тех.админа (управляет белым списком)
 *   VACATION_ANNUAL_DAYS     — норма отпуска в год, по умолчанию 28 (календарных дней)
 *   VACATION_TZ              — часовой пояс, по умолчанию Europe/Moscow
 *   VACATION_TYPES           — список типов "ключ:эмодзи,ключ:эмодзи" (опционально)
 *   VACATION_QUOTA_TYPES     — типы, списывающие остаток отпуска (по умолчанию "отпуск")
 *
 * Хранение (public_html/scripts/logs/):
 *   vacations_users.json     — белый список / справочник (сидируется из glopro_users.json)
 *   vacations_records.json   — все отсутствия
 *   vacations_sessions.json  — промежуточные состояния диалога /add
 *   vacations.offset         — последний обработанный update_id (защита от повторов)
 *   vacations.log            — рабочий лог
 *   vacations_errors.log     — ошибки
 *
 * Команды: /start /help /id /add /my /left /cancel /edit /finish /today /week
 *          /month /soon /user /users, админ: /adduser /deluser
 */

header('Content-Type: application/json');

final class VacationBot
{
    /** Лимит символов для rich message (sendRichMessage). */
    private const RICH_MAX = 32768;
    /** Лимит символов для обычного sendMessage. */
    private const TEXT_MAX = 4096;
    /** Время жизни незавершённого диалога, сек. */
    private const SESSION_TTL = 1800;

    /** Токен Telegram-бота. */
    private ?string $tgToken = null;
    /** Секрет вебхука. */
    private string $webhookSecret = '';
    /** Telegram ID тех.админа. */
    private string $adminId = '';
    /** Часовой пояс. */
    private string $tz = 'Europe/Moscow';
    /** Норма отпуска по умолчанию (календарных дней в году). */
    private int $defaultAnnualDays = 28;

    /** @var array<string, string> тип => эмодзи */
    private array $absenceTypes = [];
    /** @var string[] типы, списывающие остаток отпуска */
    private array $quotaTypes = [];

    /** Кэш таймзоны. */
    private ?DateTimeZone $timezone = null;

    public function __construct()
    {
        $this->tgToken = trim((string)getenv('VACATION_TG_TOKEN')) ?: null;
        $this->webhookSecret = trim((string)getenv('VACATION_WEBHOOK_SECRET'));
        $this->adminId = trim((string)getenv('VACATION_ADMIN_ID'));
        $this->tz = trim((string)getenv('VACATION_TZ')) ?: 'Europe/Moscow';

        $annualDays = (int)getenv('VACATION_ANNUAL_DAYS');
        $this->defaultAnnualDays = $annualDays > 0 ? $annualDays : 28;

        date_default_timezone_set($this->tz);
        $this->parseTypes();

        $this->ensureLogsDir();
        $this->ensureSeedUsers();
    }

    // ---------------------------------------------------------------------
    // Точка входа (webhook)
    // ---------------------------------------------------------------------

    public function run(): string
    {
        $raw = (string)file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return 'no_data';
        }

        // Проверка секрета вебхука.
        $secret = (string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
        if ($this->webhookSecret === '' || !hash_equals($this->webhookSecret, $secret)) {
            $this->logError('unauthorized webhook (secret mismatch or not configured)');
            return 'unauthorized';
        }

        if ($this->tgToken === null) {
            $this->logError('VACATION_TG_TOKEN не задан');
            return 'no_token';
        }

        // Защита от повторной обработки одного и того же апдейта.
        $updateId = isset($data['update_id']) ? (int)$data['update_id'] : null;
        if ($updateId !== null) {
            $offsetFile = __DIR__ . '/logs/vacations.offset';
            if ($updateId <= $this->readOffset($offsetFile)) {
                return 'duplicate';
            }
            $this->saveOffset($offsetFile, $updateId);
        }

        try {
            if (isset($data['callback_query'])) {
                $this->handleCallback($data['callback_query']);
            } elseif (isset($data['message'])) {
                $this->handleMessage($data['message']);
            }
            return 'ok';
        } catch (\Throwable $e) {
            $this->logError('exception: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            return 'error';
        }
    }

    // ---------------------------------------------------------------------
    // Обработка сообщений
    // ---------------------------------------------------------------------

    /**
     * @param array<string, mixed> $message
     */
    private function handleMessage(array $message): void
    {
        $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];
        $from = is_array($message['from'] ?? null) ? $message['from'] : [];
        $chatId = (string)($chat['id'] ?? '');
        $userId = (string)($from['id'] ?? '');
        $chatType = (string)($chat['type'] ?? 'private');
        $text = trim((string)($message['text'] ?? ''));

        if ($chatId === '' || $userId === '') {
            return;
        }

        if ($chatType !== 'private') {
            $this->sendHtml($chatId, '🤖 Бот работает только в личных сообщениях.');
            return;
        }

        $parts = preg_split('/\s+/', $text, 2) ?: [''];
        $command = mb_strtolower(explode('@', (string)($parts[0] ?? ''), 2)[0]);
        $arg = trim((string)($parts[1] ?? ''));

        // /id доступен всегда.
        if ($command === '/id') {
            $this->sendHtml($chatId, "🆔 Ваш Telegram ID: <code>{$userId}</code>");
            return;
        }

        if (!$this->isAllowed($userId)) {
            $this->sendHtml(
                $chatId,
                "🔒 <b>Доступа нет</b>\n\nВаш Telegram ID: <code>{$userId}</code>\n"
                . 'Передайте его администратору, чтобы он добавил вас в список.'
            );
            return;
        }

        $this->touchUser($userId, $from);

        // Свободный текст в рамках активного диалога (/add или перенос/завершение).
        if ($text !== '' && $command !== '' && $command[0] !== '/') {
            $session = $this->getSession($userId);
            if ($session !== null && $this->handleSessionText($chatId, $userId, $session, $text)) {
                return;
            }
        }

        if ($command === '') {
            return;
        }

        switch ($command) {
            case '/start':
            case '/help':
                $this->cmdHelp($chatId, $userId);
                return;
            case '/add':
                $this->cmdAdd($chatId, $userId, $arg);
                return;
            case '/my':
                $this->cmdMy($chatId, $userId, $arg);
                return;
            case '/left':
            case '/balance':
                $this->cmdLeft($chatId, $userId, $arg);
                return;
            case '/cancel':
                $this->cmdCancel($chatId, $userId, $arg);
                return;
            case '/edit':
                $this->cmdEdit($chatId, $userId, $arg);
                return;
            case '/finish':
                $this->cmdFinish($chatId, $userId, $arg);
                return;
            case '/today':
                $this->cmdToday($chatId, $userId);
                return;
            case '/week':
                $this->cmdWeek($chatId, $userId);
                return;
            case '/month':
                $this->cmdMonth($chatId, $userId, $arg);
                return;
            case '/soon':
                $this->cmdSoon($chatId, $userId, $arg);
                return;
            case '/user':
                $this->cmdUser($chatId, $userId, $arg);
                return;
            case '/users':
                $this->cmdUsers($chatId, $userId);
                return;
            case '/adduser':
                $this->cmdAddUser($chatId, $userId, $arg);
                return;
            case '/deluser':
                $this->cmdDelUser($chatId, $userId, $arg);
                return;
            default:
                if (str_starts_with($command, '/')) {
                    $this->sendHtml($chatId, 'Неизвестная команда. Список команд: /help');
                }
                return;
        }
    }

    // ---------------------------------------------------------------------
    // Обработка inline-кнопок
    // ---------------------------------------------------------------------

    /**
     * @param array<string, mixed> $cb
     */
    private function handleCallback(array $cb): void
    {
        $cbId = (string)($cb['id'] ?? '');
        $from = is_array($cb['from'] ?? null) ? $cb['from'] : [];
        $userId = (string)($from['id'] ?? '');
        $data = (string)($cb['data'] ?? '');
        $msg = is_array($cb['message'] ?? null) ? $cb['message'] : [];
        $chat = is_array($msg['chat'] ?? null) ? $msg['chat'] : [];
        $chatId = (string)($chat['id'] ?? $userId);

        $this->answerCallback($cbId);

        if ($userId === '') {
            return;
        }

        if (!$this->isAllowed($userId)) {
            $this->sendHtml($chatId, '🔒 Доступа нет.');
            return;
        }

        $parts = explode(':', $data);
        $scope = (string)($parts[0] ?? '');

        switch ($scope) {
            case 'menu':
                $this->menuAction($chatId, $userId, (string)($parts[1] ?? ''));
                return;
            case 'add':
                $this->addAction($chatId, $userId, $parts);
                return;
            case 'rec':
                $this->recAction($chatId, $userId, $parts);
                return;
            case 'adm':
                $this->adminAction($chatId, $userId, $parts);
                return;
            case 'usr':
                $this->cmdUser($chatId, $userId, (string)($parts[1] ?? ''));
                return;
        }
    }

    private function menuAction(string $chatId, string $userId, string $action): void
    {
        switch ($action) {
            case 'add':
                $this->startAddDialog($chatId, $userId);
                return;
            case 'my':
                $this->cmdMy($chatId, $userId, '');
                return;
            case 'left':
                $this->cmdLeft($chatId, $userId, '');
                return;
            case 'today':
                $this->cmdToday($chatId, $userId);
                return;
            case 'week':
                $this->cmdWeek($chatId, $userId);
                return;
            case 'month':
                $this->cmdMonth($chatId, $userId, '');
                return;
            case 'soon':
                $this->cmdSoon($chatId, $userId, '');
                return;
            case 'users':
                $this->cmdUsers($chatId, $userId);
                return;
            case 'admin':
                $this->cmdAdminUsers($chatId, $userId);
                return;
            case 'help':
            default:
                $this->cmdHelp($chatId, $userId);
                return;
        }
    }

    /**
     * @param string[] $parts
     */
    private function addAction(string $chatId, string $userId, array $parts): void
    {
        $sub = (string)($parts[1] ?? '');
        $value = (string)($parts[2] ?? '');

        switch ($sub) {
            case 'type':
                if (!isset($this->absenceTypes[$value])) {
                    $this->sendHtml($chatId, '⚠️ Неизвестный тип. Начните заново: /add');
                    return;
                }
                $this->setSession($userId, [
                    'step'   => 'dates',
                    'type'   => $value,
                    'ranges' => [],
                    'comment' => '',
                ]);
                $this->sendHtml(
                    $chatId,
                    '📅 <b>Введите даты</b> отсутствия (' . $this->esc($this->typeLabel($value)) . "):\n"
                    . '• <code>01.07.2026-14.07.2026</code> — диапазон с годом' . "\n"
                    . '• <code>01.07-14.07</code> — диапазон в текущем году' . "\n"
                    . '• <code>01.07</code> — один день' . "\n"
                    . '• <code>01.07 +14</code> — дата старта и количество дней' . "\n"
                    . '• можно несколько через запятую: <code>01.07-14.07, 01.09-05.09</code>',
                    $this->inlineKeyboard([[self::btn('❌ Отмена', 'add:cancel')]])
                );
                return;
            case 'comment':
                $this->setSession($userId, ['step' => 'comment']);
                $this->sendHtml(
                    $chatId,
                    '💬 Введите комментарий (или «-», чтобы очистить):',
                    $this->inlineKeyboard([[self::btn('❌ Отмена', 'add:cancel')]])
                );
                return;
            case 'skipcomment':
                $session = $this->getSession($userId);
                if ($session !== null) {
                    $session['comment'] = '';
                    $session['step'] = 'confirm';
                    $this->setSession($userId, $session);
                    $this->sendAddConfirm($chatId, $userId, $session);
                }
                return;
            case 'edit':
                $session = $this->getSession($userId);
                if ($session !== null) {
                    $session['step'] = 'dates';
                    $this->setSession($userId, $session);
                    $this->sendHtml($chatId, '📅 Введите новые даты:');
                }
                return;
            case 'confirm':
                $this->confirmAdd($chatId, $userId);
                return;
            case 'cancel':
            default:
                $this->clearSession($userId);
                $this->sendHtml($chatId, '❌ Отменено.');
                return;
        }
    }

    /**
     * @param string[] $parts
     */
    private function recAction(string $chatId, string $userId, array $parts): void
    {
        $action = (string)($parts[1] ?? '');
        $id = (int)($parts[2] ?? 0);
        if ($id <= 0) {
            return;
        }

        switch ($action) {
            case 'cancel':
                $result = $this->deleteRecord($id, $userId);
                if (!$result['ok']) {
                    $this->sendHtml($chatId, $result['err'] === 'not_owner'
                        ? '⛔ Можно отменять только свои записи.'
                        : '⚠️ Запись не найдена.');
                    return;
                }
                $this->sendHtml($chatId, '🗑 Запись #' . $id . ' отменена.');
                return;
            case 'edit':
                $record = $this->findRecord($id);
                if ($record === null) {
                    $this->sendHtml($chatId, '⚠️ Запись не найдена.');
                    return;
                }
                if ($record['user_id'] !== $userId && !$this->isAdmin($userId)) {
                    $this->sendHtml($chatId, '⛔ Можно редактировать только свои записи.');
                    return;
                }
                $this->setSession($userId, ['step' => 'edit_dates', 'target' => $id]);
                $this->sendHtml($chatId, "📅 Введите новые даты для записи #{$id}:");
                return;
            case 'finish':
                $record = $this->findRecord($id);
                if ($record === null) {
                    $this->sendHtml($chatId, '⚠️ Запись не найдена.');
                    return;
                }
                if ($record['user_id'] !== $userId && !$this->isAdmin($userId)) {
                    $this->sendHtml($chatId, '⛔ Можно завершать только свои записи.');
                    return;
                }
                $this->setSession($userId, ['step' => 'finish_date', 'target' => $id]);
                $this->sendHtml(
                    $chatId,
                    "🏁 Введите дату фактического выхода для записи #{$id}\n"
                    . '(или «сегодня»):',
                    $this->inlineKeyboard([[self::btn('❌ Отмена', 'add:cancel')]])
                );
                return;
        }
    }

    // ---------------------------------------------------------------------
    // Диалог добавления
    // ---------------------------------------------------------------------

    private function cmdAdd(string $chatId, string $userId, string $arg): void
    {
        // Быстрый вариант: /add отпуск 01.07-14.07
        if ($arg !== '') {
            $ap = preg_split('/\s+/', $arg, 2) ?: [];
            $typeKey = mb_strtolower(trim((string)($ap[0] ?? '')));
            if ($typeKey !== '' && isset($this->absenceTypes[$typeKey]) && !empty($ap[1])) {
                try {
                    $ranges = $this->parseRanges((string)$ap[1]);
                } catch (RuntimeException $e) {
                    $this->sendHtml($chatId, '⚠️ ' . $this->esc($e->getMessage()));
                    return;
                }
                $session = ['step' => 'confirm', 'type' => $typeKey, 'ranges' => $ranges, 'comment' => ''];
                $this->setSession($userId, $session);
                $this->sendAddConfirm($chatId, $userId, $session);
                return;
            }
        }

        $this->startAddDialog($chatId, $userId);
    }

    private function startAddDialog(string $chatId, string $userId): void
    {
        $this->setSession($userId, ['step' => 'type', 'type' => '', 'ranges' => [], 'comment' => '']);

        $rows = [];
        $row = [];
        foreach ($this->absenceTypes as $key => $emoji) {
            $row[] = self::btn($emoji . ' ' . $this->ucfirst($key), 'add:type:' . $key);
            if (count($row) === 2) {
                $rows[] = $row;
                $row = [];
            }
        }
        if ($row !== []) {
            $rows[] = $row;
        }
        $rows[] = [self::btn('❌ Отмена', 'add:cancel')];

        $this->sendHtml($chatId, '➕ <b>Новое отсутствие</b>. Выберите тип:', $this->inlineKeyboard($rows));
    }

    /**
     * @param array<string, mixed> $session
     */
    private function sendAddConfirm(string $chatId, string $userId, array $session): void
    {
        $ranges = is_array($session['ranges'] ?? null) ? $session['ranges'] : [];
        if ($ranges === []) {
            $this->sendHtml($chatId, '⚠️ Нет дат. Начните заново: /add');
            return;
        }

        $type = (string)($session['type'] ?? '');
        $comment = trim((string)($session['comment'] ?? ''));

        $lines = ['<b>Проверьте запись</b>', ''];
        $lines[] = 'Тип: ' . $this->esc($this->typeLabel($type));

        $total = 0;
        foreach ($ranges as $r) {
            $days = $this->daysInRange((string)$r['from'], (string)$r['to']);
            $total += $days;
            $lines[] = '• ' . $this->esc($this->formatInterval((string)$r['from'], (string)$r['to']))
                . ' — ' . $days . ' к.д.';
        }
        if (count($ranges) > 1) {
            $lines[] = 'Итого: <b>' . $total . '</b> к.д.';
        }
        if ($comment !== '') {
            $lines[] = 'Комментарий: ' . $this->esc($comment);
        }
        if (in_array($type, $this->quotaTypes, true)) {
            $lines[] = 'Спишется с отпуска: <b>' . $total . '</b> дн.';
        }

        // Проверка пересечений (информативно, не блокирует).
        $overlaps = $this->findOverlaps($userId, $ranges);
        if ($overlaps !== []) {
            $lines[] = '';
            $lines[] = '⚠️ <b>Пересечения:</b>';
            foreach ($overlaps as $o) {
                $lines[] = '• ' . $this->esc($o['name']) . ' (' . $this->esc($o['interval']) . ')'
                    . ($o['own'] ? ' — ваша запись' : '');
            }
        }

        $this->sendHtml(
            $chatId,
            implode("\n", $lines),
            $this->inlineKeyboard([
                [self::btn('✅ Подтвердить', 'add:confirm'), self::btn('✏️ Даты', 'add:edit')],
                [self::btn('💬 Комментарий', 'add:comment'), self::btn('❌ Отмена', 'add:cancel')],
            ])
        );
    }

    private function confirmAdd(string $chatId, string $userId): void
    {
        $session = $this->getSession($userId);
        if ($session === null || ($session['step'] ?? '') !== 'confirm') {
            $this->sendHtml($chatId, '⚠️ Заявка устарела. Начните заново: /add');
            return;
        }

        $ranges = is_array($session['ranges'] ?? null) ? $session['ranges'] : [];
        $type = (string)($session['type'] ?? '');
        $comment = trim((string)($session['comment'] ?? ''));

        if ($ranges === [] || !isset($this->absenceTypes[$type])) {
            $this->clearSession($userId);
            $this->sendHtml($chatId, '⚠️ Некорректная заявка. Начните заново: /add');
            return;
        }

        $created = $this->addRecords($userId, $type, $ranges, $comment);
        $this->clearSession($userId);

        $ids = array_map(static fn(array $r): int => (int)$r['id'], $created);
        $days = 0;
        foreach ($created as $r) {
            $days += (int)$r['days'];
        }

        $lines = ['✅ <b>Записано</b>'];
        foreach ($created as $r) {
            $lines[] = '#' . $r['id'] . ' · ' . $this->esc($this->typeLabel((string)$r['type']))
                . ' · ' . $this->esc($this->formatInterval((string)$r['date_from'], (string)$r['date_to']))
                . ' (' . $r['days'] . ' к.д.)';
        }

        if (in_array($type, $this->quotaTypes, true)) {
            $year = (int)substr((string)$ranges[0]['from'], 0, 4);
            $balance = $this->balanceFor($userId, $year);
            $lines[] = '';
            $lines[] = 'Остаток отпуска в ' . $year . ': <b>' . $balance['remaining'] . '</b> из ' . $balance['norm'];
        }

        $this->sendHtml($chatId, implode("\n", $lines), $this->menuKeyboard($userId));
    }

    // ---------------------------------------------------------------------
    // Текстовые шаги диалога
    // ---------------------------------------------------------------------

    /**
     * @param array<string, mixed> $session
     */
    private function handleSessionText(string $chatId, string $userId, array $session, string $text): bool
    {
        $step = (string)($session['step'] ?? '');

        if ($step === 'dates' || $step === 'edit_dates') {
            try {
                $ranges = $this->parseRanges($text);
            } catch (RuntimeException $e) {
                $this->sendHtml($chatId, '⚠️ ' . $this->esc($e->getMessage()));
                return true;
            }

            if ($step === 'edit_dates') {
                $id = (int)($session['target'] ?? 0);
                $this->clearSession($userId);
                if (count($ranges) !== 1) {
                    $this->sendHtml($chatId, '⚠️ Для переноса укажите один диапазон, например <code>01.07-14.07</code>.');
                    return true;
                }
                $result = $this->updateRecordDates($id, $userId, $ranges[0]);
                if (!$result['ok']) {
                    $this->sendHtml($chatId, $result['err'] === 'not_owner'
                        ? '⛔ Это не ваша запись.'
                        : '⚠️ Запись не найдена.');
                    return true;
                }
                $this->sendHtml(
                    $chatId,
                    '✏️ Запись #' . $id . ' перенесена: '
                    . $this->esc($this->formatInterval($ranges[0]['from'], $ranges[0]['to']))
                    . ' (' . $this->daysInRange($ranges[0]['from'], $ranges[0]['to']) . ' к.д.)'
                );
                return true;
            }

            // step 'dates'
            $session['ranges'] = $ranges;
            $session['step'] = 'confirm';
            $this->setSession($userId, $session);
            $this->sendAddConfirm($chatId, $userId, $session);
            return true;
        }

        if ($step === 'comment') {
            $session['comment'] = ($text === '-' ? '' : $text);
            $session['step'] = 'confirm';
            $this->setSession($userId, $session);
            $this->sendAddConfirm($chatId, $userId, $session);
            return true;
        }

        if ($step === 'finish_date') {
            $id = (int)($session['target'] ?? 0);
            $this->clearSession($userId);
            try {
                $ranges = $this->parseRanges($text);
            } catch (RuntimeException $e) {
                $this->sendHtml($chatId, '⚠️ ' . $this->esc($e->getMessage()));
                return true;
            }
            $date = $ranges[0]['from'];
            $result = $this->finishRecord($id, $userId, $date);
            if (!$result['ok']) {
                $msg = match ($result['err']) {
                    'not_owner' => '⛔ Это не ваша запись.',
                    'out_of_range' => '⚠️ Дата выхода должна быть внутри периода отсутствия.',
                    default => '⚠️ Запись не найдена.',
                };
                $this->sendHtml($chatId, $msg);
                return true;
            }
            $this->sendHtml($chatId, '🏁 Запись #' . $id . ' завершена ' . $this->esc($this->shortDate($date)) . '.');
            return true;
        }

        if ($step === 'admin_adduser') {
            if (!$this->isAdmin($userId)) {
                $this->clearSession($userId);
                return true;
            }
            if (!preg_match('/^\d+\s+.+$/u', trim($text))) {
                $this->sendHtml($chatId, 'Формат: <code>123456 Иван Петров</code>. Или нажмите /users.');
                return true;
            }
            $this->clearSession($userId);
            $this->cmdAddUser($chatId, $userId, $text);
            $this->cmdAdminUsers($chatId, $userId);
            return true;
        }

        return false;
    }

    // ---------------------------------------------------------------------
    // Команды: личные записи
    // ---------------------------------------------------------------------

    private function cmdMy(string $chatId, string $userId, string $arg): void
    {
        $year = $this->parseYear($arg) ?? (int)$this->now()->format('Y');
        $records = $this->recordsForUserYear($userId, $year);

        $lines = ['📋 <b>Мои отсутствия за ' . $year . '</b>', ''];
        if ($records === []) {
            $lines[] = 'За этот год записей нет.';
        } else {
            foreach ($records as $r) {
                $line = '#' . $r['id'] . ' · ' . $this->esc($this->typeLabel((string)$r['type']))
                    . ' · ' . $this->esc($this->formatInterval((string)$r['date_from'], $this->effectiveTo($r)))
                    . ' (' . $this->effectiveDays($r) . ' к.д.)';
                if (!empty($r['finished_at'])) {
                    $line .= ' <i>· завершён ' . $this->esc($this->shortDate((string)$r['finished_at'])) . '</i>';
                }
                $lines[] = $line;
                $comment = trim((string)($r['comment'] ?? ''));
                if ($comment !== '') {
                    $lines[] = '    💬 ' . $this->esc($comment);
                }
            }
        }

        $balance = $this->balanceFor($userId, $year);
        $lines[] = '';
        $lines[] = '🏖 Остаток отпуска: <b>' . $balance['remaining'] . '</b> из ' . $balance['norm']
            . ' (использовано ' . $balance['used'] . ')';

        // Кнопки управления по каждой записи (не больше 8, чтобы не перегружать).
        $rows = [];
        foreach (array_slice($records, 0, 8) as $r) {
            $id = (int)$r['id'];
            $rows[] = [
                self::btn('✏️ #' . $id, 'rec:edit:' . $id),
                self::btn('🏁 #' . $id, 'rec:finish:' . $id),
                self::btn('🗑 #' . $id, 'rec:cancel:' . $id),
            ];
        }
        $rows = array_merge($rows, $this->menuKeyboard($userId)['inline_keyboard']);

        $this->sendHtml($chatId, implode("\n", $lines), $this->inlineKeyboard($rows));
    }

    private function cmdLeft(string $chatId, string $userId, string $arg): void
    {
        $year = $this->parseYear($arg) ?? (int)$this->now()->format('Y');
        $balance = $this->balanceFor($userId, $year);

        $lines = ['🏖 <b>Остаток отпуска за ' . $year . '</b>', ''];
        $lines[] = 'Норма: <b>' . $balance['norm'] . '</b> к.д.'
            . ($balance['default_norm'] ? ' (по умолчанию)' : '');
        $lines[] = 'Использовано: <b>' . $balance['used'] . '</b> к.д.';
        $lines[] = 'Осталось: <b>' . $balance['remaining'] . '</b> к.д.';

        if ($balance['records'] !== []) {
            $lines[] = '';
            $lines[] = '<b>Списания (тип: ' . $this->esc(implode(', ', $this->quotaTypes)) . '):</b>';
            foreach ($balance['records'] as $r) {
                $lines[] = '• ' . $this->esc($this->formatInterval((string)$r['date_from'], $this->effectiveTo($r)))
                    . ' — ' . $this->effectiveDays($r) . ' дн.';
            }
        }

        $this->sendHtml($chatId, implode("\n", $lines), $this->menuKeyboard($userId));
    }

    private function cmdCancel(string $chatId, string $userId, string $arg): void
    {
        $id = (int)trim($arg);
        if ($id <= 0) {
            $this->sendHtml($chatId, 'Использование: <code>/cancel &lt;id&gt;</code>. Свой список: /my');
            return;
        }

        $result = $this->deleteRecord($id, $userId);
        if (!$result['ok']) {
            $this->sendHtml($chatId, $result['err'] === 'not_owner'
                ? '⛔ Можно отменять только свои записи.'
                : '⚠️ Запись не найдена.');
            return;
        }

        $this->sendHtml($chatId, '🗑 Запись #' . $id . ' отменена.');
    }

    private function cmdEdit(string $chatId, string $userId, string $arg): void
    {
        if (!preg_match('/^(\d+)\s+(.+)$/u', $arg, $m)) {
            $this->sendHtml(
                $chatId,
                "Использование: <code>/edit &lt;id&gt; &lt;даты&gt;</code>\n"
                . 'Например: <code>/edit 5 01.08-10.08</code>'
            );
            return;
        }

        $id = (int)$m[1];
        try {
            $ranges = $this->parseRanges($m[2]);
        } catch (RuntimeException $e) {
            $this->sendHtml($chatId, '⚠️ ' . $this->esc($e->getMessage()));
            return;
        }
        if (count($ranges) !== 1) {
            $this->sendHtml($chatId, '⚠️ Для переноса укажите один диапазон.');
            return;
        }

        $result = $this->updateRecordDates($id, $userId, $ranges[0]);
        if (!$result['ok']) {
            $this->sendHtml($chatId, $result['err'] === 'not_owner'
                ? '⛔ Это не ваша запись.'
                : '⚠️ Запись не найдена.');
            return;
        }

        $this->sendHtml(
            $chatId,
            '✏️ Запись #' . $id . ' перенесена: '
            . $this->esc($this->formatInterval($ranges[0]['from'], $ranges[0]['to']))
            . ' (' . $this->daysInRange($ranges[0]['from'], $ranges[0]['to']) . ' к.д.)'
        );
    }

    private function cmdFinish(string $chatId, string $userId, string $arg): void
    {
        $parts = preg_split('/\s+/', trim($arg)) ?: [];
        $id = (int)($parts[0] ?? 0);
        if ($id <= 0) {
            $this->sendHtml($chatId, 'Использование: <code>/finish &lt;id&gt; [дата|сегодня]</code>');
            return;
        }

        $dateInput = trim((string)($parts[1] ?? ''));
        if ($dateInput === '' || mb_strtolower($dateInput) === 'сегодня') {
            $date = $this->now()->format('Y-m-d');
        } else {
            try {
                $ranges = $this->parseRanges($dateInput);
            } catch (RuntimeException $e) {
                $this->sendHtml($chatId, '⚠️ ' . $this->esc($e->getMessage()));
                return;
            }
            $date = $ranges[0]['from'];
        }

        $result = $this->finishRecord($id, $userId, $date);
        if (!$result['ok']) {
            $msg = match ($result['err']) {
                'not_owner' => '⛔ Это не ваша запись.',
                'out_of_range' => '⚠️ Дата выхода должна быть внутри периода отсутствия.',
                default => '⚠️ Запись не найдена.',
            };
            $this->sendHtml($chatId, $msg);
            return;
        }

        $this->sendHtml($chatId, '🏁 Запись #' . $id . ' завершена ' . $this->esc($this->shortDate($date)) . '.');
    }

    // ---------------------------------------------------------------------
    // Команды: общая картина
    // ---------------------------------------------------------------------

    private function cmdToday(string $chatId, string $requesterId): void
    {
        $date = $this->now()->format('Y-m-d');
        $rows = $this->absentOn($date);

        $lines = ['📌 <b>Отсутствуют сегодня, ' . $this->esc($this->shortDate($date)) . '</b>', ''];
        if ($rows === []) {
            $lines[] = '✅ Все на месте.';
        } else {
            foreach ($rows as $row) {
                $lines[] = '• ' . $this->esc($row['name']) . ' — '
                    . $this->esc($this->formatInterval($row['from'], $row['to']));
            }
        }

        $this->sendHtml($chatId, implode("\n", $lines), $this->menuKeyboard($requesterId));
    }

    private function cmdWeek(string $chatId, string $requesterId): void
    {
        $now = $this->now();
        $dow = (int)$now->format('N');
        $monday = $now->modify('-' . ($dow - 1) . ' days')->setTime(0, 0);
        $dows = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

        $lines = ['📅 <b>Неделя ' . $this->esc($this->shortDate($monday->format('Y-m-d')))
            . ' – ' . $this->esc($this->shortDate($monday->modify('+6 days')->format('Y-m-d'))) . '</b>', ''];

        for ($i = 0; $i < 7; $i++) {
            $day = $monday->modify("+{$i} days");
            $ymd = $day->format('Y-m-d');
            $names = [];
            foreach ($this->absentOn($ymd) as $row) {
                $names[] = $row['name'];
            }
            $names = array_values(array_unique($names));
            sort($names, SORT_NATURAL | SORT_FLAG_CASE);
            $lines[] = $dows[$i] . ' ' . $day->format('d.m') . ': '
                . ($names === [] ? '—' : $this->esc(implode(', ', $names)));
        }

        $this->sendHtml($chatId, implode("\n", $lines), $this->menuKeyboard($requesterId));
    }

    private function cmdMonth(string $chatId, string $requesterId, string $arg): void
    {
        $arg = trim($arg);
        if ($arg !== '' && preg_match('/^(\d{1,2})\.(\d{4})$/', $arg, $m)) {
            $month = (int)$m[1];
            $year = (int)$m[2];
        } else {
            $now = $this->now();
            $month = (int)$now->format('n');
            $year = (int)$now->format('Y');
        }

        if ($month < 1 || $month > 12) {
            $this->sendHtml($chatId, '⚠️ Формат: <code>/month 07.2026</code>');
            return;
        }

        $this->sendHtml($chatId, $this->buildMonthTable($year, $month), $this->menuKeyboard($requesterId));
    }

    private function cmdSoon(string $chatId, string $requesterId, string $arg): void
    {
        $n = (int)trim($arg);
        if ($n <= 0) {
            $n = 14;
        }
        $n = min($n, 365);

        $from = $this->now()->format('Y-m-d');
        $to = $this->now()->modify("+{$n} days")->format('Y-m-d');

        $items = [];
        foreach ($this->loadRecords()['records'] as $r) {
            $start = (string)$r['date_from'];
            if ($start >= $from && $start <= $to) {
                $items[] = $r;
            }
        }
        usort($items, static fn(array $a, array $b): int => strcmp((string)$a['date_from'], (string)$b['date_from']));

        $lines = ['🔜 <b>Уходят в ближайшие ' . $n . ' дн.</b>', ''];
        if ($items === []) {
            $lines[] = 'Никто не планирует отсутствие в этом периоде.';
        } else {
            foreach ($items as $r) {
                $lines[] = '• ' . $this->esc($this->formatInterval((string)$r['date_from'], $this->effectiveTo($r)))
                    . ' — ' . $this->esc($this->displayNameForId((string)$r['user_id']));
            }
        }

        $this->sendHtml($chatId, implode("\n", $lines), $this->menuKeyboard($requesterId));
    }

    private function cmdUser(string $chatId, string $requesterId, string $arg): void
    {
        $arg = trim($arg);
        $year = null;
        // Последний токен-год: /user @user 2026
        if (preg_match('/^(.+?)\s+(\d{4})$/u', $arg, $m)) {
            $arg = trim($m[1]);
            $year = (int)$m[2];
        }
        if ($year === null) {
            $year = (int)$this->now()->format('Y');
        }

        $user = $this->resolveUser($arg);
        if ($user === null) {
            $this->sendHtml($chatId, 'Сотрудник не найден. Список: /users');
            return;
        }

        $targetId = (string)$user['id'];
        $isSelf = $targetId === $requesterId;
        $records = $this->recordsForUserYear($targetId, $year);

        $lines = ['👤 <b>' . $this->esc($this->displayName($user)) . ' · ' . $year . '</b>', ''];
        if ($records === []) {
            $lines[] = 'Записей нет.';
        } else {
            foreach ($records as $r) {
                $line = '• ' . $this->esc($this->formatInterval((string)$r['date_from'], $this->effectiveTo($r)))
                    . ' (' . $this->effectiveDays($r) . ' к.д.)';
                // Тип и комментарий показываем только самому сотруднику.
                if ($isSelf) {
                    $line = '#' . $r['id'] . ' · ' . $this->esc($this->typeLabel((string)$r['type'])) . ' ' . $line;
                }
                $lines[] = $line;
                if ($isSelf && trim((string)($r['comment'] ?? '')) !== '') {
                    $lines[] = '    💬 ' . $this->esc((string)$r['comment']);
                }
            }
        }

        // Остаток отпуска — только самому сотруднику.
        if ($isSelf) {
            $balance = $this->balanceFor($targetId, $year);
            $lines[] = '';
            $lines[] = '🏖 Остаток отпуска: <b>' . $balance['remaining'] . '</b> из ' . $balance['norm'];
        }

        $this->sendHtml($chatId, implode("\n", $lines), $this->usersKeyboard($requesterId));
    }

    private function cmdUsers(string $chatId, string $requesterId): void
    {
        $users = $this->loadUsers();
        if ($users === []) {
            $this->sendHtml($chatId, 'Список пуст.');
            return;
        }

        $items = array_values($users);
        usort($items, static fn(array $a, array $b): int => strcasecmp((string)$a['name'], (string)$b['name']));

        $lines = ['👥 <b>Сотрудники</b>', '', 'Выберите сотрудника, чтобы посмотреть записи за текущий год:'];
        foreach ($items as $u) {
            $active = !empty($u['active']) ? '' : ' <i>(отключён)</i>';
            $uname = trim((string)($u['username'] ?? ''));
            $lines[] = '• ' . $this->esc($this->displayName($u))
                . ($uname !== '' ? ' (@' . $this->esc($uname) . ')' : '')
                . ' <code>' . $this->esc((string)$u['id']) . '</code>' . $active;
        }

        $this->sendHtml($chatId, implode("\n", $lines), $this->usersKeyboard($requesterId));
    }

    private function cmdHelp(string $chatId, string $requesterId): void
    {
        $types = [];
        foreach ($this->absenceTypes as $key => $emoji) {
            $types[] = $emoji . ' ' . $this->esc($key);
        }

        $text = "<b>🏖 Бот учёта отсутствий</b>\n\n"
            . "Типы: " . implode(', ', $types) . ".\n"
            . 'Остаток отпуска считают типы: ' . $this->esc(implode(', ', $this->quotaTypes)) . ".\n\n"
            . "<b>Свои записи</b>\n"
            . "• /add — добавить отсутствие (кнопками)\n"
            . "• /my [год] — мои отсутствия\n"
            . "• /left — остаток отпуска в этом году\n"
            . "• /cancel &lt;id&gt; — отменить\n"
            . "• /edit &lt;id&gt; &lt;даты&gt; — перенести\n"
            . "• /finish &lt;id&gt; [дата|сегодня] — досрочно завершить\n\n"
            . "<b>Общая картина</b>\n"
            . "• /today — кто отсутствует сегодня\n"
            . "• /week — кто отсутствует на этой неделе\n"
            . "• /month [ММ.ГГГГ] — календарь на месяц\n"
            . "• /soon [N] — кто уходит в ближайшие N дней\n"
            . "• /user &lt;@username|имя|id&gt; [год] — отсутствия сотрудника\n"
            . "• /users — список сотрудников (кнопкой выбрать и посмотреть текущий год)\n\n"
            . "Даты: <code>01.07.2026-14.07.2026</code>, <code>01.07-14.07</code>, <code>01.07</code> или <code>01.07 +14</code> (старт и число дней).";

        if ($this->isAdmin($requesterId)) {
            $text .= "\n\n<b>Админ</b>\n"
                . "• /adduser &lt;tg_id&gt; &lt;Имя&gt; — добавить сотрудника\n"
                . "• /deluser &lt;tg_id&gt; — отключить доступ\n"
                . "• кнопка «⚙️ Админ» в меню — список с включением/отключением";
        }

        $this->sendHtml($chatId, $text, $this->menuKeyboard($requesterId));
    }

    // ---------------------------------------------------------------------
    // Команды: админ
    // ---------------------------------------------------------------------

    private function cmdAddUser(string $chatId, string $requesterId, string $arg): void
    {
        if (!$this->isAdmin($requesterId)) {
            $this->sendHtml($chatId, '⛔ Команда только для администратора.');
            return;
        }

        if (!preg_match('/^(\d+)\s+(.+)$/u', trim($arg), $m)) {
            $this->sendHtml(
                $chatId,
                "Использование: <code>/adduser &lt;tg_id&gt; &lt;Имя&gt;</code>\n"
                . 'Сотрудник узнаёт свой ID командой /id.'
            );
            return;
        }

        $id = (string)(int)$m[1];
        $name = trim($m[2]);

        $this->mutateUsers(function (array &$users) use ($id, $name): void {
            $existing = $users[$id] ?? [];
            $users[$id] = [
                'id'          => $id,
                'name'        => $name,
                'username'    => (string)($existing['username'] ?? ''),
                'role'        => $id === $this->adminId ? 'admin' : 'user',
                'annual_days' => $existing['annual_days'] ?? null,
                'active'      => true,
                'created_at'  => (string)($existing['created_at'] ?? date('c')),
            ];
        });

        $this->sendHtml($chatId, '✅ Добавлен: ' . $this->esc($name) . ' <code>' . $this->esc($id) . '</code>');
    }

    private function cmdDelUser(string $chatId, string $requesterId, string $arg): void
    {
        if (!$this->isAdmin($requesterId)) {
            $this->sendHtml($chatId, '⛔ Команда только для администратора.');
            return;
        }

        $id = (string)(int)trim($arg);
        if ($id === '' || $id === '0') {
            $this->sendHtml($chatId, 'Использование: <code>/deluser &lt;tg_id&gt;</code>');
            return;
        }

        $found = false;
        $this->mutateUsers(function (array &$users) use ($id, &$found): void {
            if (isset($users[$id])) {
                $users[$id]['active'] = false;
                $found = true;
            }
        });

        $this->sendHtml($chatId, $found
            ? '🚫 Доступ отключён для <code>' . $this->esc($id) . '</code>. Историю записей сохранил.'
            : '⚠️ Пользователь не найден.');
    }

    /**
     * Раздел админа, доступный по inline-кнопке «⚙️ Админ».
     *
     * @param string[] $parts
     */
    private function adminAction(string $chatId, string $userId, array $parts): void
    {
        if (!$this->isAdmin($userId)) {
            $this->sendHtml($chatId, '⛔ Раздел только для администратора.');
            return;
        }

        switch ((string)($parts[1] ?? '')) {
            case 'add':
                $this->setSession($userId, ['step' => 'admin_adduser']);
                $this->sendHtml(
                    $chatId,
                    "➕ <b>Новый сотрудник</b>\n\nОтправьте одним сообщением:\n"
                    . '<code>&lt;tg_id&gt; Имя Фамилия</code>' . "\n\n"
                    . 'Сотрудник узнаёт свой ID командой /id.',
                    $this->inlineKeyboard([[self::btn('❌ Отмена', 'adm:list')]])
                );
                return;
            case 'toggle':
                $this->cmdAdminToggle($chatId, $userId, (string)($parts[2] ?? ''));
                return;
            case 'list':
            default:
                $this->clearSession($userId);
                $this->cmdAdminUsers($chatId, $userId);
                return;
        }
    }

    private function cmdAdminUsers(string $chatId, string $requesterId): void
    {
        if (!$this->isAdmin($requesterId)) {
            $this->sendHtml($chatId, '⛔ Раздел только для администратора.');
            return;
        }

        $items = array_values($this->loadUsers());
        usort($items, static fn(array $a, array $b): int => strcasecmp((string)$a['name'], (string)$b['name']));

        $rows = [];
        $lines = ['⚙️ <b>Админ: сотрудники</b>', '', 'Нажмите на сотрудника, чтобы включить/отключить доступ:'];
        foreach ($items as $u) {
            $id = (string)$u['id'];
            $active = !empty($u['active']);
            $mark = $active ? '✅' : '🚫';
            $lines[] = $mark . ' ' . $this->esc($this->displayName($u))
                . ' <code>' . $this->esc($id) . '</code>'
                . ($id === $this->adminId ? ' <i>(админ)</i>' : '');
            $rows[] = [self::btn($mark . ' ' . $this->displayName($u), 'adm:toggle:' . $id)];
        }
        $rows[] = [self::btn('➕ Добавить сотрудника', 'adm:add')];
        $rows[] = [self::btn('🔄 Обновить', 'adm:list')];

        $this->sendHtml($chatId, implode("\n", $lines), $this->inlineKeyboard($rows));
    }

    private function cmdAdminToggle(string $chatId, string $requesterId, string $targetId): void
    {
        if (!$this->isAdmin($requesterId)) {
            $this->sendHtml($chatId, '⛔ Раздел только для администратора.');
            return;
        }

        $targetId = (string)(int)$targetId;
        if ($targetId === '' || $targetId === '0') {
            $this->cmdAdminUsers($chatId, $requesterId);
            return;
        }

        $found = false;
        $nowActive = false;
        $this->mutateUsers(function (array &$users) use ($targetId, &$found, &$nowActive): void {
            if (isset($users[$targetId])) {
                $nowActive = empty($users[$targetId]['active']);
                $users[$targetId]['active'] = $nowActive;
                $found = true;
            }
        });

        if (!$found) {
            $this->sendHtml($chatId, '⚠️ Пользователь не найден.');
            return;
        }

        $this->sendHtml($chatId, ($nowActive ? '✅ Доступ включён: ' : '🚫 Доступ отключён: ')
            . '<code>' . $this->esc($targetId) . '</code>');
        $this->cmdAdminUsers($chatId, $requesterId);
    }

    // ---------------------------------------------------------------------
    // Хранилище: пользователи
    // ---------------------------------------------------------------------

    private function usersFile(): string
    {
        return __DIR__ . '/logs/vacations_users.json';
    }

    private function recordsFile(): string
    {
        return __DIR__ . '/logs/vacations_records.json';
    }

    private function sessionsFile(): string
    {
        return __DIR__ . '/logs/vacations_sessions.json';
    }

    /**
     * Сидирует белый список из glopro_users.json при первом запуске
     * и гарантирует наличие админа.
     */
    private function ensureSeedUsers(): void
    {
        $users = $this->readJson($this->usersFile(), []);

        if ($users === []) {
            $seed = $this->readJson(__DIR__ . '/logs/glopro_users.json', []);
            foreach ($seed as $id => $u) {
                if (!is_array($u)) {
                    continue;
                }
                $id = (string)$id;
                $users[$id] = $this->makeUser($id, (string)($u['name'] ?? ''), (string)($u['username'] ?? ''));
            }
            if ($users !== []) {
                $this->log('seed users from glopro_users.json: ' . count($users));
            }
        }

        if ($this->adminId !== '' && !isset($users[$this->adminId])) {
            $users[$this->adminId] = $this->makeUser($this->adminId, 'Администратор', '');
            $this->log('admin bootstrap: ' . $this->adminId);
        } elseif ($this->adminId !== '' && ($users[$this->adminId]['role'] ?? '') !== 'admin') {
            $users[$this->adminId]['role'] = 'admin';
        }

        if ($users !== []) {
            $this->writeJson($this->usersFile(), $users);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function makeUser(string $id, string $name, string $username): array
    {
        return [
            'id'          => $id,
            'name'        => $name !== '' ? $name : ('ID ' . $id),
            'username'    => $username,
            'role'        => $id === $this->adminId ? 'admin' : 'user',
            'annual_days' => null,
            'active'      => true,
            'created_at'  => date('c'),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadUsers(): array
    {
        $users = $this->readJson($this->usersFile(), []);
        return is_array($users) ? $users : [];
    }

    /**
     * @param callable(array<string, mixed>&): mixed $fn
     */
    private function mutateUsers(callable $fn): mixed
    {
        return $this->withLock('vacations_users', function () use ($fn) {
            $users = $this->loadUsers();
            $result = $fn($users);
            $this->writeJson($this->usersFile(), $users);
            return $result;
        });
    }

    private function findUser(string $id): ?array
    {
        $users = $this->loadUsers();
        return isset($users[$id]) && is_array($users[$id]) ? $users[$id] : null;
    }

    private function isAllowed(string $id): bool
    {
        if ($this->isAdmin($id)) {
            return true;
        }
        $user = $this->findUser($id);
        return $user !== null && !empty($user['active']);
    }

    private function isAdmin(string $id): bool
    {
        return $this->adminId !== '' && $id === $this->adminId;
    }

    /**
     * Обновляет имя/username пользователя, если они изменились.
     *
     * @param array<string, mixed> $from
     */
    private function touchUser(string $id, array $from): void
    {
        $name = trim((string)($from['first_name'] ?? ''));
        if (isset($from['last_name'])) {
            $name = trim($name . ' ' . (string)$from['last_name']);
        }
        $username = (string)($from['username'] ?? '');

        $user = $this->findUser($id);
        if ($user === null) {
            return;
        }
        if (($user['name'] ?? '') === $name && ($user['username'] ?? '') === $username) {
            return;
        }

        $this->mutateUsers(function (array &$users) use ($id, $name, $username): void {
            if (isset($users[$id])) {
                if ($name !== '') {
                    $users[$id]['name'] = $name;
                }
                $users[$id]['username'] = $username;
            }
        });
    }

    // ---------------------------------------------------------------------
    // Хранилище: записи
    // ---------------------------------------------------------------------

    /**
     * @return array{next_id: int, records: array<int, array<string, mixed>>}
     */
    private function loadRecords(): array
    {
        $data = $this->readJson($this->recordsFile(), []);
        $records = is_array($data['records'] ?? null) ? array_values($data['records']) : [];
        $nextId = isset($data['next_id']) ? (int)$data['next_id'] : 1;
        if ($nextId < 1) {
            $nextId = 1;
        }
        $this->sortRecords($records);
        return ['next_id' => $nextId, 'records' => $records];
    }

    /**
     * Сортирует записи по дате начала, затем по id.
     *
     * @param array<int, array<string, mixed>> $records
     */
    private function sortRecords(array &$records): void
    {
        usort($records, static function (array $a, array $b): int {
            $cmp = strcmp((string)($a['date_from'] ?? ''), (string)($b['date_from'] ?? ''));
            return $cmp !== 0 ? $cmp : ((int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0));
        });
    }

    /**
     * @param callable(array{next_id: int, records: array<int, array<string, mixed>>}&): mixed $fn
     */
    private function mutateRecords(callable $fn): mixed
    {
        return $this->withLock('vacations_records', function () use ($fn) {
            $data = $this->loadRecords();
            $result = $fn($data);
            $this->sortRecords($data['records']);
            $this->writeJson($this->recordsFile(), $data);
            return $result;
        });
    }

    private function findRecord(int $id): ?array
    {
        foreach ($this->loadRecords()['records'] as $r) {
            if ((int)$r['id'] === $id) {
                return $r;
            }
        }
        return null;
    }

    /**
     * @param array<int, array{from: string, to: string}> $ranges
     * @return array<int, array<string, mixed>> созданные записи
     */
    private function addRecords(string $userId, string $type, array $ranges, string $comment): array
    {
        $now = date('c');
        return $this->mutateRecords(function (array &$data) use ($userId, $type, $ranges, $comment, $now): array {
            $created = [];
            foreach ($ranges as $range) {
                $record = [
                    'id'          => $data['next_id']++,
                    'user_id'     => $userId,
                    'type'        => $type,
                    'date_from'   => $range['from'],
                    'date_to'     => $range['to'],
                    'days'        => $this->daysInRange($range['from'], $range['to']),
                    'comment'     => $comment,
                    'finished_at' => null,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
                $data['records'][] = $record;
                $created[] = $record;
            }
            $this->sortRecords($created);
            return $created;
        });
    }

    /**
     * @return array{ok: bool, err?: string, rec?: array<string, mixed>}
     */
    private function deleteRecord(int $id, string $requesterId): array
    {
        return $this->mutateRecords(function (array &$data) use ($id, $requesterId): array {
            foreach ($data['records'] as $i => $r) {
                if ((int)$r['id'] === $id) {
                    if ((string)$r['user_id'] !== $requesterId && !$this->isAdmin($requesterId)) {
                        return ['ok' => false, 'err' => 'not_owner'];
                    }
                    array_splice($data['records'], $i, 1);
                    return ['ok' => true, 'rec' => $r];
                }
            }
            return ['ok' => false, 'err' => 'not_found'];
        });
    }

    /**
     * @param array{from: string, to: string} $range
     * @return array{ok: bool, err?: string}
     */
    private function updateRecordDates(int $id, string $requesterId, array $range): array
    {
        return $this->mutateRecords(function (array &$data) use ($id, $requesterId, $range): array {
            foreach ($data['records'] as $i => $r) {
                if ((int)$r['id'] !== $id) {
                    continue;
                }
                if ((string)$r['user_id'] !== $requesterId && !$this->isAdmin($requesterId)) {
                    return ['ok' => false, 'err' => 'not_owner'];
                }
                $data['records'][$i]['date_from'] = $range['from'];
                $data['records'][$i]['date_to'] = $range['to'];
                $data['records'][$i]['days'] = $this->daysInRange($range['from'], $range['to']);
                // Если запись была завершена раньше — перенос сбрасывает завершение.
                $data['records'][$i]['finished_at'] = null;
                $data['records'][$i]['updated_at'] = date('c');
                return ['ok' => true];
            }
            return ['ok' => false, 'err' => 'not_found'];
        });
    }

    /**
     * @return array{ok: bool, err?: string}
     */
    private function finishRecord(int $id, string $requesterId, string $date): array
    {
        return $this->mutateRecords(function (array &$data) use ($id, $requesterId, $date): array {
            foreach ($data['records'] as $i => $r) {
                if ((int)$r['id'] !== $id) {
                    continue;
                }
                if ((string)$r['user_id'] !== $requesterId && !$this->isAdmin($requesterId)) {
                    return ['ok' => false, 'err' => 'not_owner'];
                }
                if ($date < (string)$r['date_from'] || $date > (string)$r['date_to']) {
                    return ['ok' => false, 'err' => 'out_of_range'];
                }
                $data['records'][$i]['finished_at'] = $date;
                $data['records'][$i]['updated_at'] = date('c');
                return ['ok' => true];
            }
            return ['ok' => false, 'err' => 'not_found'];
        });
    }

    /**
     * Записи сотрудника, пересекающиеся с указанным годом.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recordsForUserYear(string $userId, int $year): array
    {
        $records = [];
        foreach ($this->loadRecords()['records'] as $r) {
            if ((string)$r['user_id'] !== $userId) {
                continue;
            }
            if ($this->daysInYear($r, $year) <= 0) {
                continue;
            }
            $records[] = $r;
        }
        usort($records, static fn(array $a, array $b): int => strcmp((string)$a['date_from'], (string)$b['date_from']));
        return $records;
    }

    /**
     * @return array<int, array{name: string, from: string, to: string}>
     */
    private function absentOn(string $date): array
    {
        $rows = [];
        foreach ($this->loadRecords()['records'] as $r) {
            if (!$this->isActiveOn($r, $date)) {
                continue;
            }
            $rows[] = [
                'name' => $this->displayNameForId((string)$r['user_id']),
                'from' => (string)$r['date_from'],
                'to'   => $this->effectiveTo($r),
            ];
        }
        usort($rows, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        return $rows;
    }

    /**
     * Пересечения с другими записями (информативно).
     *
     * @param array<int, array{from: string, to: string}> $ranges
     * @return array<int, array{name: string, interval: string, own: bool}>
     */
    private function findOverlaps(string $userId, array $ranges): array
    {
        $result = [];
        foreach ($this->loadRecords()['records'] as $r) {
            $rid = (string)$r['user_id'];
            if ($rid === $userId) {
                continue;
            }
            foreach ($ranges as $range) {
                if ($this->overlapsRange($r, $range['from'], $range['to'])) {
                    $result[] = [
                        'name'     => $this->displayNameForId($rid),
                        'interval' => $this->formatInterval((string)$r['date_from'], $this->effectiveTo($r)),
                        'own'      => false,
                    ];
                    break;
                }
            }
        }

        // Пересечения с собственными записями.
        foreach ($this->loadRecords()['records'] as $r) {
            if ((string)$r['user_id'] !== $userId) {
                continue;
            }
            foreach ($ranges as $range) {
                if ($this->overlapsRange($r, $range['from'], $range['to'])) {
                    $result[] = [
                        'name'     => 'вы',
                        'interval' => $this->formatInterval((string)$r['date_from'], $this->effectiveTo($r)),
                        'own'      => true,
                    ];
                    break;
                }
            }
        }

        return $result;
    }

    // ---------------------------------------------------------------------
    // Остаток отпуска
    // ---------------------------------------------------------------------

    /**
     * @return array{norm: int, used: int, remaining: int, default_norm: bool, records: array<int, array<string, mixed>>}
     */
    private function balanceFor(string $userId, int $year): array
    {
        $user = $this->findUser($userId);
        $annual = $user['annual_days'] ?? null;
        $defaultNorm = true;
        if (is_numeric($annual) && (int)$annual > 0) {
            $norm = (int)$annual;
            $defaultNorm = false;
        } else {
            $norm = $this->defaultAnnualDays;
        }

        $used = 0;
        $records = [];
        foreach ($this->loadRecords()['records'] as $r) {
            if ((string)$r['user_id'] !== $userId) {
                continue;
            }
            if (!in_array((string)$r['type'], $this->quotaTypes, true)) {
                continue;
            }
            $days = $this->daysInYear($r, $year);
            if ($days <= 0) {
                continue;
            }
            $used += $days;
            $records[] = $r;
        }

        usort($records, static fn(array $a, array $b): int => strcmp((string)$a['date_from'], (string)$b['date_from']));

        return [
            'norm'         => $norm,
            'used'         => $used,
            'remaining'    => $norm - $used,
            'default_norm' => $defaultNorm,
            'records'      => $records,
        ];
    }

    // ---------------------------------------------------------------------
    // Месячная таблица
    // ---------------------------------------------------------------------

    private function buildMonthTable(int $year, int $month): string
    {
        $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $this->timezone());
        $days = (int)$first->format('t');
        $monthEnd = sprintf('%04d-%02d-%02d', $year, $month, $days);
        $monthStart = sprintf('%04d-%02d-01', $year, $month);

        /** @var array<string, array<int, bool>> $marks */
        $marks = [];
        foreach ($this->loadRecords()['records'] as $r) {
            if ((string)$r['date_from'] > $monthEnd || $this->effectiveTo($r) < $monthStart) {
                continue;
            }
            $uid = (string)$r['user_id'];
            for ($d = 1; $d <= $days; $d++) {
                $ymd = sprintf('%04d-%02d-%02d', $year, $month, $d);
                if ($this->isActiveOn($r, $ymd)) {
                    $marks[$uid][$d] = true;
                }
            }
        }

        $monthNames = [1 => 'январь', 'февраль', 'март', 'апрель', 'май', 'июнь',
            'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];
        $title = '🗓 <b>' . $this->ucfirst($monthNames[$month]) . ' ' . $year . '</b>';

        if ($marks === []) {
            return $title . "\n\nЗа этот месяц отсутствий нет.";
        }

        $users = $this->loadUsers();
        uksort($marks, function (string $a, string $b) use ($users): int {
            return strcasecmp($this->userNameFrom($users, $a), $this->userNameFrom($users, $b));
        });

        $html = [$title, '', '<table bordered>'];
        $header = '<tr><th>Сотрудник</th>';
        for ($d = 1; $d <= $days; $d++) {
            $header .= '<th>' . $d . '</th>';
        }
        $header .= '</tr>';
        $html[] = $header;

        foreach ($marks as $uid => $dayMarks) {
            $name = $this->userNameFrom($users, (string)$uid);
            $row = '<tr><td>' . $this->esc($name) . '</td>';
            for ($d = 1; $d <= $days; $d++) {
                $row .= '<td>' . (isset($dayMarks[$d]) ? '•' : '') . '</td>';
            }
            $row .= '</tr>';
            $html[] = $row;
        }
        $html[] = '</table>';
        $html[] = '';
        $html[] = '<i>• — сотрудник отсутствует (тип не раскрывается).</i>';

        return implode("\n", $html);
    }

    /**
     * @param array<string, array<string, mixed>> $users
     */
    private function userNameFrom(array $users, string $id): string
    {
        if (isset($users[$id]) && is_array($users[$id])) {
            return $this->displayName($users[$id]);
        }
        return 'ID ' . $id;
    }

    // ---------------------------------------------------------------------
    // Даты и утилиты
    // ---------------------------------------------------------------------

    /**
     * Разбирает строку с датами в список диапазонов.
     *
     * @return array<int, array{from: string, to: string}>
     * @throws RuntimeException
     */
    private function parseRanges(string $input): array
    {
        $input = str_replace(['—', '–'], '-', trim($input));

        // Поддержка «сегодня»/«завтра» одиночным значением.
        $lower = mb_strtolower($input);
        if ($lower === 'сегодня' || $lower === 'завтра') {
            $day = $lower === 'сегодня' ? $this->now() : $this->now()->modify('+1 day');
            $ymd = $day->format('Y-m-d');
            return [['from' => $ymd, 'to' => $ymd]];
        }

        $parts = preg_split('/[,;\n]+/', $input) ?: [];
        $currentYear = (int)$this->now()->format('Y');
        $ranges = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $part = (string)preg_replace('/\s*-\s*/', '-', $part);

            if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})-(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $part, $m)) {
                $from = $this->mkDate((int)$m[3], (int)$m[2], (int)$m[1]);
                $to = $this->mkDate((int)$m[6], (int)$m[5], (int)$m[4]);
            } elseif (preg_match('/^(\d{1,2})\.(\d{1,2})-(\d{1,2})\.(\d{1,2})$/', $part, $m)) {
                $from = $this->mkDate($currentYear, (int)$m[2], (int)$m[1]);
                $to = $this->mkDate($currentYear, (int)$m[4], (int)$m[3]);
            } elseif (preg_match('/^(\d{1,2})\.(\d{1,2})(?:\.(\d{4}))?\s*\+\s*(\d{1,3})$/u', $part, $m)) {
                // Дата старта + количество дней: "01.07 +14".
                $year = !empty($m[3]) ? (int)$m[3] : $currentYear;
                $count = (int)$m[4];
                if ($count < 1) {
                    throw new RuntimeException('Количество дней должно быть больше нуля: «' . $part . '».');
                }
                $from = $this->mkDate($year, (int)$m[2], (int)$m[1]);
                $to = $from->modify('+' . ($count - 1) . ' days');
            } elseif (preg_match('/^(\d{1,2})\.(\d{1,2})(?:\.(\d{4}))?\s+(\d{1,3})\s*(?:д(?:н(?:ей|я|и)?)?|день|дня|days?|day)?$/ui', $part, $m)) {
                // Дата старта + количество дней через пробел: "01.07 14", "01.07 14 дней".
                $year = !empty($m[3]) ? (int)$m[3] : $currentYear;
                $count = (int)$m[4];
                if ($count < 1) {
                    throw new RuntimeException('Количество дней должно быть больше нуля: «' . $part . '».');
                }
                $from = $this->mkDate($year, (int)$m[2], (int)$m[1]);
                $to = $from->modify('+' . ($count - 1) . ' days');
            } elseif (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $part, $m)) {
                $from = $to = $this->mkDate((int)$m[3], (int)$m[2], (int)$m[1]);
            } elseif (preg_match('/^(\d{1,2})\.(\d{1,2})$/', $part, $m)) {
                $from = $to = $this->mkDate($currentYear, (int)$m[2], (int)$m[1]);
            } else {
                throw new RuntimeException(
                    'Не понял даты: «' . $part . '». Формат: 01.07.2026-14.07.2026 или 01.07-14.07.'
                );
            }

            if ($to < $from) {
                throw new RuntimeException('Дата начала позже даты окончания: «' . $part . '».');
            }

            $ranges[] = ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')];
        }

        if ($ranges === []) {
            throw new RuntimeException('Не указаны даты.');
        }

        return $ranges;
    }

    private function mkDate(int $year, int $month, int $day): DateTimeImmutable
    {
        if (!checkdate($month, $day, $year)) {
            throw new RuntimeException("Некорректная дата: {$day}.{$month}.{$year}");
        }
        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), $this->timezone());
    }

    private function daysInRange(string $from, string $to): int
    {
        $a = new DateTimeImmutable($from, $this->timezone());
        $b = new DateTimeImmutable($to, $this->timezone());
        return (int)$a->diff($b)->days + 1;
    }

    /**
     * @param array<string, mixed> $r
     */
    private function effectiveTo(array $r): string
    {
        $end = (string)$r['date_to'];
        $finished = (string)($r['finished_at'] ?? '');
        if ($finished !== '' && $finished < $end) {
            return $finished;
        }
        return $end;
    }

    /**
     * @param array<string, mixed> $r
     */
    private function effectiveDays(array $r): int
    {
        return $this->daysInRange((string)$r['date_from'], $this->effectiveTo($r));
    }

    /**
     * @param array<string, mixed> $r
     */
    private function isActiveOn(array $r, string $date): bool
    {
        return (string)$r['date_from'] <= $date && $date <= $this->effectiveTo($r);
    }

    /**
     * @param array<string, mixed> $r
     */
    private function overlapsRange(array $r, string $from, string $to): bool
    {
        return (string)$r['date_from'] <= $to && $this->effectiveTo($r) >= $from;
    }

    /**
     * Число дней записи, попадающих в указанный год (с учётом перехода через год).
     *
     * @param array<string, mixed> $r
     */
    private function daysInYear(array $r, int $year): int
    {
        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);

        $from = max((string)$r['date_from'], $yearStart);
        $to = min($this->effectiveTo($r), $yearEnd);

        if ($from > $to) {
            return 0;
        }
        return $this->daysInRange($from, $to);
    }

    private function formatInterval(string $from, string $to): string
    {
        if ($from === $to) {
            return $this->shortDate($from);
        }
        // В пределах одного месяца — общий месяц.
        if (substr($from, 0, 7) === substr($to, 0, 7)) {
            return substr($from, 8, 2) . '–' . $this->shortDate($to);
        }
        return $this->shortDate($from) . ' – ' . $this->shortDate($to);
    }

    private function shortDate(string $ymd, bool $withYear = true): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
            return $ymd;
        }
        return $withYear
            ? $m[3] . '.' . $m[2] . '.' . $m[1]
            : $m[3] . '.' . $m[2];
    }

    private function parseYear(string $arg): ?int
    {
        $arg = trim($arg);
        return preg_match('/^\d{4}$/', $arg) ? (int)$arg : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveUser(string $query): ?array
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        $users = $this->loadUsers();
        if (isset($users[$query]) && is_array($users[$query])) {
            return $users[$query];
        }

        $needle = mb_strtolower(ltrim($query, '@'));

        // Сначала точное совпадение по username.
        foreach ($users as $u) {
            if (is_array($u) && mb_strtolower((string)($u['username'] ?? '')) === $needle) {
                return $u;
            }
        }
        // Затем вхождение в имя.
        foreach ($users as $u) {
            if (is_array($u) && mb_strpos(mb_strtolower((string)($u['name'] ?? '')), $needle) !== false) {
                return $u;
            }
        }

        return null;
    }

    private function displayName(array $user): string
    {
        $name = trim((string)($user['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $username = trim((string)($user['username'] ?? ''));
        return $username !== '' ? '@' . $username : (string)($user['id'] ?? '—');
    }

    private function displayNameForId(string $id): string
    {
        $user = $this->findUser($id);
        return $user !== null ? $this->displayName($user) : ('ID ' . $id);
    }

    private function typeLabel(string $type): string
    {
        $emoji = $this->absenceTypes[$type] ?? '•';
        return $emoji . ' ' . $type;
    }

    private function timezone(): DateTimeZone
    {
        if ($this->timezone === null) {
            $this->timezone = new DateTimeZone($this->tz);
        }
        return $this->timezone;
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone());
    }

    private function parseTypes(): void
    {
        $raw = trim((string)getenv('VACATION_TYPES'));
        if ($raw === '') {
            $raw = 'отпуск:🏖,отгул:🌴,больничный:🤒,учёба:🎓,прочее:📌';
        }

        $types = [];
        foreach (explode(',', $raw) as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }
            $kv = explode(':', $pair, 2);
            $key = mb_strtolower(trim((string)($kv[0] ?? '')));
            if ($key === '') {
                continue;
            }
            $types[$key] = trim((string)($kv[1] ?? '•'));
        }
        $this->absenceTypes = $types !== [] ? $types : ['отпуск' => '🏖'];

        $rawQuota = trim((string)getenv('VACATION_QUOTA_TYPES'));
        if ($rawQuota === '') {
            $rawQuota = 'отпуск';
        }
        $quota = [];
        foreach (explode(',', $rawQuota) as $q) {
            $q = mb_strtolower(trim($q));
            if ($q !== '' && isset($this->absenceTypes[$q])) {
                $quota[] = $q;
            }
        }
        $this->quotaTypes = $quota !== [] ? $quota : ['отпуск'];
    }

    private function ucfirst(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }

    // ---------------------------------------------------------------------
    // Сессии диалога
    // ---------------------------------------------------------------------

    /**
     * @return array<string, mixed>|null
     */
    private function getSession(string $userId): ?array
    {
        $sessions = $this->readSessions();
        $session = $sessions[$userId] ?? null;
        if (!is_array($session)) {
            return null;
        }
        if (($session['ts'] ?? 0) < time() - self::SESSION_TTL) {
            $this->clearSession($userId);
            return null;
        }
        return $session;
    }

    /**
     * Сохраняет шаг диалога, сохраняя уже введённые поля.
     *
     * @param array<string, mixed> $patch
     */
    private function setSession(string $userId, array $patch): void
    {
        $this->withLock('vacations_sessions', function () use ($userId, $patch): void {
            $sessions = $this->readSessions();
            $current = is_array($sessions[$userId] ?? null) ? $sessions[$userId] : [];
            $sessions[$userId] = array_merge($current, $patch, ['ts' => time()]);
            $this->writeJson($this->sessionsFile(), $sessions);
        });
    }

    private function clearSession(string $userId): void
    {
        $this->withLock('vacations_sessions', function () use ($userId): void {
            $sessions = $this->readSessions();
            if (isset($sessions[$userId])) {
                unset($sessions[$userId]);
                $this->writeJson($this->sessionsFile(), $sessions);
            }
        });
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readSessions(): array
    {
        $sessions = $this->readJson($this->sessionsFile(), []);
        if (!is_array($sessions)) {
            return [];
        }
        // Заодно чистим просроченные.
        $now = time();
        foreach ($sessions as $id => $s) {
            if (!is_array($s) || ($s['ts'] ?? 0) < $now - self::SESSION_TTL) {
                unset($sessions[$id]);
            }
        }
        return $sessions;
    }

    // ---------------------------------------------------------------------
    // Telegram API
    // ---------------------------------------------------------------------

    /**
     * Отправляет сообщение: rich message (таблицы) либо обычный HTML с клавиатурой.
     */
    private function sendHtml(int|string $chatId, string $html, ?array $keyboard = null): bool
    {
        if ($keyboard !== null || mb_strlen($html) > self::RICH_MAX) {
            return $this->sendMessageHtml($chatId, $html, $keyboard);
        }
        if ($this->sendRichMessage($chatId, $html)) {
            return true;
        }
        return $this->sendMessageHtml($chatId, $this->richToSendMessageHtml($html), null);
    }

    private function sendMessageHtml(int|string $chatId, string $text, ?array $keyboard = null): bool
    {
        $chunks = $this->splitMessage($text);
        $last = count($chunks) - 1;
        $allOk = true;

        foreach ($chunks as $i => $chunk) {
            $markup = ($keyboard !== null && $i === $last) ? $keyboard : null;
            $payload = [
                'chat_id'                  => $chatId,
                'text'                     => $chunk,
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => true,
            ];
            if ($markup !== null) {
                $payload['reply_markup'] = $markup;
            }

            if ($this->api('sendMessage', $payload) !== null) {
                continue;
            }

            // Фолбэк: отправить как plain text (кривой HTML-тег и т.п.).
            $plain = [
                'chat_id' => $chatId,
                'text'    => html_entity_decode(strip_tags($chunk), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            ];
            if ($markup !== null) {
                $plain['reply_markup'] = $markup;
            }
            if ($this->api('sendMessage', $plain) === null) {
                $allOk = false;
            }
        }

        return $allOk;
    }

    private function sendRichMessage(int|string $chatId, string $html): bool
    {
        $payload = [
            'chat_id'      => $chatId,
            'rich_message' => json_encode(
                ['html' => str_replace("\n", '<br>', $html)],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
        ];
        return $this->api('sendRichMessage', $payload, true) !== null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function api(string $method, array $payload, bool $raw = false): ?array
    {
        $ch = curl_init('https://api.telegram.org/bot' . $this->tgToken . '/' . $method);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $raw ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        if (!$raw) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($response === false || $error !== '') {
            $this->logError("cURL {$method}: {$error}");
            return null;
        }
        if ($httpCode >= 400) {
            $this->logError("{$method} HTTP {$httpCode}: " . substr((string)$response, 0, 300));
            return null;
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
            $this->logError("{$method} bad response: " . substr((string)$response, 0, 300));
            return null;
        }
        return $decoded;
    }

    private function answerCallback(string $callbackId): void
    {
        if ($callbackId === '') {
            return;
        }
        $this->api('answerCallbackQuery', ['callback_query_id' => $callbackId]);
    }

    /**
     * @return array{inline_keyboard: array<int, array<int, array<string, string>>>}
     */
    private function inlineKeyboard(array $rows): array
    {
        return ['inline_keyboard' => $rows];
    }

    private function menuKeyboard(?string $requesterId = null): array
    {
        $rows = [
            [self::btn('➕ Добавить', 'menu:add'), self::btn('📋 Мои', 'menu:my')],
            [self::btn('🏖 Остаток', 'menu:left'), self::btn('📌 Сегодня', 'menu:today')],
            [self::btn('📅 Неделя', 'menu:week'), self::btn('🗓 Месяц', 'menu:month')],
            [self::btn('🔜 Скоро', 'menu:soon'), self::btn('👥 Сотрудники', 'menu:users')],
        ];

        if ($requesterId !== null && $this->isAdmin($requesterId)) {
            $rows[] = [self::btn('⚙️ Админ: сотрудники', 'menu:admin')];
        }

        $rows[] = [self::btn('❓ Помощь', 'menu:help')];

        return $this->inlineKeyboard($rows);
    }

    /**
     * Клавиатура со списком сотрудников: нажатие открывает записи за текущий год
     * (аналог "/user &lt;id&gt;").
     *
     * @return array{inline_keyboard: array<int, array<int, array<string, string>>>}
     */
    private function usersKeyboard(string $requesterId): array
    {
        $items = array_values($this->loadUsers());
        usort($items, static fn(array $a, array $b): int => strcasecmp((string)$a['name'], (string)$b['name']));

        $rows = [];
        $row = [];
        foreach ($items as $u) {
            $id = (string)$u['id'];
            $label = $this->displayName($u) . ($id === $requesterId ? ' (я)' : '');
            if (mb_strlen($label) > 28) {
                $label = mb_substr($label, 0, 27) . '…';
            }
            $row[] = self::btn($label, 'usr:' . $id);
            if (count($row) === 2) {
                $rows[] = $row;
                $row = [];
            }
        }
        if ($row !== []) {
            $rows[] = $row;
        }

        return $this->inlineKeyboard(array_merge($rows, $this->menuKeyboard($requesterId)['inline_keyboard']));
    }

    /**
     * @return array{text: string, callback_data: string}
     */
    private static function btn(string $text, string $data): array
    {
        return ['text' => $text, 'callback_data' => $data];
    }

    /**
     * Убирает из rich HTML теги, непонятные sendMessage parse_mode=HTML.
     */
    private function richToSendMessageHtml(string $html): string
    {
        $patterns = [
            '#</?(?:details|summary|p|table|thead|tbody|tr|div|h6)>#' => "\n",
            '#<br\s*/?>#' => "\n",
            '#</?small>#' => '',
            '#<(td|th)[^>]*>#' => ' ',
            '#</(td|th)>#' => ' ',
        ];
        return trim((string)preg_replace(array_keys($patterns), array_values($patterns), $html));
    }

    /**
     * @return string[]
     */
    private function splitMessage(string $text): array
    {
        if (mb_strlen($text) <= self::TEXT_MAX) {
            return [$text];
        }

        $chunks = [];
        $current = '';
        foreach (explode("\n", $text) as $line) {
            if (mb_strlen($current) + mb_strlen($line) + 1 > self::TEXT_MAX) {
                if ($current !== '') {
                    $chunks[] = $current;
                }
                $current = $line;
            } else {
                $current = ($current === '' ? '' : $current . "\n") . $line;
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }
        return $chunks;
    }

    // ---------------------------------------------------------------------
    // Файлы, лог, утилиты
    // ---------------------------------------------------------------------

    private function ensureLogsDir(): void
    {
        $dir = __DIR__ . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    private function readJson(string $file, mixed $default): mixed
    {
        if (!is_file($file)) {
            return $default;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return $default;
        }
        $data = json_decode($raw, true);
        return $data === null ? $default : $data;
    }

    private function writeJson(string $file, mixed $data): void
    {
        $tmp = $file . '.tmp.' . getmypid();
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $this->logError('json_encode failed for ' . $file);
            return;
        }
        file_put_contents($tmp, $json, LOCK_EX);
        @rename($tmp, $file);
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    private function withLock(string $name, callable $fn): mixed
    {
        $handle = fopen(__DIR__ . '/logs/' . $name . '.lock', 'c');
        if ($handle === false) {
            return $fn();
        }
        flock($handle, LOCK_EX);
        try {
            return $fn();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function readOffset(string $file): int
    {
        $saved = trim((string)@file_get_contents($file));
        return $saved !== '' && ctype_digit($saved) ? (int)$saved : 0;
    }

    private function saveOffset(string $file, int $offset): void
    {
        file_put_contents($file, (string)$offset, LOCK_EX);
    }

    private function log(string $msg): void
    {
        $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $msg);
        file_put_contents(__DIR__ . '/logs/vacations.log', $line, FILE_APPEND | LOCK_EX);
    }

    private function logError(string $msg): void
    {
        $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $msg);
        file_put_contents(__DIR__ . '/logs/vacations_errors.log', $line, FILE_APPEND | LOCK_EX);
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$vacationBot = new VacationBot();
echo json_encode(['status' => $vacationBot->run()], JSON_UNESCAPED_UNICODE);
