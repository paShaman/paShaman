<?php

declare(strict_types=1);

include __DIR__ . '/_env.php';
require_once __DIR__ . '/clients/telegram_client.php';

/**
 * Бот регистрации на корпоратив NY2027 (24.12.2026, Санкт-Петербург).
 *
 * Режимы запуска:
 *   php glopro_ny2027_bot.php --listen          long polling (по умолчанию)
 *   php glopro_ny2027_bot.php --set-webhook URL установить вебхук
 *   php glopro_ny2027_bot.php --webhook-info
 *   php glopro_ny2027_bot.php --delete-webhook
 *   php glopro_ny2027_bot.php --set-commands
 *   php glopro_ny2027_bot.php --stats
 *   php glopro_ny2027_bot.php --export
 *   php glopro_ny2027_bot.php --self-test
 *
 * Конфигурация (.env):
 *   TG_GLOPRO_NY_27      — токен бота @glopro_ny2027_bot
 *   NY27_WEBHOOK_SECRET  — секрет вебхука (обязателен для webhook-режима)
 *   NY27_ADMIN_ID        — Telegram ID администратора (через запятую;
 *                          если пусто — берётся TG_CHAT_ID)
 *   NY27_TZ              — часовой пояс (по умолчанию Europe/Moscow)
 *   NY27_IMAGE           — путь к картинке (по умолчанию scripts/assets/ny2027.* или goat.*)
 *   NY27_IMAGE_URL       — публичный URL картинки (альтернатива файлу)
 *   NY27_DATA_DIR        — каталог данных (по умолчанию scripts/logs; удобно для тестов)
 *
 * Хранение (public_html/scripts/logs/):
 *   ny2027_registrations.json — заявки (источник истины)
 *   ny2027_registrations.csv  — выгрузка для Excel
 *   ny2027_sessions.json      — состояния диалога
 *   ny2027_users.json         — все, кто запускал бота (для рассылки)
 *   ny2027_broadcast.json     — черновики рассылки
 *   ny2027_photo.json         — кэш file_id приветственной картинки
 *   ny2027.offset             — offset long polling
 *   ny2027.log, ny2027_errors.log
 *
 * Команды бота: /start /help /my /cancel /id
 * Админ: /stats /list /export /broadcast
 *
 * Кнопка «Удалить заявку» убирает её у пользователя; в CSV запись остаётся
 * со статусом «Удалена» (активные — со статусом «Активна»).
 */

final class Ny2027Bot
{
    /** Лимит символов обычного сообщения. */
    private const TEXT_MAX = 4096;
    /** Лимит подписи к фотографии. */
    private const CAPTION_MAX = 1024;
    /** Время жизни незавершённого диалога, сек. */
    private const SESSION_TTL = 3600;
    /** Максимальная длина текста рассылки (с запасом под оформление). */
    private const BROADCAST_MAX = 3800;
    /** Доступные размеры одежды. */
    private const SIZES = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
    /** Команды, которые перехватываются до обработки текста активной сессии. */
    private const COMMANDS = [
        '/start', '/help', '/my', '/cancel', '/id', '/stats', '/list', '/export', '/broadcast',
        '/delete', '/deleteall',
    ];

    private ?string $tgToken = null;
    private ?TelegramClient $telegramClient = null;
    private string $webhookSecret = '';
    /** @var string[] */
    private array $adminIds = [];
    private string $tz = 'Europe/Moscow';
    private string $imagePath = '';
    private string $imageUrl = '';
    /** Флаг: предупреждение об отсутствующей картинке уже записано в лог. */
    private bool $imageWarned = false;

    public function __construct()
    {
        $this->tgToken = trim((string)getenv('TG_GLOPRO_NY_27')) ?: null;
        $this->webhookSecret = trim((string)getenv('NY27_WEBHOOK_SECRET'));
        $this->tz = trim((string)getenv('NY27_TZ')) ?: 'Europe/Moscow';

        // Админы: явный список NY27_ADMIN_ID + владелец бота из TG_CHAT_ID.
        $adminRaw = trim((string)getenv('NY27_ADMIN_ID')) . ',' . trim((string)getenv('TG_CHAT_ID'));
        $this->adminIds = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $adminRaw)),
            static fn(string $id): bool => $id !== ''
        )));

        $image = trim((string)getenv('NY27_IMAGE'));
        if ($image === '') {
            // Ищем картинку с любым распространённым расширением.
            $image = __DIR__ . '/assets/ny2027.jpg';
            $candidates = [
                'ny2027.jpg', 'ny2027.jpeg', 'ny2027.png', 'ny2027.webp',
                'goat.jpg', 'goat.jpeg', 'goat.png', 'goat.webp',
            ];
            foreach ($candidates as $candidate) {
                $path = __DIR__ . '/assets/' . $candidate;
                if (is_file($path)) {
                    $image = $path;
                    break;
                }
            }
        } elseif (!preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $image)) {
            $image = __DIR__ . '/' . ltrim($image, '/\\');
        }
        $this->imagePath = $image;
        $this->imageUrl = trim((string)getenv('NY27_IMAGE_URL'));

        date_default_timezone_set($this->tz);
        $this->ensureLogsDir();
    }

    // -----------------------------------------------------------------
    // Точка входа
    // -----------------------------------------------------------------

    /** @param list<string> $argv */
    public function runCli(array $argv): int
    {
        $args = array_values(array_slice($argv, 1));
        $command = $args[0] ?? '--listen';

        if (str_starts_with($command, '--set-webhook=')) {
            return $this->cmdSetWebhook(substr($command, strlen('--set-webhook=')));
        }

        switch ($command) {
            case '--listen':
                return $this->listen();
            case '--set-webhook':
                return $this->cmdSetWebhook((string)($args[1] ?? ''));
            case '--webhook-info':
                return $this->cmdWebhookInfo();
            case '--delete-webhook':
                return $this->cmdDeleteWebhook();
            case '--set-commands':
                return $this->cmdSetCommands();
            case '--stats':
                return $this->cmdStatsCli();
            case '--export':
                return $this->cmdExportCli();
            case '--self-test':
                return $this->cmdSelfTest();
            case '--help':
            case '-h':
                $this->printUsage();
                return 0;
            default:
                $this->printUsage();
                return 2;
        }
    }

    public function runWebhook(): string
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            return 'method_not_allowed';
        }

        if ($this->webhookSecret === '') {
            $this->logError('NY27_WEBHOOK_SECRET не задан — вебхук отключён');
            http_response_code(503);
            return 'no_secret';
        }

        $secret = (string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
        if (!hash_equals($this->webhookSecret, $secret)) {
            $this->logError('unauthorized webhook request');
            http_response_code(403);
            return 'unauthorized';
        }

        if ($this->tgToken === null) {
            $this->logError('TG_GLOPRO_NY_27 не задан');
            http_response_code(503);
            return 'no_token';
        }

        $raw = (string)file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            http_response_code(400);
            return 'no_data';
        }

        $updateId = (int)($data['update_id'] ?? 0);
        if ($updateId > 0 && !$this->claimUpdate($updateId)) {
            return 'duplicate';
        }

        try {
            $this->handleUpdate($data);
            return 'ok';
        } catch (\Throwable $e) {
            $this->logError('webhook exception: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            return 'error';
        }
    }

    // -----------------------------------------------------------------
    // Long polling
    // -----------------------------------------------------------------

    private function listen(): int
    {
        if ($this->tgToken === null) {
            fwrite(STDERR, "Ошибка: не задан TG_GLOPRO_NY_27 в .env\n");
            return 2;
        }

        $lock = fopen($this->logsDir() . '/ny2027_listen.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            fwrite(STDERR, "Бот уже запущен (logs/ny2027_listen.lock занят).\n");
            return 1;
        }

        $offsetFile = $this->logsDir() . '/ny2027.offset';
        $lastUpdateId = $this->readOffset($offsetFile);

        // getUpdates не работает, пока установлен вебхук.
        try {
            $info = $this->telegram()->call('getWebhookInfo')->result();
            $webhookUrl = is_array($info) ? (string)($info['url'] ?? '') : '';
            if ($webhookUrl !== '') {
                $this->telegram()->deleteWebhook();
                $this->log('Вебхук ' . $webhookUrl . ' отключён для long polling');
            }
        } catch (\Throwable $e) {
            $this->logError('deleteWebhook: ' . $e->getMessage());
        }

        $this->log('Long-polling запущен, offset=' . $lastUpdateId);
        echo "Long-polling запущен. Для остановки: Ctrl+C\n";

        while (true) {
            try {
                $response = $this->telegram()->timeout(60)->call('getUpdates', [
                    'offset' => $lastUpdateId + 1,
                    'timeout' => 50,
                    'allowed_updates' => ['message', 'callback_query'],
                ]);

                if (!$response->ok()) {
                    $code = $response->errorCode() ?? 0;
                    if ($code === 401) {
                        $this->logError('Неверный TG_GLOPRO_NY_27');
                        break;
                    }
                    if ($code === 409) {
                        $this->logError('409 Conflict: уже работает другой экземпляр бота');
                        break;
                    }
                    $this->logError('getUpdates: ' . json_encode($response->raw(), JSON_UNESCAPED_UNICODE));
                    sleep(5);
                    continue;
                }

                $updates = $response->result();
                if (!is_array($updates)) {
                    $updates = [];
                }

                foreach ($updates as $update) {
                    if (!is_array($update)) {
                        continue;
                    }
                    // Offset фиксируем до обработки: при сбое апдейт не будет
                    // применён повторно и не сломает шаг диалога.
                    $updateId = (int)($update['update_id'] ?? 0);
                    if ($updateId > 0) {
                        $lastUpdateId = $updateId;
                        $this->saveOffset($offsetFile, $updateId);
                    }
                    $this->handleUpdate($update);
                }
            } catch (\Throwable $e) {
                $this->logError('listen exception: ' . $e->getMessage());
                sleep(5);
            }
        }

        flock($lock, LOCK_UN);
        fclose($lock);

        return 0;
    }

    /** @param array<string, mixed> $update */
    private function handleUpdate(array $update): void
    {
        if (isset($update['message']) && is_array($update['message'])) {
            $this->handleMessage($update['message']);
            return;
        }
        if (isset($update['callback_query']) && is_array($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);
        }
    }

    // -----------------------------------------------------------------
    // Сообщения
    // -----------------------------------------------------------------

    /** @param array<string, mixed> $message */
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

        $this->rememberUser($chatId, $from);

        if ($text === '') {
            if ($this->getSession($userId) !== null
                || ($this->isAdmin($userId) && $this->getBroadcast($userId) !== null)
            ) {
                $this->sendHtml($chatId, 'Пожалуйста, отправьте ответ текстом.');
            }
            return;
        }

        $parts = preg_split('/\s+/u', $text, 2) ?: [''];
        $command = mb_strtolower(explode('@', (string)($parts[0] ?? ''), 2)[0]);
        $arg = trim((string)($parts[1] ?? ''));

        if ($command === '/id') {
            $this->sendHtml($chatId, "🆔 Ваш Telegram ID: <code>{$userId}</code>");
            return;
        }

        // Админ пишет текст рассылки — перехватываем до диалога регистрации.
        if ($this->isAdmin($userId)) {
            $broadcastState = $this->getBroadcast($userId);
            if ($broadcastState !== null && !str_starts_with($text, '/')) {
                $this->saveBroadcastText($chatId, $userId, $text);
                return;
            }
        }

        // Свободный текст внутри диалога (включая /skip) обрабатываем до команд.
        if (!in_array($command, self::COMMANDS, true)) {
            $session = $this->getSession($userId);
            if ($session !== null && $this->handleSessionText($chatId, $userId, $session, $text)) {
                return;
            }
        }

        switch ($command) {
            case '/start':
                $this->cmdStart($chatId, $userId);
                return;
            case '/help':
                $this->cmdHelp($chatId, $userId);
                return;
            case '/my':
                $this->cmdMy($chatId, $userId);
                return;
            case '/cancel':
                $hadBroadcast = $this->getBroadcast($userId) !== null;
                $this->clearSession($userId);
                $this->clearBroadcast($userId);
                $this->sendHtml(
                    $chatId,
                    $hadBroadcast
                        ? 'Рассылка отменена.'
                        : 'Заполнение отменено. Вернуться к регистрации: /start'
                );
                return;
            case '/broadcast':
                $this->startBroadcast($chatId, $userId);
                return;
            case '/stats':
                $this->cmdStats($chatId, $userId);
                return;
            case '/list':
                $this->cmdList($chatId, $userId);
                return;
            case '/export':
                $this->cmdExport($chatId, $userId);
                return;
            case '/delete':
                $this->cmdAdminDelete($chatId, $userId, $arg);
                return;
            case '/deleteall':
                $this->cmdAdminDeleteAll($chatId, $userId);
                return;
            default:
                if (str_starts_with($command, '/')) {
                    $this->sendHtml($chatId, 'Неизвестная команда. Список команд: /help');
                }
                return;
        }
    }

    // -----------------------------------------------------------------
    // Inline-кнопки
    // -----------------------------------------------------------------

    /** @param array<string, mixed> $callback */
    private function handleCallback(array $callback): void
    {
        $callbackId = (string)($callback['id'] ?? '');
        $from = is_array($callback['from'] ?? null) ? $callback['from'] : [];
        $userId = (string)($from['id'] ?? '');
        $data = (string)($callback['data'] ?? '');
        $message = is_array($callback['message'] ?? null) ? $callback['message'] : [];
        $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];
        $chatId = (string)($chat['id'] ?? $userId);
        $messageId = (int)($message['message_id'] ?? 0);

        $this->answerCallback($callbackId);

        if ($userId === '' || $chatId === '') {
            return;
        }

        $this->rememberUser($chatId, $from);

        $parts = explode(':', $data);
        $scope = (string)($parts[0] ?? '');

        if ($scope === 'bc') {
            if (!$this->isAdmin($userId)) {
                $this->sendHtml($chatId, '⛔ Команда доступна только администратору.');
                return;
            }

            switch ((string)($parts[1] ?? '')) {
                case 'start':
                    $this->startBroadcast($chatId, $userId);
                    return;
                case 'send':
                    $this->sendBroadcast($chatId, $userId, $messageId);
                    return;
                case 'cancel':
                    $this->clearBroadcast($userId);
                    if ($messageId > 0) {
                        $this->api('editMessageText', [
                            'chat_id' => $chatId,
                            'message_id' => $messageId,
                            'text' => 'Рассылка отменена.',
                            'reply_markup' => ['inline_keyboard' => []],
                        ]);
                    } else {
                        $this->sendHtml($chatId, 'Рассылка отменена.');
                    }
                    return;
            }
            return;
        }

        if ($scope === 'adm') {
            if (!$this->isAdmin($userId)) {
                $this->sendHtml($chatId, '⛔ Команда доступна только администратору.');
                return;
            }

            switch ((string)($parts[1] ?? '')) {
                case 'del':
                    $this->adminDeleteOne(
                        $chatId,
                        $userId,
                        (string)($parts[2] ?? ''),
                        (string)($parts[3] ?? ''),
                        $messageId
                    );
                    return;
                case 'delall':
                    if ((string)($parts[2] ?? '') === 'confirm') {
                        $this->adminDeleteAllConfirm($chatId, $userId, $messageId);
                    }
                    return;
                case 'cancel':
                    $this->finishAdminAction($chatId, $messageId, 'Удаление отменено.');
                    return;
            }
            return;
        }

        if ($scope !== 'reg') {
            return;
        }

        switch ((string)($parts[1] ?? '')) {
            case 'start':
            case 'edit':
                // Убираем кнопки у текстового сообщения, с которого начали заново.
                // У фото-сообщения текста нет, editMessageText для него неприменим.
                if ($messageId > 0 && !isset($message['photo'])) {
                    $this->api('editMessageText', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'text' => '✏️ Заполняем заново.',
                        'reply_markup' => ['inline_keyboard' => []],
                    ]);
                }
                $this->startRegistration($chatId, $userId);
                return;
            case 'my':
                $this->cmdMy($chatId, $userId);
                return;
            case 'size':
                $this->chooseSize($chatId, $userId, (string)($parts[2] ?? ''), $messageId);
                return;
            case 'save':
                $this->finishRegistration($chatId, $userId, $from, $messageId);
                return;
            case 'delete':
                $this->confirmDelete($chatId, $userId);
                return;
            case 'delete_confirm':
                $this->deleteApplication($chatId, $userId, $messageId);
                return;
            case 'delete_cancel':
                if ($messageId > 0) {
                    $this->api('editMessageText', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'text' => 'Удаление отменено.',
                        'reply_markup' => ['inline_keyboard' => []],
                    ]);
                } else {
                    $this->sendHtml($chatId, 'Удаление отменено.');
                }
                return;
            case 'cancel':
                $this->clearSession($userId);
                if ($messageId > 0) {
                    $this->api('editMessageText', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'text' => '❌ Заполнение отменено.',
                        'reply_markup' => ['inline_keyboard' => []],
                    ]);
                }
                return;
        }
    }

    // -----------------------------------------------------------------
    // Приветствие
    // -----------------------------------------------------------------

    private function cmdStart(string $chatId, string $userId): void
    {
        $this->clearSession($userId);
        $this->clearBroadcast($userId);
        $this->sendWelcome($chatId, $userId);

        $record = $this->findRecord($userId);
        if ($record !== null) {
            $this->sendHtml(
                $chatId,
                "✅ <b>Вы уже зарегистрированы.</b>\n\n" . $this->recordSummaryHtml($record)
            );
        }
    }

    private function sendWelcome(string $chatId, string $userId): void
    {
        $caption = $this->welcomeHtml();
        $keyboard = $this->welcomeKeyboard($this->findRecord($userId) !== null);

        if ($this->sendWelcomePhoto($chatId, $caption, $keyboard)) {
            return;
        }

        $this->sendHtml($chatId, $caption, $keyboard);
    }

    private function welcomeHtml(): string
    {
        return <<<'HTML'
🐐 <b>Дорогие друзья!</b>

Хотели бы вам сказать: вы — классные!
Работать с вами — одно удовольствие!
Спасибо за ещё один год работы плечом к плечу, преодолевая все трудности и невзгоды. За вашу вовлечённость и за то, что всегда можно на вас положиться!
Надеемся, что в новом году будет больше поводов для радостных встреч, больших свершений и меньше стресса!

Хотели бы завершить этот насыщенный событиями год на праздничной ноте и пригласить вас проводить его вместе с нами!

📍 <b>24.12.2026, Санкт-Петербург</b>

⛸ <b>Покататься на коньках</b>
13:30–16:00
ICEBURG ARENA
<a href="https://iceburgarena.ru/ledovaya-arena/">iceburgarena.ru</a>
Парашютная улица, 11

🏒 <b>Вместе посмотреть хоккей</b>
19:00
СКА Арена
Шанхайские Драконы — Динамо Мск
<a href="https://www.khl.ru/game/1436/902391/preview/">Матч на сайте КХЛ</a>

🎁 Гарантируем приятное общение, хорошее настроение и подарки от Деда Мороза!

Для участия просьба пройти небольшую регистрацию 👇
HTML;
    }

    /** @return array{inline_keyboard: list<list<array{text: string, callback_data: string}>>} */
    private function welcomeKeyboard(bool $registered): array
    {
        if ($registered) {
            return $this->inlineKeyboard([
                [
                    self::btn('✏️ Изменить данные', 'reg:edit'),
                    self::btn('📋 Моя заявка', 'reg:my'),
                ],
                [self::btn('🗑 Удалить заявку', 'reg:delete')],
            ]);
        }

        return $this->registerKeyboard();
    }

    // -----------------------------------------------------------------
    // Диалог регистрации
    // -----------------------------------------------------------------

    private function startRegistration(string $chatId, string $userId): void
    {
        $this->clearBroadcast($userId);

        $existing = $this->findRecord($userId);
        $data = [];
        if ($existing !== null) {
            $data = [
                'fio' => (string)($existing['fio'] ?? ''),
                'company' => (string)($existing['company'] ?? ''),
                'size' => (string)($existing['size'] ?? ''),
            ];
        }

        $this->setSession($userId, [
            'step' => 'fio',
            'data' => $data,
            'chat_id' => $chatId,
            'updated_at' => time(),
        ]);

        $this->askFio($chatId, $data);
    }

    /** @param array<string, mixed> $data */
    private function askFio(string $chatId, array $data): void
    {
        $current = trim((string)($data['fio'] ?? ''));
        $hint = '';
        if ($current !== '') {
            $hint = "\n\nСейчас: <b>" . $this->esc($current) . '</b>'
                . "\nЧтобы оставить без изменений, отправьте /skip.";
        }

        $this->sendHtml(
            $chatId,
            "👤 <b>Шаг 1 из 3</b>\n\nВведите ваше ФИО полностью:"
            . "\n<i>Например: Иванов Иван Иванович</i>" . $hint
        );
    }

    /** @param array<string, mixed> $data */
    private function askCompany(string $chatId, array $data): void
    {
        $current = trim((string)($data['company'] ?? ''));
        $hint = '';
        if ($current !== '') {
            $hint = "\n\nСейчас: <b>" . $this->esc($current) . '</b>'
                . "\nЧтобы оставить без изменений, отправьте /skip.";
        }

        $this->sendHtml(
            $chatId,
            "🏢 <b>Шаг 2 из 3</b>\n\nУкажите компанию:" . $hint
        );
    }

    private function askSize(string $chatId): void
    {
        $this->sendHtml(
            $chatId,
            "👕 <b>Шаг 3 из 3</b>\n\nВыберите размер одежды:",
            $this->sizeKeyboard()
        );
    }

    /** @param array<string, mixed> $session */
    private function handleSessionText(string $chatId, string $userId, array $session, string $text): bool
    {
        $step = (string)($session['step'] ?? '');
        $data = is_array($session['data'] ?? null) ? $session['data'] : [];
        $text = $this->normalizeSpaces($text);

        if ($step === 'confirm') {
            $this->sendHtml($chatId, 'Подтвердите заявку или измените данные кнопками ниже 👇');
            return true;
        }

        if ($step !== 'fio' && $step !== 'company') {
            $this->sendHtml($chatId, 'Выберите размер кнопкой ниже 👇');
            return true;
        }

        if ($text !== '' && $text[0] === '/' && mb_strtolower($text) !== '/skip') {
            return false;
        }

        $field = $step === 'fio' ? 'fio' : 'company';
        $current = trim((string)($data[$field] ?? ''));

        if (mb_strtolower($text) === '/skip') {
            if ($current === '') {
                $this->sendHtml($chatId, 'Здесь нечего пропускать — введите значение текстом.');
                return true;
            }
        } else {
            if ($step === 'fio') {
                if (!$this->validFio($text)) {
                    $this->sendHtml(
                        $chatId,
                        'Похоже, это не ФИО. Введите фамилию, имя и отчество текстом:'
                        . "\n<i>Например: Иванов Иван Иванович</i>"
                    );
                    return true;
                }
                $data['fio'] = $text;
            } else {
                if (!$this->validCompany($text)) {
                    $this->sendHtml($chatId, 'Укажите компанию текстом (от 2 до 120 символов).');
                    return true;
                }
                $data['company'] = $text;
            }
        }

        if ($step === 'fio') {
            $this->setSession($userId, [
                'step' => 'company',
                'data' => $data,
                'chat_id' => $chatId,
                'updated_at' => time(),
            ]);
            $this->askCompany($chatId, $data);
            return true;
        }

        $this->setSession($userId, [
            'step' => 'size',
            'data' => $data,
            'chat_id' => $chatId,
            'updated_at' => time(),
        ]);
        $this->askSize($chatId);
        return true;
    }

    private function chooseSize(string $chatId, string $userId, string $size, int $messageId): void
    {
        $size = strtoupper(trim($size));
        if (!in_array($size, self::SIZES, true)) {
            $this->sendHtml($chatId, 'Неизвестный размер. Выберите кнопкой из списка.');
            return;
        }

        $session = $this->getSession($userId);
        $step = $session !== null ? (string)($session['step'] ?? '') : '';
        if ($session === null || ($step !== 'size' && $step !== 'confirm')) {
            $record = $this->findRecord($userId);
            $this->sendHtml(
                $chatId,
                $record !== null
                    ? 'Вы уже зарегистрированы. Чтобы изменить данные, нажмите «✏️ Изменить данные».'
                    : 'Сессия устарела. Начните заново: /start'
            );
            return;
        }

        $data = is_array($session['data'] ?? null) ? $session['data'] : [];
        $data['size'] = $size;

        $this->setSession($userId, [
            'step' => 'confirm',
            'data' => $data,
            'chat_id' => $chatId,
            'updated_at' => time(),
        ]);

        if ($messageId > 0) {
            $edited = $this->api('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $this->confirmHtml($data),
                'parse_mode' => 'HTML',
                'reply_markup' => $this->confirmKeyboard(),
            ]);
            if ($edited !== null) {
                return;
            }
        }

        $this->sendHtml($chatId, $this->confirmHtml($data), $this->confirmKeyboard());
    }

    /** @param array<string, mixed> $from */
    private function finishRegistration(string $chatId, string $userId, array $from, int $messageId): void
    {
        $session = $this->getSession($userId);
        if ($session === null) {
            // Повторное нажатие «Подтвердить» после успешного сохранения.
            $record = $this->findRecord($userId);
            if ($record !== null) {
                $this->sendHtml(
                    $chatId,
                    "✅ Вы уже зарегистрированы.\n\n" . $this->recordSummaryHtml($record),
                    $this->editKeyboard()
                );
                return;
            }
            $this->sendHtml($chatId, 'Заявка устарела. Начните заново: /start');
            return;
        }

        if ((string)($session['step'] ?? '') !== 'confirm') {
            $this->sendHtml($chatId, 'Заявка устарела. Начните заново: /start');
            return;
        }

        $data = is_array($session['data'] ?? null) ? $session['data'] : [];
        $fio = trim((string)($data['fio'] ?? ''));
        $company = trim((string)($data['company'] ?? ''));
        $size = strtoupper(trim((string)($data['size'] ?? '')));

        if (!$this->validFio($fio) || !$this->validCompany($company) || !in_array($size, self::SIZES, true)) {
            $this->clearSession($userId);
            $this->sendHtml($chatId, 'Не хватает данных заявки. Начните заново: /start');
            return;
        }

        $record = $this->saveRecord($userId, $from, $fio, $company, $size);
        $this->clearSession($userId);

        if ($messageId > 0) {
            $this->api('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => '✅ Заявка отправлена.',
                'reply_markup' => ['inline_keyboard' => []],
            ]);
        }

        $this->sendHtml($chatId, $this->successHtml($record), $this->editKeyboard());
    }

    // -----------------------------------------------------------------
    // Команды
    // -----------------------------------------------------------------

    private function cmdHelp(string $chatId, string $userId): void
    {
        $text = "🐐 <b>Регистрация на корпоратив</b>\n\n"
            . "/start — приглашение и регистрация\n"
            . "/my — моя заявка\n"
            . "/cancel — отменить заполнение\n"
            . "/id — мой Telegram ID";

        $keyboard = null;
        if ($this->isAdmin($userId)) {
            $text .= "\n\n<b>Админ:</b>\n/stats — статистика\n/list — список\n/export — CSV для Excel\n/broadcast — рассылка\n/delete &lt;номер|ID&gt; — удалить одну заявку\n/deleteall — удалить все заявки";
            $keyboard = $this->inlineKeyboard([[self::btn('✉️ Рассылка', 'bc:start')]]);
        }

        $this->sendHtml($chatId, $text, $keyboard);
    }

    private function cmdMy(string $chatId, string $userId): void
    {
        $record = $this->findRecord($userId);
        if ($record === null) {
            $this->sendHtml(
                $chatId,
                "Вы ещё не зарегистрированы.\n\nНажмите кнопку ниже или отправьте /start.",
                $this->registerKeyboard()
            );
            return;
        }

        $this->sendHtml(
            $chatId,
            "📋 <b>Ваша заявка</b>\n\n" . $this->recordSummaryHtml($record),
            $this->editKeyboard()
        );
    }

    private function cmdStats(string $chatId, string $userId): void
    {
        if (!$this->isAdmin($userId)) {
            $this->sendHtml($chatId, '⛔ Команда доступна только администратору.');
            return;
        }

        $stats = $this->statsData();
        $lines = [
            '📊 <b>Статистика регистрации</b>',
            '',
            'Активных: <b>' . $stats['total'] . '</b>',
            'Удалено: <b>' . $stats['deleted'] . '</b>',
            '',
        ];
        foreach (self::SIZES as $size) {
            $lines[] = $size . ': ' . ($stats['sizes'][$size] ?? 0);
        }

        $this->sendHtml($chatId, implode("\n", $lines));
    }

    private function cmdList(string $chatId, string $userId): void
    {
        if (!$this->isAdmin($userId)) {
            $this->sendHtml($chatId, '⛔ Команда доступна только администратору.');
            return;
        }

        $records = $this->sortedRecords();
        $deleted = count($this->loadArchive());

        if ($records === []) {
            $this->sendHtml(
                $chatId,
                $deleted > 0
                    ? "Активных заявок нет. Удалённых: {$deleted} — они есть в выгрузке /export."
                    : 'Пока нет зарегистрированных.'
            );
            return;
        }

        $lines = ['👥 <b>Зарегистрированные</b> (' . count($records) . '):', ''];
        $index = 0;
        foreach ($records as $record) {
            $index++;
            $lines[] = $index . '. ' . $this->esc((string)($record['fio'] ?? ''))
                . ' — ' . $this->esc((string)($record['company'] ?? ''))
                . ' — <b>' . $this->esc((string)($record['size'] ?? '')) . '</b>';
        }

        if ($deleted > 0) {
            $lines[] = '';
            $lines[] = 'Удалённых заявок: ' . $deleted . ' (в выгрузке /export)';
        }

        $lines[] = '';
        $lines[] = 'Удалить одну: /delete &lt;номер&gt;, все: /deleteall';

        $this->sendHtml($chatId, implode("\n", $lines));
    }

    private function cmdExport(string $chatId, string $userId): void
    {
        if (!$this->isAdmin($userId)) {
            $this->sendHtml($chatId, '⛔ Команда доступна только администратору.');
            return;
        }

        $data = $this->loadData();
        $this->writeCsv($data['records'], $data['archive']);

        $caption = 'Регистрации NY2027: активных ' . count($data['records'])
            . ', удалённых ' . count($data['archive']);
        if (!$this->sendDocumentFile($chatId, $this->csvFile(), 'ny2027_registrations.csv', $caption)) {
            $this->sendHtml($chatId, 'Не удалось отправить CSV. Файл на сервере: <code>' . $this->esc($this->csvFile()) . '</code>');
        }
    }

    private function confirmDelete(string $chatId, string $userId): void
    {
        $record = $this->findRecord($userId);
        if ($record === null) {
            $this->sendHtml($chatId, 'Активной заявки нет — удалять нечего.', $this->registerKeyboard());
            return;
        }

        $this->sendHtml(
            $chatId,
            "🗑 <b>Удалить заявку?</b>\n\n"
            . $this->recordSummaryHtml($record)
            . "\n\nПосле удаления вы сможете зарегистрироваться заново.",
            $this->deleteConfirmKeyboard()
        );
    }

    private function deleteApplication(string $chatId, string $userId, int $messageId): void
    {
        $deleted = $this->deleteRecord($userId);
        if ($deleted === null) {
            if ($messageId > 0) {
                $this->api('editMessageText', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => 'Активной заявки уже нет.',
                    'reply_markup' => $this->registerKeyboard(),
                ]);
            } else {
                $this->sendHtml($chatId, 'Активной заявки уже нет.', $this->registerKeyboard());
            }
            return;
        }

        $this->clearSession($userId);

        if ($messageId > 0) {
            $this->api('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => '🗑 <b>Заявка удалена.</b>',
                'parse_mode' => 'HTML',
                'reply_markup' => ['inline_keyboard' => []],
            ]);
        } else {
            $this->sendHtml($chatId, '🗑 <b>Заявка удалена.</b>');
        }

        $this->sendHtml($chatId, 'Вы можете зарегистрироваться заново:', $this->registerKeyboard());
    }

    private function cmdAdminDelete(string $chatId, string $userId, string $arg): void
    {
        if (!$this->isAdmin($userId)) {
            $this->sendHtml($chatId, '⛔ Команда доступна только администратору.');
            return;
        }

        $arg = trim($arg);
        if ($arg === '') {
            $this->sendHtml(
                $chatId,
                'Использование: <code>/delete &lt;номер из /list&gt;</code> или <code>/delete &lt;Telegram ID&gt;</code>.'
            );
            return;
        }

        $records = $this->sortedRecords();
        if ($records === []) {
            $this->sendHtml($chatId, 'Нет активных заявок.');
            return;
        }

        $record = null;
        if (ctype_digit($arg)) {
            $number = (int)$arg;
            if ($number >= 1 && $number <= count($records)) {
                $record = $records[$number - 1];
            }
        }
        if ($record === null) {
            $record = $this->findRecord($arg);
        }

        if ($record === null) {
            $this->sendHtml(
                $chatId,
                'Заявка не найдена. /list — номера активных заявок; также можно указать Telegram ID.'
            );
            return;
        }

        $targetId = (string)($record['telegram_id'] ?? '');
        if ($targetId === '') {
            $this->sendHtml($chatId, 'У заявки нет Telegram ID — удаление невозможно.');
            return;
        }

        $hash = substr(md5((string)($record['created_at'] ?? '')), 0, 8);

        $this->sendHtml(
            $chatId,
            "🗑 <b>Удалить заявку?</b>\n\n"
            . $this->recordSummaryHtml($record)
            . "\nTelegram ID: <code>" . $this->esc($targetId) . "</code>"
            . "\n\nЗаявка исчезнет у пользователя, в выгрузке останется со статусом «Удалена».",
            $this->adminDeleteKeyboard($targetId, $hash)
        );
    }

    private function cmdAdminDeleteAll(string $chatId, string $userId): void
    {
        if (!$this->isAdmin($userId)) {
            $this->sendHtml($chatId, '⛔ Команда доступна только администратору.');
            return;
        }

        $count = count($this->sortedRecords());
        if ($count === 0) {
            $this->sendHtml($chatId, 'Нет активных заявок.');
            return;
        }

        $this->sendHtml(
            $chatId,
            "🗑 <b>Удалить все заявки?</b>\n\n"
            . "Активных заявок: <b>{$count}</b>.\n\n"
            . 'Все они переместятся в архив со статусом «Удалена» и останутся в /export. '
            . 'Пользователи смогут зарегистрироваться заново.',
            $this->adminDeleteAllKeyboard($count)
        );
    }

    private function adminDeleteOne(
        string $chatId,
        string $adminId,
        string $targetId,
        string $hash,
        int $messageId
    ): void {
        if (!$this->isAdmin($adminId)) {
            $this->sendHtml($chatId, '⛔ Команда доступна только администратору.');
            return;
        }

        $record = $this->findRecord($targetId);
        if ($record === null) {
            $this->finishAdminAction($chatId, $messageId, 'Заявка уже удалена.');
            return;
        }

        $actualHash = substr(md5((string)($record['created_at'] ?? '')), 0, 8);
        if (!hash_equals($actualHash, $hash)) {
            $this->finishAdminAction($chatId, $messageId, 'Заявка изменилась. Проверьте /list и повторите.');
            return;
        }

        $deleted = $this->deleteRecord($targetId);
        $this->clearSession($targetId);

        if ($deleted === null) {
            $this->finishAdminAction($chatId, $messageId, 'Заявка уже удалена.');
            return;
        }

        $this->log("Админ {$adminId} удалил заявку: ID={$targetId}");
        $fio = trim((string)($deleted['fio'] ?? ''));
        $this->finishAdminAction(
            $chatId,
            $messageId,
            $fio !== '' ? '🗑 <b>Заявка удалена:</b> ' . $this->esc($fio) : '🗑 <b>Заявка удалена.</b>'
        );
    }

    private function adminDeleteAllConfirm(string $chatId, string $adminId, int $messageId): void
    {
        if (!$this->isAdmin($adminId)) {
            $this->sendHtml($chatId, '⛔ Команда доступна только администратору.');
            return;
        }

        $count = $this->deleteAllRecords();
        $this->clearAllSessions();

        if ($count === 0) {
            $this->finishAdminAction($chatId, $messageId, 'Активных заявок уже нет.');
            return;
        }

        $this->log("Админ {$adminId} удалил все заявки: {$count}");
        $this->finishAdminAction(
            $chatId,
            $messageId,
            "🗑 <b>Удалено заявок: {$count}.</b>\n\nВсе перемещены в архив со статусом «Удалена»."
        );
    }

    private function finishAdminAction(string $chatId, int $messageId, string $html): void
    {
        if ($messageId > 0) {
            $edited = $this->api('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $html,
                'parse_mode' => 'HTML',
                'reply_markup' => ['inline_keyboard' => []],
            ]);
            if ($edited !== null) {
                return;
            }
        }

        $this->sendHtml($chatId, $html);
    }

    // -----------------------------------------------------------------
    // Рассылка (админ)
    // -----------------------------------------------------------------

    private function startBroadcast(string $chatId, string $userId): void
    {
        if (!$this->isAdmin($userId)) {
            $this->sendHtml($chatId, '⛔ Команда доступна только администратору.');
            return;
        }

        $this->clearSession($userId);
        $this->setBroadcast($userId, ['step' => 'text', 'updated_at' => time()]);
        $this->sendHtml(
            $chatId,
            "✉️ <b>Рассылка</b>\n\n"
            . "Отправьте текст сообщения — я покажу превью и спрошу подтверждение.\n\n"
            . 'Максимум ' . self::BROADCAST_MAX . ' символов. Эмодзи и ссылки поддерживаются. Отмена: /cancel.'
        );
    }

    private function saveBroadcastText(string $chatId, string $userId, string $text): void
    {
        if (!$this->isAdmin($userId)) {
            return;
        }

        $text = trim($text);
        $length = mb_strlen($text);
        if ($text === '') {
            $this->sendHtml($chatId, 'Текст пустой. Отправьте сообщение текстом.');
            return;
        }
        if ($length > self::BROADCAST_MAX) {
            $this->sendHtml(
                $chatId,
                "Слишком длинное сообщение: {$length} символов. Максимум "
                . self::BROADCAST_MAX . ' — сократите текст.'
            );
            return;
        }

        $this->setBroadcast($userId, ['step' => 'confirm', 'text' => $text, 'updated_at' => time()]);
        $total = count($this->broadcastRecipients($userId));

        $this->sendHtml(
            $chatId,
            "✉️ <b>Проверьте сообщение</b>\n\nПолучателей: <b>{$total}</b>. Ниже — то, что увидят пользователи:"
        );
        $this->sendHtml($chatId, $this->formatBroadcastHtml($text));
        $this->sendHtml($chatId, 'Отправить всем?', $this->broadcastConfirmKeyboard($total));
    }

    private function formatBroadcastHtml(string $text): string
    {
        return "📢 <b>Сообщение от GloPro</b>\n"
            . "──────────\n\n"
            . $this->esc($text)
            . "\n\n──────────\n"
            . '🐐 <i>Корпоратив NY2027</i>';
    }

    private function sendBroadcast(string $chatId, string $userId, int $messageId): void
    {
        if (!$this->isAdmin($userId)) {
            $this->sendHtml($chatId, '⛔ Команда доступна только администратору.');
            return;
        }

        $state = $this->getBroadcast($userId);
        $text = is_array($state) ? trim((string)($state['text'] ?? '')) : '';
        if ($text === '') {
            $this->clearBroadcast($userId);
            $message = 'Черновик рассылки не найден. Начните заново: /broadcast';
            if ($messageId > 0) {
                $this->api('editMessageText', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $message,
                    'reply_markup' => ['inline_keyboard' => []],
                ]);
            } else {
                $this->sendHtml($chatId, $message);
            }
            return;
        }

        $recipients = $this->broadcastRecipients($userId);
        $total = count($recipients);
        if ($total === 0) {
            $this->clearBroadcast($userId);
            $message = 'Нет получателей — кроме вас, никто ещё не запускал бота.';
            if ($messageId > 0) {
                $this->api('editMessageText', [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $message,
                    'reply_markup' => ['inline_keyboard' => []],
                ]);
            } else {
                $this->sendHtml($chatId, $message);
            }
            return;
        }

        $this->clearBroadcast($userId);

        $formatted = $this->formatBroadcastHtml($text);
        $started = "✉️ Рассылка началась. Получателей: <b>{$total}</b>.";
        if ($messageId > 0) {
            $this->api('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $started,
                'parse_mode' => 'HTML',
                'reply_markup' => ['inline_keyboard' => []],
            ]);
        } else {
            $this->sendHtml($chatId, $started);
        }

        $sent = 0;
        $blocked = 0;
        $failed = 0;
        $delivery = [];

        foreach ($recipients as $recipientChatId => $_user) {
            $recipientChatId = (string)$recipientChatId;
            $status = $this->broadcastSend($recipientChatId, $formatted);
            if ($status === 'ok') {
                $sent++;
                $delivery[$recipientChatId] = false;
            } elseif ($status === 'blocked') {
                $blocked++;
                $delivery[$recipientChatId] = true;
            } else {
                $failed++;
            }

            // Не упираемся в лимит Telegram (~30 сообщений/сек).
            usleep(40000);
        }

        $this->markUsersDelivery($delivery);

        $report = "✅ <b>Рассылка завершена.</b>\n\nОтправлено: <b>{$sent}</b>\nВсего получателей: {$total}";
        if ($blocked > 0) {
            $report .= "\nЗаблокировали бота: {$blocked}";
        }
        if ($failed > 0) {
            $report .= "\nОшибок: {$failed}";
        }
        $this->sendHtml($chatId, $report);
        $this->log("broadcast: admin={$userId}, total={$total}, sent={$sent}, blocked={$blocked}, failed={$failed}");
    }

    /**
     * @param string $excludeChatId чат отправителя — ему рассылка не нужна
     * @return array<string, array<string, mixed>>
     */
    private function broadcastRecipients(string $excludeChatId = ''): array
    {
        $excludeChatId = (string)$excludeChatId;
        $candidates = [];

        $users = $this->readJson($this->usersFile(), []);
        if (is_array($users)) {
            foreach ($users as $chatId => $user) {
                $candidates[(string)$chatId] = is_array($user) ? $user : [];
            }
        }

        // Регистрации до появления реестра пользователей и удалённые заявки.
        $data = $this->loadData();
        foreach (array_merge(array_values($data['records']), $data['archive']) as $record) {
            if (!is_array($record)) {
                continue;
            }
            $chatId = (string)($record['telegram_id'] ?? '');
            if ($chatId !== '' && !isset($candidates[$chatId])) {
                $candidates[$chatId] = [];
            }
        }

        $recipients = [];
        foreach ($candidates as $chatId => $user) {
            $chatId = (string)$chatId;
            if ($chatId === '' || str_starts_with($chatId, '-')) {
                continue;
            }
            if ($excludeChatId !== '' && $chatId === $excludeChatId) {
                continue;
            }
            if (!empty($user['blocked'])) {
                continue;
            }
            $recipients[$chatId] = $user;
        }

        return $recipients;
    }

    private function broadcastSend(string $chatId, string $html): string
    {
        try {
            $result = $this->telegram()->timeout(20)->call('sendMessage', [
                'chat_id' => $chatId,
                'text' => $html,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);
        } catch (\Throwable $e) {
            $this->logError('broadcast ' . $chatId . ': ' . $e->getMessage());
            return 'error';
        }

        if ($result->ok()) {
            return 'ok';
        }

        $code = $result->errorCode() ?? 0;
        $description = (string)($result->description() ?? '');
        if ($code === 403
            || ($code === 400 && (stripos($description, 'chat not found') !== false
                || stripos($description, 'deactivated') !== false))
        ) {
            return 'blocked';
        }

        $this->logError('broadcast ' . $chatId . ': ' . $code . ' ' . $description);

        return 'error';
    }

    /** @param array<string, bool> $delivery */
    private function markUsersDelivery(array $delivery): void
    {
        if ($delivery === []) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->withLock('ny2027_users', function () use ($delivery, $now): void {
            $users = $this->readJson($this->usersFile(), []);
            if (!is_array($users)) {
                $users = [];
            }

            foreach ($delivery as $chatId => $blocked) {
                $chatId = (string)$chatId;
                $old = is_array($users[$chatId] ?? null) ? $users[$chatId] : [];
                $users[$chatId] = array_merge([
                    'chat_id' => $chatId,
                    'telegram_id' => $chatId,
                    'username' => '',
                    'name' => '',
                    'first_seen' => $now,
                    'last_seen' => $now,
                ], $old, [
                    'blocked' => (bool)$blocked,
                    'blocked_at' => $blocked ? $now : null,
                ]);
            }

            $this->writeJson($this->usersFile(), $users);
        });
    }

    /** @param array<string, mixed> $from */
    private function rememberUser(string $chatId, array $from): void
    {
        if ($chatId === '' || str_starts_with($chatId, '-')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->withLock('ny2027_users', function () use ($chatId, $from, $now): void {
            $users = $this->readJson($this->usersFile(), []);
            if (!is_array($users)) {
                $users = [];
            }

            $old = is_array($users[$chatId] ?? null) ? $users[$chatId] : [];
            $isNew = $old === [];
            $username = trim((string)($from['username'] ?? ''));
            $name = trim(
                trim((string)($from['first_name'] ?? '')) . ' ' . trim((string)($from['last_name'] ?? ''))
            );

            $users[$chatId] = [
                'chat_id' => $chatId,
                'telegram_id' => (string)($from['id'] ?? $chatId),
                'username' => $username,
                'name' => $name,
                'first_seen' => (string)($old['first_seen'] ?? $now),
                'last_seen' => $now,
                'blocked' => false,
                'blocked_at' => null,
            ];

            $this->writeJson($this->usersFile(), $users);

            if ($isNew) {
                $this->log(sprintf(
                    'Новый пользователь бота: ID=%s, username=%s, имя=%s',
                    $chatId,
                    $username !== '' ? '@' . $username : '—',
                    $name !== '' ? $name : '—'
                ));
            }
        });
    }

    /** @return array<string, mixed>|null */
    private function getBroadcast(string $adminId): ?array
    {
        $states = $this->readJson($this->broadcastFile(), []);
        if (!is_array($states)) {
            return null;
        }

        $state = $states[$adminId] ?? null;
        if (!is_array($state)) {
            return null;
        }
        if ((int)($state['updated_at'] ?? 0) < time() - self::SESSION_TTL) {
            return null;
        }

        return $state;
    }

    /** @param array<string, mixed> $state */
    private function setBroadcast(string $adminId, array $state): void
    {
        $this->withLock('ny2027_broadcast', function () use ($adminId, $state): void {
            $states = $this->readJson($this->broadcastFile(), []);
            if (!is_array($states)) {
                $states = [];
            }

            foreach ($states as $id => $old) {
                if (!is_array($old) || (int)($old['updated_at'] ?? 0) < time() - self::SESSION_TTL) {
                    unset($states[$id]);
                }
            }

            $states[$adminId] = $state;
            $this->writeJson($this->broadcastFile(), $states);
        });
    }

    private function clearBroadcast(string $adminId): void
    {
        $this->withLock('ny2027_broadcast', function () use ($adminId): void {
            $states = $this->readJson($this->broadcastFile(), []);
            if (!is_array($states) || !isset($states[$adminId])) {
                return;
            }

            unset($states[$adminId]);
            $this->writeJson($this->broadcastFile(), $states);
        });
    }

    /** @return array{inline_keyboard: list<list<array{text: string, callback_data: string}>>} */
    private function broadcastConfirmKeyboard(int $total): array
    {
        return $this->inlineKeyboard([[
            self::btn('✅ Отправить всем (' . $total . ')', 'bc:send'),
            self::btn('❌ Отмена', 'bc:cancel'),
        ]]);
    }

    // -----------------------------------------------------------------
    // Тексты
    // -----------------------------------------------------------------

    /** @param array<string, mixed> $record */
    private function recordSummaryHtml(array $record): string
    {
        return '👤 <b>ФИО:</b> ' . $this->esc((string)($record['fio'] ?? ''))
            . "\n🏢 <b>Компания:</b> " . $this->esc((string)($record['company'] ?? ''))
            . "\n👕 <b>Размер:</b> " . $this->esc((string)($record['size'] ?? ''));
    }

    /** @param array<string, mixed> $data */
    private function confirmHtml(array $data): string
    {
        return "Проверьте данные заявки:\n\n"
            . '👤 <b>ФИО:</b> ' . $this->esc((string)($data['fio'] ?? ''))
            . "\n🏢 <b>Компания:</b> " . $this->esc((string)($data['company'] ?? ''))
            . "\n👕 <b>Размер:</b> " . $this->esc((string)($data['size'] ?? ''))
            . "\n\nВсё верно?";
    }

    /** @param array<string, mixed> $record */
    private function successHtml(array $record): string
    {
        return "🎉 <b>Вы зарегистрированы!</b>\n\n"
            . $this->recordSummaryHtml($record)
            . "\n\n📅 <b>24.12.2026, Санкт-Петербург</b>"
            . "\n⛸ 13:30–16:00 — ICEBURG ARENA, Парашютная ул., 11"
            . "\n🏒 19:00 — СКА Арена, Шанхайские Драконы — Динамо Мск"
            . "\n\nЖдём вас! 🐐";
    }

    // -----------------------------------------------------------------
    // Клавиатуры
    // -----------------------------------------------------------------

    /** @return array{inline_keyboard: list<list<array{text: string, callback_data: string}>>} */
    private function sizeKeyboard(): array
    {
        $rows = [];
        $row = [];
        foreach (self::SIZES as $size) {
            $row[] = self::btn($size, 'reg:size:' . $size);
            if (count($row) === 3) {
                $rows[] = $row;
                $row = [];
            }
        }
        if ($row !== []) {
            $rows[] = $row;
        }
        $rows[] = [self::btn('❌ Отмена', 'reg:cancel')];

        return $this->inlineKeyboard($rows);
    }

    /** @return array{inline_keyboard: list<list<array{text: string, callback_data: string}>>} */
    private function confirmKeyboard(): array
    {
        return $this->inlineKeyboard([
            [self::btn('✅ Подтвердить', 'reg:save'), self::btn('✏️ Изменить', 'reg:edit')],
            [self::btn('❌ Отмена', 'reg:cancel')],
        ]);
    }

    /** @return array{inline_keyboard: list<list<array{text: string, callback_data: string}>>} */
    private function editKeyboard(): array
    {
        return $this->inlineKeyboard([
            [self::btn('✏️ Изменить данные', 'reg:edit')],
            [self::btn('🗑 Удалить заявку', 'reg:delete')],
        ]);
    }

    /** @return array{inline_keyboard: list<list<array{text: string, callback_data: string}>>} */
    private function registerKeyboard(): array
    {
        return $this->inlineKeyboard([[
            self::btn('🎟 Пройти регистрацию', 'reg:start'),
        ]]);
    }

    /** @return array{inline_keyboard: list<list<array{text: string, callback_data: string}>>} */
    private function deleteConfirmKeyboard(): array
    {
        return $this->inlineKeyboard([[
            self::btn('🗑 Да, удалить', 'reg:delete_confirm'),
            self::btn('↩️ Отмена', 'reg:delete_cancel'),
        ]]);
    }

    /** @return array{inline_keyboard: list<list<array{text: string, callback_data: string}>>} */
    private function adminDeleteKeyboard(string $targetId, string $hash): array
    {
        return $this->inlineKeyboard([[
            self::btn('🗑 Да, удалить', 'adm:del:' . $targetId . ':' . $hash),
            self::btn('❌ Отмена', 'adm:cancel'),
        ]]);
    }

    /** @return array{inline_keyboard: list<list<array{text: string, callback_data: string}>>} */
    private function adminDeleteAllKeyboard(int $count): array
    {
        return $this->inlineKeyboard([[
            self::btn('🗑 Удалить все (' . $count . ')', 'adm:delall:confirm'),
            self::btn('❌ Отмена', 'adm:cancel'),
        ]]);
    }

    /**
     * @param list<list<array{text: string, callback_data: string}>> $rows
     * @return array{inline_keyboard: list<list<array{text: string, callback_data: string}>>}
     */
    private function inlineKeyboard(array $rows): array
    {
        return ['inline_keyboard' => $rows];
    }

    /** @return array{text: string, callback_data: string} */
    private static function btn(string $text, string $data): array
    {
        return ['text' => $text, 'callback_data' => $data];
    }

    // -----------------------------------------------------------------
    // Хранилище
    // -----------------------------------------------------------------

    /**
     * @return array{version: int, records: array<string, array<string, mixed>>, archive: list<array<string, mixed>>}
     */
    private function loadData(): array
    {
        $data = $this->readJson($this->recordsFile(), []);
        if (!is_array($data)) {
            return ['version' => 2, 'records' => [], 'archive' => []];
        }

        $records = is_array($data['records'] ?? null) ? $data['records'] : [];
        $archive = is_array($data['archive'] ?? null)
            ? array_values(array_filter($data['archive'], static fn(mixed $row): bool => is_array($row)))
            : [];

        return ['version' => 2, 'records' => $records, 'archive' => $archive];
    }

    /** @return list<array<string, mixed>> */
    private function loadArchive(): array
    {
        return $this->loadData()['archive'];
    }

    /** @return array<string, array<string, mixed>> */
    private function loadRecords(): array
    {
        return $this->loadData()['records'];
    }

    /** @return array<string, mixed>|null */
    private function findRecord(string $userId): ?array
    {
        $records = $this->loadRecords();
        $record = $records[$userId] ?? null;

        return is_array($record) ? $record : null;
    }

    /**
     * @param array<string, mixed> $from
     * @return array<string, mixed>
     */
    private function saveRecord(string $userId, array $from, string $fio, string $company, string $size): array
    {
        $now = date('Y-m-d H:i:s');
        $record = [];

        $this->mutateRecords(function (array &$records) use ($userId, $from, $fio, $company, $size, $now, &$record): void {
            $old = is_array($records[$userId] ?? null) ? $records[$userId] : [];
            $isNew = $old === [];
            $username = trim((string)($from['username'] ?? ''));

            $record = [
                'telegram_id' => $userId,
                'fio' => $fio,
                'company' => $company,
                'size' => $size,
                'username' => $username,
                'tg_name' => trim(
                    trim((string)($from['first_name'] ?? '')) . ' ' . trim((string)($from['last_name'] ?? ''))
                ),
                'created_at' => (string)($old['created_at'] ?? $now),
                'updated_at' => $now,
            ];
            $records[$userId] = $record;

            $this->log(sprintf(
                'Регистрация %s: ID=%s, username=%s, ФИО=%s, компания=%s, размер=%s',
                $isNew ? 'создана' : 'обновлена',
                $userId,
                $username !== '' ? '@' . $username : '—',
                $fio,
                $company,
                $size
            ));
        });

        return $record;
    }

    /**
     * @param array{records: array<string, array<string, mixed>>, archive: list<array<string, mixed>>} $data
     */
    private function writeData(array $data): void
    {
        $this->writeJson($this->recordsFile(), [
            'version' => 2,
            'records' => $data['records'],
            'archive' => array_values($data['archive']),
        ]);
        $this->writeCsv($data['records'], $data['archive']);
    }

    /**
     * @param callable(array<string, array<string, mixed>>&): void $fn
     */
    private function mutateRecords(callable $fn): void
    {
        $this->withLock('ny2027_records', function () use ($fn): void {
            $data = $this->loadData();
            $records = $data['records'];

            $fn($records);

            $data['records'] = $records;
            $this->writeData($data);
        });
    }

    /**
     * Убирает заявку из активных и переносит её в архив с пометкой «Удалена».
     *
     * @return array<string, mixed>|null
     */
    private function deleteRecord(string $userId): ?array
    {
        $deleted = $this->withLock('ny2027_records', function () use ($userId): ?array {
            $data = $this->loadData();
            $record = $data['records'][$userId] ?? null;
            if (!is_array($record)) {
                return null;
            }

            $record['status'] = 'deleted';
            $record['deleted_at'] = date('Y-m-d H:i:s');
            $data['archive'][] = $record;
            unset($data['records'][$userId]);

            $this->writeData($data);

            $username = trim((string)($record['username'] ?? ''));
            $this->log(sprintf(
                'Заявка удалена: ID=%s, username=%s, ФИО=%s, компания=%s, размер=%s',
                $userId,
                $username !== '' ? '@' . $username : '—',
                (string)($record['fio'] ?? '—'),
                (string)($record['company'] ?? '—'),
                (string)($record['size'] ?? '—')
            ));

            return $record;
        });

        return is_array($deleted) ? $deleted : null;
    }

    private function deleteAllRecords(): int
    {
        $count = $this->withLock('ny2027_records', function (): int {
            $data = $this->loadData();
            if ($data['records'] === []) {
                return 0;
            }

            $now = date('Y-m-d H:i:s');
            $deleted = 0;

            foreach ($data['records'] as $userId => $record) {
                if (!is_array($record)) {
                    continue;
                }

                $record['status'] = 'deleted';
                $record['deleted_at'] = $now;
                $data['archive'][] = $record;
                $deleted++;

                $username = trim((string)($record['username'] ?? ''));
                $this->log(sprintf(
                    'Заявка удалена: ID=%s, username=%s, ФИО=%s, компания=%s, размер=%s',
                    (string)$userId,
                    $username !== '' ? '@' . $username : '—',
                    (string)($record['fio'] ?? '—'),
                    (string)($record['company'] ?? '—'),
                    (string)($record['size'] ?? '—')
                ));
            }

            $data['records'] = [];
            $this->writeData($data);
            $this->log("Массовое удаление заявок: {$deleted}");

            return $deleted;
        });

        return is_int($count) ? $count : 0;
    }

    /** @return array<string, mixed>|null */
    private function getSession(string $userId): ?array
    {
        $sessions = $this->readJson($this->sessionsFile(), []);
        if (!is_array($sessions)) {
            return null;
        }

        $session = $sessions[$userId] ?? null;
        if (!is_array($session)) {
            return null;
        }
        if ((int)($session['updated_at'] ?? 0) < time() - self::SESSION_TTL) {
            return null;
        }

        return $session;
    }

    /** @param array<string, mixed> $session */
    private function setSession(string $userId, array $session): void
    {
        $this->withLock('ny2027_sessions', function () use ($userId, $session): void {
            $sessions = $this->readJson($this->sessionsFile(), []);
            if (!is_array($sessions)) {
                $sessions = [];
            }

            foreach ($sessions as $id => $old) {
                if (!is_array($old) || (int)($old['updated_at'] ?? 0) < time() - self::SESSION_TTL) {
                    unset($sessions[$id]);
                }
            }

            $sessions[$userId] = $session;
            $this->writeJson($this->sessionsFile(), $sessions);
        });
    }

    private function clearSession(string $userId): void
    {
        $this->withLock('ny2027_sessions', function () use ($userId): void {
            $sessions = $this->readJson($this->sessionsFile(), []);
            if (!is_array($sessions) || !isset($sessions[$userId])) {
                return;
            }

            unset($sessions[$userId]);
            $this->writeJson($this->sessionsFile(), $sessions);
        });
    }

    private function clearAllSessions(): void
    {
        $this->withLock('ny2027_sessions', function (): void {
            $this->writeJson($this->sessionsFile(), []);
        });
    }

    /**
     * @return array{total: int, deleted: int, sizes: array<string, int>}
     */
    private function statsData(): array
    {
        $data = $this->loadData();
        $sizes = array_fill_keys(self::SIZES, 0);

        foreach ($data['records'] as $record) {
            $size = strtoupper((string)($record['size'] ?? ''));
            if (isset($sizes[$size])) {
                $sizes[$size]++;
            }
        }

        return [
            'total' => count($data['records']),
            'deleted' => count($data['archive']),
            'sizes' => $sizes,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function sortedRecords(): array
    {
        $records = array_values($this->loadRecords());
        usort(
            $records,
            static fn(array $a, array $b): int => strcmp(
                (string)($a['created_at'] ?? ''),
                (string)($b['created_at'] ?? '')
            )
        );

        return $records;
    }

    /**
     * @param array<string, array<string, mixed>> $records
     * @param list<array<string, mixed>> $archive
     */
    private function writeCsv(array $records, array $archive = []): void
    {
        $file = $this->csvFile();
        $tmp = $file . '.tmp';

        $handle = @fopen($tmp, 'wb');
        if ($handle === false) {
            $this->logError('Не удалось открыть CSV для записи: ' . $tmp);
            return;
        }

        // BOM — чтобы Excel корректно открыл UTF-8, разделитель ; — для русской локали.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv(
            $handle,
            ['Дата регистрации', 'ФИО', 'Компания', 'Размер', 'Telegram ID', 'Username', 'Имя в Telegram', 'Обновлено', 'Статус', 'Удалено'],
            ';',
            '"',
            '',
            "\n"
        );

        $rows = [];
        foreach ($records as $record) {
            if (is_array($record)) {
                $rows[] = ['record' => $record, 'status' => 'Активна', 'deleted_at' => ''];
            }
        }
        foreach ($archive as $record) {
            if (is_array($record)) {
                $rows[] = [
                    'record' => $record,
                    'status' => 'Удалена',
                    'deleted_at' => (string)($record['deleted_at'] ?? ''),
                ];
            }
        }

        usort(
            $rows,
            static fn(array $a, array $b): int => strcmp(
                (string)($a['record']['created_at'] ?? ''),
                (string)($b['record']['created_at'] ?? '')
            )
        );

        foreach ($rows as $row) {
            $record = $row['record'];
            fputcsv($handle, [
                (string)($record['created_at'] ?? ''),
                (string)($record['fio'] ?? ''),
                (string)($record['company'] ?? ''),
                (string)($record['size'] ?? ''),
                (string)($record['telegram_id'] ?? ''),
                (string)($record['username'] ?? ''),
                (string)($record['tg_name'] ?? ''),
                (string)($record['updated_at'] ?? ''),
                $row['status'],
                $row['deleted_at'],
            ], ';', '"', '', "\n");
        }

        fclose($handle);

        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            $this->logError('Не удалось переименовать CSV: ' . $tmp);
        }
    }

    // -----------------------------------------------------------------
    // Telegram API
    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function api(string $method, array $payload): ?array
    {
        try {
            $result = $this->telegram()->timeout(25)->call($method, $payload);
        } catch (TelegramException $e) {
            $this->logError($method . ': ' . $e->getMessage());
            return null;
        }

        if (!$result->ok()) {
            $this->logError($method . ' bad response: ' . json_encode($result->raw(), JSON_UNESCAPED_UNICODE));
            return null;
        }

        $raw = $result->raw();

        return is_array($raw) ? $raw : null;
    }

    private function telegram(): TelegramClient
    {
        if ($this->telegramClient === null) {
            if ($this->tgToken === null) {
                throw new TelegramException('Не задан TG_GLOPRO_NY_27');
            }
            $this->telegramClient = new TelegramClient($this->tgToken);
        }

        return $this->telegramClient;
    }

    private function answerCallback(string $callbackId): void
    {
        if ($callbackId === '') {
            return;
        }
        $this->api('answerCallbackQuery', ['callback_query_id' => $callbackId]);
    }

    private function sendHtml(int|string $chatId, string $html, ?array $keyboard = null): bool
    {
        $chunks = $this->splitMessage($html);
        $last = count($chunks) - 1;
        $allOk = true;

        foreach ($chunks as $index => $chunk) {
            $markup = ($keyboard !== null && $index === $last) ? $keyboard : null;
            $payload = [
                'chat_id' => $chatId,
                'text' => $chunk,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ];
            if ($markup !== null) {
                $payload['reply_markup'] = $markup;
            }

            if ($this->api('sendMessage', $payload) !== null) {
                continue;
            }

            // Фолбэк: кривой HTML или неподдерживаемый тег — отправляем текстом.
            $plain = [
                'chat_id' => $chatId,
                'text' => html_entity_decode(strip_tags($chunk), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
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

    /**
     * @param array{inline_keyboard: list<list<array{text: string, callback_data: string}>>} $keyboard
     */
    private function sendWelcomePhoto(string $chatId, string $caption, array $keyboard): bool
    {
        $fits = $this->visibleLength($caption) <= self::CAPTION_MAX;
        $captionForPhoto = $fits ? $caption : null;
        $keyboardForPhoto = $fits ? $keyboard : null;

        $cachedFileId = $this->cachedPhotoFileId();
        if ($cachedFileId !== null) {
            $payload = [
                'chat_id' => $chatId,
                'photo' => $cachedFileId,
                'reply_markup' => $keyboardForPhoto ?? ['inline_keyboard' => []],
            ];
            if ($captionForPhoto !== null) {
                $payload['caption'] = $captionForPhoto;
                $payload['parse_mode'] = 'HTML';
            }
            if ($this->api('sendPhoto', $payload) !== null) {
                if (!$fits) {
                    $this->sendHtml($chatId, $caption, $keyboard);
                }
                return true;
            }
            // file_id мог устареть — пробуем загрузить файл заново.
            @unlink($this->photoCacheFile());
        }

        if (is_file($this->imagePath) && is_readable($this->imagePath)) {
            $result = $this->uploadPhoto($chatId, $captionForPhoto, $keyboardForPhoto);
            if ($result !== null) {
                $this->cachePhotoFileId($result);
                if (!$fits) {
                    $this->sendHtml($chatId, $caption, $keyboard);
                }
                return true;
            }
        } elseif (!$this->imageWarned) {
            $this->imageWarned = true;
            $this->logError('Приветственная картинка не найдена: ' . $this->imagePath . ' — отправляю текст.');
        }

        if ($this->imageUrl !== '') {
            $payload = [
                'chat_id' => $chatId,
                'photo' => $this->imageUrl,
                'reply_markup' => $keyboardForPhoto ?? ['inline_keyboard' => []],
            ];
            if ($captionForPhoto !== null) {
                $payload['caption'] = $captionForPhoto;
                $payload['parse_mode'] = 'HTML';
            }
            if ($this->api('sendPhoto', $payload) !== null) {
                if (!$fits) {
                    $this->sendHtml($chatId, $caption, $keyboard);
                }
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{inline_keyboard: list<list<array{text: string, callback_data: string}>>}|null $keyboard
     * @return array<string, mixed>|null
     */
    private function uploadPhoto(string $chatId, ?string $caption, ?array $keyboard): ?array
    {
        $fields = [
            'chat_id' => $chatId,
            'photo' => new CURLFile($this->imagePath, $this->imageMime($this->imagePath), basename($this->imagePath)),
        ];
        if ($caption !== null) {
            $fields['caption'] = $caption;
            $fields['parse_mode'] = 'HTML';
        }
        if ($keyboard !== null) {
            $fields['reply_markup'] = json_encode($keyboard, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $this->upload('sendPhoto', $fields);
    }

    private function sendDocumentFile(string $chatId, string $path, string $filename, string $caption): bool
    {
        if (!is_file($path)) {
            return false;
        }

        $fields = [
            'chat_id' => $chatId,
            'document' => new CURLFile($path, 'text/csv', $filename),
            'caption' => $caption,
        ];

        return $this->upload('sendDocument', $fields) !== null;
    }

    /**
     * Multipart-загрузка файла в Telegram.
     *
     * @param array<string, mixed> $fields
     * @return array<string, mixed>|null
     */
    private function upload(string $method, array $fields): ?array
    {
        if ($this->tgToken === null) {
            return null;
        }

        $url = 'https://api.telegram.org/bot' . $this->tgToken . '/' . $method;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        curl_setopt_array($ch, tgProxyCurlOptionsForUrl($url));

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (!is_string($body) || $body === '' || $error !== '') {
            $this->logError($method . ' upload: ' . ($error !== '' ? $error : 'empty response') . ' [HTTP ' . $status . ']');
            return null;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
            $this->logError($method . ' upload bad response: ' . mb_substr($body, 0, 500));
            return null;
        }

        $result = $decoded['result'] ?? null;

        return is_array($result) ? $result : [];
    }

    /** @param array<string, mixed> $result */
    private function cachePhotoFileId(array $result): void
    {
        $photo = $result['photo'] ?? null;
        if (!is_array($photo) || $photo === []) {
            return;
        }

        $best = null;
        $bestSize = -1;
        foreach ($photo as $size) {
            if (!is_array($size)) {
                continue;
            }
            $fileSize = (int)($size['file_size'] ?? 0);
            if ($fileSize > $bestSize) {
                $bestSize = $fileSize;
                $best = $size;
            }
        }

        $fileId = is_array($best) ? (string)($best['file_id'] ?? '') : '';
        if ($fileId === '') {
            return;
        }

        $this->writeJson($this->photoCacheFile(), [
            'path' => realpath($this->imagePath) ?: $this->imagePath,
            'mtime' => (int)@filemtime($this->imagePath),
            'file_id' => $fileId,
        ]);
    }

    private function cachedPhotoFileId(): ?string
    {
        $cache = $this->readJson($this->photoCacheFile(), []);
        if (!is_array($cache) || !is_file($this->imagePath)) {
            return null;
        }

        $path = realpath($this->imagePath) ?: $this->imagePath;
        if ((string)($cache['path'] ?? '') !== $path) {
            return null;
        }
        if ((int)($cache['mtime'] ?? 0) !== (int)@filemtime($this->imagePath)) {
            return null;
        }

        $fileId = (string)($cache['file_id'] ?? '');

        return $fileId !== '' ? $fileId : null;
    }

    // -----------------------------------------------------------------
    // Утилиты
    // -----------------------------------------------------------------

    /** @return list<string> */
    private function splitMessage(string $text): array
    {
        $limit = self::TEXT_MAX - 100;
        if (mb_strlen($text) <= $limit) {
            return [$text];
        }

        $chunks = [];
        $current = '';
        $lines = preg_split('/\n/u', $text) ?: [$text];

        foreach ($lines as $line) {
            $line = (string)$line;
            $candidate = $current === '' ? $line : $current . "\n" . $line;
            if (mb_strlen($candidate) <= $limit) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $chunks[] = $current;
                $current = '';
            }

            while (mb_strlen($line) > $limit) {
                $chunks[] = mb_substr($line, 0, $limit);
                $line = mb_substr($line, $limit);
            }
            $current = $line;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks === [] ? [$text] : $chunks;
    }

    private function visibleLength(string $html): int
    {
        $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_strlen($plain);
    }

    private function normalizeSpaces(string $value): string
    {
        $value = str_replace(["\r", "\n", "\t", "\u{00A0}"], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function validFio(string $value): bool
    {
        $value = $this->normalizeSpaces($value);
        $length = mb_strlen($value);
        if ($length < 5 || $length > 120) {
            return false;
        }
        if (preg_match('/\d/u', $value) === 1) {
            return false;
        }

        $words = preg_split('/\s+/u', $value) ?: [];
        $words = array_values(array_filter($words, static fn(string $word): bool => $word !== ''));
        if (count($words) < 2 || count($words) > 6) {
            return false;
        }

        foreach ($words as $word) {
            if (mb_strlen($word) < 2) {
                return false;
            }
            if (preg_match("/^[\\p{L}\\-.']+$/u", $word) !== 1) {
                return false;
            }
        }

        return true;
    }

    private function validCompany(string $value): bool
    {
        $value = $this->normalizeSpaces($value);
        $length = mb_strlen($value);
        if ($length < 2 || $length > 120) {
            return false;
        }

        return preg_match('/[\\p{L}\\p{N}]/u', $value) === 1;
    }

    private function imageMime(string $path): string
    {
        $mime = function_exists('mime_content_type') ? (string)@mime_content_type($path) : '';
        if (str_starts_with($mime, 'image/')) {
            return $mime;
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function isAdmin(string $userId): bool
    {
        return in_array($userId, $this->adminIds, true);
    }

    // -----------------------------------------------------------------
    // Файлы, блокировки, логи
    // -----------------------------------------------------------------

    private function logsDir(): string
    {
        $dir = trim((string)getenv('NY27_DATA_DIR'));
        if ($dir === '') {
            return __DIR__ . '/logs';
        }
        if (!preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $dir)) {
            $dir = __DIR__ . '/' . ltrim($dir, '/\\');
        }

        return rtrim($dir, '/\\');
    }

    private function ensureLogsDir(): void
    {
        $dir = $this->logsDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    private function recordsFile(): string
    {
        return $this->logsDir() . '/ny2027_registrations.json';
    }

    private function csvFile(): string
    {
        return $this->logsDir() . '/ny2027_registrations.csv';
    }

    private function sessionsFile(): string
    {
        return $this->logsDir() . '/ny2027_sessions.json';
    }

    private function usersFile(): string
    {
        return $this->logsDir() . '/ny2027_users.json';
    }

    private function broadcastFile(): string
    {
        return $this->logsDir() . '/ny2027_broadcast.json';
    }

    private function photoCacheFile(): string
    {
        return $this->logsDir() . '/ny2027_photo.json';
    }

    /** @return mixed */
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

        return $data ?? $default;
    }

    private function writeJson(string $file, mixed $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $this->logError('json_encode failed: ' . $file);
            return;
        }

        $tmp = $file . '.tmp.' . getmypid();
        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            $this->logError('write failed: ' . $tmp);
            return;
        }

        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            $this->logError('rename failed: ' . $tmp . ' -> ' . $file);
        }
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    private function withLock(string $name, callable $fn): mixed
    {
        $handle = fopen($this->logsDir() . '/' . $name . '.lock', 'c');
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
        @file_put_contents($file, (string)$offset, LOCK_EX);
    }

    private function claimUpdate(int $updateId): bool
    {
        $file = $this->logsDir() . '/ny2027.offset';

        return (bool)$this->withLock('ny2027_offset', function () use ($file, $updateId): bool {
            if ($updateId <= $this->readOffset($file)) {
                return false;
            }
            $this->saveOffset($file, $updateId);

            return true;
        });
    }

    private function log(string $message): void
    {
        $line = sprintf('[%s] %s', date('Y-m-d H:i:s'), $message);
        @file_put_contents(
            $this->logsDir() . '/ny2027.log',
            $line . "\n",
            FILE_APPEND | LOCK_EX
        );

        // В long polling дублируем в консоль батника, чтобы события было видно сразу.
        if (PHP_SAPI === 'cli') {
            echo $line . PHP_EOL;
        }
    }

    private function logError(string $message): void
    {
        $line = sprintf('[%s] %s', date('Y-m-d H:i:s'), $message);
        @file_put_contents(
            $this->logsDir() . '/ny2027_errors.log',
            $line . "\n",
            FILE_APPEND | LOCK_EX
        );

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $line . PHP_EOL);
        }
    }

    // -----------------------------------------------------------------
    // CLI
    // -----------------------------------------------------------------

    private function printUsage(): void
    {
        echo "Бот регистрации на корпоратив NY2027\n\n";
        echo "Запуск:\n";
        echo "  php glopro_ny2027_bot.php --listen\n\n";
        echo "Настройка:\n";
        echo "  --set-webhook <url>  установить вебхук (нужен NY27_WEBHOOK_SECRET)\n";
        echo "  --webhook-info       информация о вебхуке\n";
        echo "  --delete-webhook     удалить вебхук\n";
        echo "  --set-commands       меню команд в Telegram\n\n";
        echo "Данные:\n";
        echo "  --stats              статистика\n";
        echo "  --export             выгрузить CSV\n";
        echo "  --self-test          самопроверка валидации и текста\n";
    }

    private function cmdSetWebhook(string $url): int
    {
        $url = trim($url);
        if (!preg_match('~^https://~i', $url)) {
            fwrite(STDERR, "URL вебхука должен начинаться с https://\n");
            return 2;
        }
        if ($this->webhookSecret === '') {
            fwrite(STDERR, "Сначала задайте NY27_WEBHOOK_SECRET в .env\n");
            return 2;
        }

        try {
            $result = $this->telegram()->setWebhook($url, [
                'secret_token' => $this->webhookSecret,
                'allowed_updates' => ['message', 'callback_query'],
            ]);
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . "\n");
            return 1;
        }

        if ($result->ok()) {
            echo "Вебхук установлен: {$url}\n";
            return 0;
        }

        fwrite(STDERR, 'Ошибка: ' . ($result->description() ?? 'unknown') . "\n");

        return 1;
    }

    private function cmdWebhookInfo(): int
    {
        try {
            $result = $this->telegram()->call('getWebhookInfo');
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . "\n");
            return 1;
        }

        echo json_encode($result->raw(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        return $result->ok() ? 0 : 1;
    }

    private function cmdDeleteWebhook(): int
    {
        try {
            $result = $this->telegram()->deleteWebhook();
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . "\n");
            return 1;
        }

        echo $result->ok() ? "Вебхук удалён.\n" : 'Ошибка: ' . ($result->description() ?? 'unknown') . "\n";

        return $result->ok() ? 0 : 1;
    }

    private function cmdSetCommands(): int
    {
        try {
            $result = $this->telegram()->call('setMyCommands', [
                'commands' => [
                    ['command' => 'start', 'description' => 'Приглашение и регистрация'],
                    ['command' => 'my', 'description' => 'Моя заявка'],
                    ['command' => 'help', 'description' => 'Помощь'],
                    ['command' => 'cancel', 'description' => 'Отменить заполнение'],
                ],
            ]);
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Ошибка: ' . $e->getMessage() . "\n");
            return 1;
        }

        echo $result->ok() ? "Команды установлены.\n" : 'Ошибка: ' . ($result->description() ?? 'unknown') . "\n";

        return $result->ok() ? 0 : 1;
    }

    private function cmdStatsCli(): int
    {
        $stats = $this->statsData();
        echo "Активных: {$stats['total']}\n";
        echo "Удалено: {$stats['deleted']}\n";
        foreach (self::SIZES as $size) {
            echo $size . ': ' . ($stats['sizes'][$size] ?? 0) . "\n";
        }

        return 0;
    }

    private function cmdExportCli(): int
    {
        $data = $this->loadData();
        $this->writeCsv($data['records'], $data['archive']);
        echo 'CSV: ' . $this->csvFile() . ' (активных: ' . count($data['records'])
            . ', удалённых: ' . count($data['archive']) . ")\n";

        return 0;
    }

    private function cmdSelfTest(): int
    {
        $welcome = $this->welcomeHtml();
        $length = $this->visibleLength($welcome);

        $imageValid = false;
        $imageInfo = 'не найдена';
        if (is_file($this->imagePath) && is_readable($this->imagePath)) {
            $info = @getimagesize($this->imagePath);
            if (is_array($info)) {
                $imageValid = true;
                $imageInfo = $info[0] . 'x' . $info[1] . ' ' . (string)($info['mime'] ?? '');
            } else {
                $imageInfo = 'файл не является изображением';
            }
        }

        $checks = [
            'fio_valid' => $this->validFio('Иванов Иван Иванович'),
            'fio_invalid_short' => !$this->validFio('Иван'),
            'fio_invalid_digits' => !$this->validFio('Иванов Иван 2000'),
            'company_valid' => $this->validCompany('ООО Ромашка'),
            'company_invalid' => !$this->validCompany(' '),
            'caption_fits' => $length <= self::CAPTION_MAX,
            'image_valid' => $imageValid,
        ];

        $ok = true;
        foreach ($checks as $name => $passed) {
            echo ($passed ? 'OK   ' : 'FAIL ') . $name . "\n";
            if (!$passed) {
                $ok = false;
            }
        }

        echo 'welcome visible length: ' . $length . ' (limit ' . self::CAPTION_MAX . ")\n";
        echo 'image: ' . $this->imagePath . ' (' . $imageInfo . ")\n";

        return $ok ? 0 : 1;
    }
}

// Точка входа выполняется только при прямом запуске файла, а не при include из тестов.
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    $bot = new Ny2027Bot();

    if (PHP_SAPI === 'cli') {
        exit($bot->runCli($_SERVER['argv'] ?? []));
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => $bot->runWebhook()], JSON_UNESCAPED_UNICODE);
}
