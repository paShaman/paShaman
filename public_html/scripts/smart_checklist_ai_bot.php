<?php

require_once __DIR__ . '/../../vendor/autoload.php';

include __DIR__ . '/_env.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;

/*
 * @getWebhook https://api.telegram.org/bot<TG_TOKEN>/getWebhookInfo
 * @setWebhook https://api.telegram.org/bot<TG_TOKEN>/setWebhook?url=https://paShaman.dev/scripts/smart_checklist_ai_bot.php&secret_token=<TG_WEBHOOK_SECRET>
 * @deleteWebhook https://api.telegram.org/bot <TG_TOKEN>/deleteWebhook?url=https://paShaman.dev/scripts/smart_checklist_ai_bot.php
 */

// Устанавливаем Content-Type для ответа Telegram
header('Content-Type: application/json');

// ============================================================
// КЛАСС БОТА
// ============================================================

class SmartChecklistAIBot
{
    const MAX_ITEMS = 30;

    // --- КОНФИГУРАЦИЯ ---
    private string $tgToken;
    private int $tgChatId;
    private int $tgAppId;
    private string $tgAppHash;
    private string $openRouterKey;
    private string $deepseekKey;
    private string $deepseekModel;
    private ?API $MadelineProto = null;

    // Список дополнительных разрешенных Telegram ID (белый список)
    // TG_CHAT_ID проверяется отдельно — всегда имеет доступ
    private const array ALLOWED_TELEGRAM_IDS = [
        224028930,  // Алёнка
        1780404823, // alpus
    ];

    // --- ТУМБЛЕРЫ ЛОГИРОВАНИЯ ---
    private bool $logTg;
    private bool $logDeepseek;
    private bool $logOpenRouter;
    private bool $logTgErrors;
    private bool $logUserRequests;
    private bool $logBotStatus;

    // --- ТУМБЛЕР РАБОТЫ С ГРУППАМИ ---
    private bool $groupsEnabled;

    // --- ТРИГГЕРЫ ---
    private const array LIST_CREATE_TRIGGERS = [
        'список', 'чеклист', 'задачи', 'checklist', 'list',
    ];
    private const array LIST_ADD_TRIGGERS = [
        'добавить', 'дополнить', 'добавь', 'дополни', 'add',
    ];

    // --- ВХОДЯЩИЕ ДАННЫЕ ---
    private ?int $chatId = null;
    private ?string $text = null;
    private ?int $userId = null;
    private string $username = 'unknown';
    private ?string $replyToText = null;
    private ?string $voiceFileId = null;
    private ?string $replyToVoiceFileId = null;
    private bool $isBusiness = false;
    private bool $isGroup = false;
    private ?int $replyToMessageId = null;
    private array $replyToChecklist = [];
    private string $businessConnectionId = '';
    private ?int $statusMessageId = null;

    /**
     * Конструктор: инициализирует конфигурацию из переменных окружения
     */
    public function __construct()
    {
        $this->tgToken = (string)getenv('TG_TOKEN');
        $this->tgChatId = (int)getenv('TG_CHAT_ID');
        $this->tgAppId = (int)getenv('TG_APP_ID');
        $this->tgAppHash = (string)getenv('TG_APP_HASH');
        $this->openRouterKey = (string)getenv('OPENROUTER_KEY');
        $this->deepseekKey = (string)getenv('DEEPSEEK_KEY');
        $this->deepseekModel = (string)getenv('DEEPSEEK_MODEL');

        $this->logTg = getenv('LOG_TG') === 'true';
        $this->logDeepseek = getenv('LOG_DEEPSEEK') === 'true';
        $this->logOpenRouter = getenv('LOG_OPENROUTER') === 'true';
        $this->logTgErrors = getenv('LOG_TG_ERRORS') === 'true';
        $this->logUserRequests = getenv('LOG_USER_REQUESTS') === 'true';
        $this->logBotStatus = getenv('LOG_BOT_STATUS') === 'true';

        $this->groupsEnabled = getenv('TG_GROUPS_ENABLED') === 'true';
    }

    /**
     * Главный метод — точка входа.
     * Парсит входящий запрос и запускает обработку.
     */
    public function run(): string
    {
        // Получаем входящий JSON от Telegram
        $input = file_get_contents('php://input');

        // Отладочный лог Telegram
        if ($this->logTg) {
            file_put_contents(
                'tg_debug.log',
                sprintf("%s | Telegram input \n %s\n", date('Y-m-d H:i:s'), $input),
                FILE_APPEND
            );
        }

        $data = json_decode($input, true);

        if (!$data) {
            return 'no_data';
        }

        $this->parseInput($data);

        // Проверка Webhook Secret Token
        $secret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
        if (!hash_equals($secret, getenv('TG_WEBHOOK_SECRET'))) {
            return 'unauthorized_webhook';
        }

        if ($this->text !== null && str_starts_with($this->text, '/start')) { $this->handleStart(); return 'ok'; }
        if ($this->text !== null && str_starts_with($this->text, '/info'))  { $this->handleInfo(); return 'ok'; }
        if ($this->text !== null && str_starts_with($this->text, '/tgid'))  { $this->handleTgId(); return 'ok'; }

        if (!$this->isAccessAllowed()) {
            return 'forbidden';
        }

        if ($this->isGroup && !$this->groupsEnabled) {
            return 'groups_disabled';
        }

        if (
            (!$this->isBusiness && !$this->isGroup) ||
            ($this->isBusiness && $this->businessConnectionId === '')
        ) {
            $this->sendTelegramMessage("⚠️ Генерация списка доступна только в бизнес чате или группах");
            return 'only_business';
        }

        // ------------------------------------------------------------
        // ПРОВЕРКА И УСТАНОВКА LOCK-ФАЙЛА (Защита от параллельных дублей)
        // ------------------------------------------------------------
        $updateId = $data['update_id'] ?? null;

        if ($updateId) {
            if ($this->isUpdateLocked($updateId)) {
                // Если этот update_id уже обрабатывается другим процессом,
                // просто тихо отдаем Telegram статус и выходим, не запуская ИИ повторно.
                echo json_encode(['status' => 'already_processing']);
                return 'duplicate_ignored';
            }

            $this->createLock($updateId);
        }

        // ------------------------------------------------------------
        // Отпускаем Telegram, закрывая соединение
        // ------------------------------------------------------------
        $this->sendEarlyResponseAndContinue();

        // Запускаем основную тяжелую логику в фоне
        try {
            $result = $this->processRequest();
        } finally {
            // Гарантированно снимаем блокировку после окончания работы (или при падении)
            if ($updateId) {
                $this->releaseLock($updateId);
            }
        }

        return $result;
    }

    /**
     * Проверяет, существует ли активный лок для текущего update_id
     * (с защитой от «протухания» лока, если скрипт когда-то упал намертво)
     */
    private function isUpdateLocked(int $updateId): bool
    {
        $lockFile = sys_get_temp_dir() . "/tg_lock_{$updateId}.lock";

        if (file_exists($lockFile)) {
            // Если лок-файл старше 5 минут, значит прошлый процесс умер, не удалив его.
            // В таком случае игнорируем старый лок.
            if (time() - filemtime($lockFile) > 300) {
                @unlink($lockFile);
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * Создает лок-файл
     */
    private function createLock(int $updateId): void
    {
        $lockFile = sys_get_temp_dir() . "/tg_lock_{$updateId}.lock";
        file_put_contents($lockFile, time());
    }

    /**
     * Удаляет лок-файл
     */
    private function releaseLock(int $updateId): void
    {
        $lockFile = sys_get_temp_dir() . "/tg_lock_{$updateId}.lock";
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }
    }

    /**
     * Моментально завершает HTTP-соединение с сервером Telegram,
     * позволяя PHP-скрипту работать дальше в фоне.
     */
    private function sendEarlyResponseAndContinue(): void
    {
        if (ob_get_level()) {
            ob_end_clean();
        }

        header("Connection: close");
        header("Content-Type: application/json");

        ignore_user_abort(true);

        ob_start();
        echo json_encode(['status' => 'processing']);

        header("Content-Length: " . ob_get_length());

        ob_end_flush();
        flush();

        // Если используется PHP-FPM, закрываем fastcgi сессию мгновенно
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    // ============================================================
    // ОБРАБОТЧИКИ КОМАНД
    // ============================================================

    private function handleStart(): void
    {
        $this->sendTelegramMessage("Бизнес\\-бот успешно настроен и готов к работе\!");
    }

    private function handleInfo(): void
    {
        $infoText = "📋 *Бизнес\\-помощник на базе DeepSeek V4*\n\n"
            . "Превращает хаотичные сообщения и ТЗ от клиентов в аккуратные нативные чек\\-листы\\.\n\n"
            . "⚡️ *Как это работает:*\n"
            . "1\\. Добавь бота к бизнес\\-аккаунту или в группу\\.\n"
            . "2\\. *Создать список:* ответь \\(reply\\) на сообщение фразой «список» \\(или «чеклист», «задачи»\\)\\.\n"
            . "3\\. Бот мгновенно пришлет структурированный чек\\-лист\\.\n"
            . "4\\. *Дополнить список:* ответь \\(reply\\) на существующий чек\\-лист фразой «добавить» \\(или «add»\\) — бот расширит список новыми задачами\\.\n\n"
            . "👥 *Фичи:* Бизнес\\-чаты — нативные интерактивные чек\\-листы с возможностью дополнения\\.\n"
            . "🔒 Доступ только по белому списку\\.\n"
            . "⚙️ *Поддерживаемые типы чатов:* бизнес\\-чаты и группы\\.";

        $this->sendTelegramMessage($infoText);
    }

    private function handleTgId(): void
    {
        $this->sendTelegramMessage("🆔 {$this->userId}; 👤 @{$this->username}");
    }

    // ============================================================
    // ПАРСИНГ И ПРОВЕРКИ
    // ============================================================

    private function parseInput(array $data): void
    {
        if (isset($data['business_message'])) {
            $message = $data['business_message'];

            $this->businessConnectionId = $message['business_connection_id'] ?? '';

            $this->isBusiness = true;
        } elseif (isset($data['message'])) {
            $message = $data['message'];

            $chatType = $message['chat']['type'] ?? 'private';
            if ($chatType === 'group' || $chatType === 'supergroup') {
                $this->isGroup = true;
            }
        }

        if (empty($message)) {
            return;
        }

        $this->chatId = $message['chat']['id'];
        $this->text = $message['text'] ?? '';
        $this->userId = $message['from']['id'] ?? null;
        $this->username = $message['from']['username'] ?? 'no_username';

        $this->voiceFileId = $message['voice']['file_id'] ?? null;

        $this->replyToText = $message['reply_to_message']['text']
            ?? $message['reply_to_message']['caption']
            ?? null;
        $this->replyToChecklist = $message['reply_to_message']['checklist'] ?? [];

        $this->replyToVoiceFileId = $message['reply_to_message']['voice']['file_id'] ?? null;
        $this->replyToMessageId = $message['reply_to_message']['message_id'] ?? null;
    }

    private function isAccessAllowed(): bool
    {
        return ($this->userId === $this->tgChatId)
            || in_array($this->userId, self::ALLOWED_TELEGRAM_IDS);
    }

    // ============================================================
    // ОСНОВНАЯ ЛОГИКА ОБРАБОТКИ
    // ============================================================

    private function processRequest(): string
    {
        $totalStart = microtime(true);

        $requestLower = trim(mb_strtolower($this->text ?? ''));
        $isListRequest = $this->isListCreateTrigger($requestLower);

        [$isAddRequest, $matchedTrigger] = $this->detectAddRequest($isListRequest, $requestLower);

        if ($matchedTrigger === '__VOICE_ERROR__') {
            return 'voice_transcribe_error';
        }

        if (empty($this->replyToVoiceFileId)) {
            $text = $this->resolveText($isListRequest, $isAddRequest, $matchedTrigger);
            if ($text === null) {
                return 'ignored';
            }
            $this->text = $text;
        } else {
            $voiceError = $this->handleReplyVoice();
            if ($voiceError !== null) {
                return $voiceError;
            }
        }

        // Отправляем сообщение о начале генерации
        $this->sendTelegramMessage("⏳ Генерирую список\\.\\.\\.", isStatusMessage: true);

        $aiRawOutput = $this->askDeepSeek($this->text);

        $checklistEntries = $this->parseChecklistLines($aiRawOutput);

        if (empty($checklistEntries)) {
            $this->editStatusMessage("⚠️ Не удалось извлечь задачи из сообщения");
            return 'empty_checklist';
        }

        $result = $this->sendChecklistResponse($checklistEntries, $isAddRequest);
        $count = $result['count'];
        $added = $result['added'] ?? 0;

        $truncatedSuffix = $count > self::MAX_ITEMS ? ' \\(макс '.self::MAX_ITEMS.'\\)' : '';

        if (!$result['ok']) {
            $this->editStatusMessage("⚠️ Ошибка генерации списка");
            return 'error';
        }

        $totalTime = round(microtime(true) - $totalStart, 2);
        $timeStr = str_replace(".", "\\.", (string)$totalTime);

        if ($isAddRequest) {
            if ($count > self::MAX_ITEMS && $added > 0) {
                $actualAdded = min(self::MAX_ITEMS, $count + $added) - $count;

                $this->editStatusMessage("➕ Обработано пунктов: *{$added}  шт\\.*, добавлено: *{$actualAdded} шт\\.*{$truncatedSuffix} за `{$timeStr}с`");
            } else {
                $this->editStatusMessage("➕ Добавлено пунктов: *{$added} шт\\.*{$truncatedSuffix} за `{$timeStr}с`");
            }
        } else {
            if ($count > self::MAX_ITEMS) {
                $actualCount = min(self::MAX_ITEMS, $count);

                $this->editStatusMessage("✅ Обработано пунктов: *{$count}  шт\\.*, создано: *{$actualCount} шт\\.*{$truncatedSuffix} за `{$timeStr}с`");
            } else {
                $this->editStatusMessage("✅ Создано пунктов: *{$count} шт\\.*{$truncatedSuffix} за `{$timeStr}с`");
            }
        }

        return 'ok';
    }

    private function isListCreateTrigger(string $requestLower): bool
    {
        return in_array($requestLower, self::LIST_CREATE_TRIGGERS, true)
            && (!empty($this->replyToText) || !empty($this->replyToVoiceFileId));
    }

    private function detectAddRequest(bool $isListRequest, string $requestLower): array
    {
        if ($isListRequest || empty($this->replyToChecklist) || empty($this->replyToMessageId)) {
            return [false, ''];
        }

        if (!empty($this->voiceFileId)) {
            $transcribed = $this->getVoiceTranscription($this->voiceFileId);
            if (empty($transcribed)) {
                $this->editStatusMessage("⚠️ Не удалось распознать аудиосообщение");
                return [false, '__VOICE_ERROR__'];
            }

            $this->text = $transcribed;
            $requestLower = trim(mb_strtolower($this->text));
        }

        foreach (self::LIST_ADD_TRIGGERS as $trigger) {
            $matched = empty($this->voiceFileId)
                ? str_starts_with($requestLower, $trigger)
                : str_contains($requestLower, $trigger);

            if ($matched) {
                return [true, $trigger];
            }
        }

        return [false, ''];
    }

    private function resolveText(bool $isListRequest, bool $isAddRequest, string $matchedTrigger): ?string
    {
        return match (true) {
            $isListRequest => $this->replyToText,
            $isAddRequest => trim(mb_substr($this->text, mb_strlen($matchedTrigger))),
            default => null,
        };
    }

    private function handleReplyVoice(): ?string
    {
        if (empty($this->replyToVoiceFileId)) {
            return null;
        }

        $transcription = $this->getVoiceTranscription($this->replyToVoiceFileId);
        if (empty($transcription)) {
            $this->editStatusMessage("⚠️ Не удалось распознать аудиосообщение");
            return 'voice_transcribe_error';
        }

        if (!empty($this->text)) {
            $transcription = $this->text . "\n--- транскрибация аудио ---\n" . $transcription;
        }
        $this->text = "Это транскрибация голосового сообщения:\n\n" . $transcription;

        return null;
    }

    private function parseChecklistLines(string $aiRawOutput): array
    {
        $entries = [];
        $taskId = 1;

        foreach (explode("\n", trim($aiRawOutput)) as $line) {
            $cleanedLine = trim($line);

            if ($cleanedLine === '') {
                continue;
            }

            $cleanedLine = ltrim($cleanedLine, "-*•·/ \t");
            if (mb_strlen($cleanedLine) > 97) {
                $cleanedLine = mb_substr($cleanedLine, 0, 97) . '…';
            }

            $entries[] = [
                'id' => $taskId++,
                'text' => $cleanedLine,
            ];
        }

        return $entries;
    }

    private function sendChecklistResponse(array $checklistEntries, bool $isAddRequest): array
    {
        if ($isAddRequest) {
            $tasks = $this->replyToChecklist['tasks'] ?? [];

            $lastTaskId = $tasks[count($tasks) - 1]['id'] ?? 0;
            $addedCount = 0;
            foreach ($checklistEntries as $entry) {
                $tasks[] = ['id' => ++$lastTaskId, 'text' => $entry['text']];
                $addedCount++;
            }

            if ($this->isGroup) {
                $ok = $this->sendFromMyself($tasks, $this->replyToMessageId);
            } else {
                $ok = $this->sendTelegramChecklist($tasks, $this->replyToMessageId);
            }

            return ['ok' => $ok, 'count' => count($tasks), 'added' => $addedCount];
        }

        if ($this->isGroup) {
            $ok = $this->sendFromMyself($checklistEntries);
        } else {
            $ok = $this->sendTelegramChecklist($checklistEntries);
        }

        return ['ok' => $ok, 'count' => count($checklistEntries)];
    }

    // ============================================================
    // API DEEPSEEK
    // ============================================================

    private function askDeepSeek(string $message): string
    {
        $startApi = microtime(true);

        $url = 'https://api.deepseek.com/chat/completions';
        $payload = [
            'model' => $this->deepseekModel,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Ты — утилита для структурирования задач. Твоя цель — извлечь список дел из хаотичного текста. Правила:\n1. Одна задача — одна строка.\n2. Никаких дефисов, звездочек, цифр и галочек в начале строки.\n3. Никакого вводного текста, пояснений или Markdown форматирования.\n4. Только сухой текст действий.",
                ],
                [
                    'role' => 'user',
                    'content' => $message,
                ],
            ],
            'temperature' => 0.2,
            'stream' => false,
        ];

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->deepseekKey,
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($this->logDeepseek) {
            $dsLog = sprintf(
                "=== %s ===\n>>> TO DEEPSEEK [%s]: %s\n<<< FROM DEEPSEEK [HTTP %d]: %s\n\n",
                date('Y-m-d H:i:s'),
                $this->deepseekModel,
                $jsonPayload,
                $httpCode,
                $response ?: 'Ошибка cURL: ' . $curlError
            );
            file_put_contents('deepseek_debug.log', $dsLog, FILE_APPEND);
        }

        if (!$response || $curlError) {
            return "Ошибка связи с DeepSeek API.";
        }

        $res = json_decode($response, true);
        $generationTime = round(microtime(true) - $startApi, 2);

        $total = $res['usage']['total_tokens'] ?? 0;
        $cache = $res['usage']['prompt_cache_hit_tokens'] ?? 0;
        $paidTokens = $total - $cache;

        if ($this->logUserRequests) {
            $log = sprintf(
                "[%s] User: @%s | Paid: %d | Cache: %d | Time: %.2fs\n",
                date('Y-m-d H:i:s'),
                $this->username,
                $paidTokens,
                $cache,
                $generationTime
            );
            file_put_contents('user_requests.log', $log, FILE_APPEND);
        }

        return $res['choices'][0]['message']['content'] ?? "Ошибка: пустой ответ API.";
    }

    // ============================================================
    // ОТПРАВКА В TELEGRAM
    // ============================================================

    private function sendFromMyself(array $entries, int $replyToMessageId = 0): bool
    {
        try {
            if (empty($this->MadelineProto)) {
                $settings = new Settings();

                $appInfo = new AppInfo();
                $appInfo->setApiId($this->tgAppId);
                $appInfo->setApiHash($this->tgAppHash);

                $settings->setAppInfo($appInfo);

                $this->MadelineProto = new API('session.madeline', $settings);

                // Запускаем сессию
                $this->MadelineProto->start();

                // Быстрый прогрев базы диалогов для MadelineProto 8.7+
                $this->MadelineProto->getDialogIds();
            }

            if (count($entries) > self::MAX_ITEMS) {
                $entries = array_slice($entries, 0, self::MAX_ITEMS);
            }

            // Форматируем элементы под спецификацию TodoItem и textWithEntities
            foreach ($entries as &$entry) {
                $entry['_'] = 'todoItem';
                $entry['title'] = [
                    '_' => 'textWithEntities',
                    'text' => $entry['text'],
                    'entities' => []
                ];
            }

            // Обертка под корректный inputMediaTodo
            $inputMediaTodo = [
                '_' => 'inputMediaTodo',
                'todo' => [
                    '_' => 'todoList',
                    'title' => [
                        '_' => 'textWithEntities',
                        'text' => "📋 Список задач",
                        'entities' => []
                    ],
                    'list' => $entries,
                    'others_can_append' => true,
                    'others_can_complete' => true,
                ]
            ];

            if ($replyToMessageId) {
                $result = $this->MadelineProto->messages->editMessage(peer: $this->chatId, id: $replyToMessageId, media: $inputMediaTodo);
            } else {
                $result = $this->MadelineProto->messages->sendMedia(peer: $this->chatId, media: $inputMediaTodo);
            }

            if (
                !empty($result['updates'][0]['message']['id']) || //edit
                !empty($result['updates'][0]['id']) //new
            ) {
                return true;
            }
        } catch (\Throwable $e) {
            if ($this->logTgErrors) {
                file_put_contents(
                    'tg_api_errors.log',
                    sprintf("%s | MadelineProto sendFromMyself Error: %s\n", date('Y-m-d H:i:s'), $e->getMessage()),
                    FILE_APPEND
                );
            }
        }

        return false;
    }

    private function sendTelegramChecklist(array $entries, int $replyToMessageId = 0): bool
    {
        $url = 'https://api.telegram.org/bot' . $this->tgToken;
        $url .= $replyToMessageId ? '/editMessageChecklist' : '/sendChecklist';

        if (count($entries) > self::MAX_ITEMS) {
            $entries = array_slice($entries, 0, self::MAX_ITEMS);
        }

        $payload = [
            'business_connection_id' => $this->businessConnectionId,
            'chat_id' => $this->chatId,
            'checklist' => [
                'title' => "📋 Список задач",
                'tasks' => $entries,
                'others_can_add_tasks' => true,
                'others_can_mark_tasks_as_done' => true,
            ],
        ];

        if ($replyToMessageId) {
            $payload['message_id'] = $replyToMessageId;
        }

        return $this->sendCurl($url, $payload);
    }

    private function editStatusMessage(string $text): void
    {
        if ($this->statusMessageId === null) {
            return;
        }

        $url = 'https://api.telegram.org/bot' . $this->tgToken . '/editMessageText';
        $payload = [
            'chat_id' => $this->chatId,
            'message_id' => $this->statusMessageId,
            'text' => $text,
            'parse_mode' => 'MarkdownV2',
        ];

        if ($this->businessConnectionId !== '' && $this->isBusiness) {
            $payload['business_connection_id'] = $this->businessConnectionId;
        }

        $this->sendCurl($url, $payload);

        $this->statusMessageId = null;
    }

    private function sendTelegramMessage(string $text, ?int $replyToMsgId = null, bool $isStatusMessage = false): void
    {
        $url = 'https://api.telegram.org/bot' . $this->tgToken . '/sendMessage';
        $payload = [
            'chat_id' => $this->chatId,
            'text' => $text,
            'parse_mode' => 'MarkdownV2',
        ];

        if ($this->businessConnectionId !== '' && $this->isBusiness) {
            $payload['business_connection_id'] = $this->businessConnectionId;
        }

        if ($replyToMsgId !== null) {
            $payload['reply_to_message_id'] = $replyToMsgId;
        }

        $this->sendCurl($url, $payload, $isStatusMessage);
    }

    private function sendCurl(string $url, array $payload, bool $isStatusMessage = false): bool
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            if ($this->logTgErrors) {
                file_put_contents(
                    'tg_api_errors.log',
                    sprintf("%s | URL: %s | cURL Error: %s\n%s\n", date('Y-m-d H:i:s'), $url, $curlError, print_r($payload, true)),
                    FILE_APPEND
                );
            }
            return false;
        }

        if ($response) {
            $resArr = json_decode($response, true);
            if (isset($resArr['ok']) && $resArr['ok'] === false) {
                if ($this->logTgErrors) {
                    file_put_contents(
                        'tg_api_errors.log',
                        sprintf("%s | URL: %s | Response: %s\n%s\n", date('Y-m-d H:i:s'), $url, $response, print_r($payload, true)),
                        FILE_APPEND
                    );
                }
                return false;
            } else {
                if ($isStatusMessage) {
                    $this->statusMessageId = $resArr['result']['message_id'] ?? null;
                }
            }
            return true;
        }

        return false;
    }

    // ============================================================
    // ЛОГИРОВАНИЕ СТАТУСОВ
    // ============================================================

    /**
     * Логирует статус обработки запроса ботом.
     */
    public function logBotStatus(string $status): void
    {
        if (!$this->logBotStatus) {
            return;
        }

        $log = sprintf(
            "[%s] Status: %-25s | User: @%-15s | ID: %-10s | ChatID: %s\n",
            date('Y-m-d H:i:s'),
            $status,
            $this->username,
            $this->userId ?? 'N/A',
            $this->chatId ?? 'N/A'
        );
        file_put_contents('bot_status.log', $log, FILE_APPEND);
    }

    // ============================================================
    // ТРАНСКРИБАЦИЯ ГОЛОСОВЫХ
    // ============================================================

    private function getVoiceTranscription(string $fileId): ?string
    {
        $voiceFileUrl = $this->getTelegramFileUrl($fileId);
        if (empty($voiceFileUrl)) {
            return null;
        }
        return $this->transcribeWithOpenRouter($voiceFileUrl);
    }

    private function getTelegramFileUrl(string $fileId): ?string
    {
        $url = 'https://api.telegram.org/bot' . $this->tgToken . '/getFile?file_id=' . urlencode($fileId);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        if (!($data['ok'] ?? false) || empty($data['result']['file_path'])) {
            return null;
        }

        return 'https://api.telegram.org/file/bot' . $this->tgToken . '/' . $data['result']['file_path'];
    }

    private function transcribeWithOpenRouter(string $audioUrl): ?string
    {
        $startApi = microtime(true);
        $tmpFile = tempnam(sys_get_temp_dir(), 'tg_voice_');
        $ch = curl_init($audioUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $audioData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($audioData)) {
            if (is_file($tmpFile)) {
                unlink($tmpFile);
            }
            return null;
        }

        file_put_contents($tmpFile, $audioData);

        try {
            $base64Audio = base64_encode($audioData);

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $tmpFile);
            finfo_close($finfo);

            $ext = 'ogg';
            if (str_contains($mime, 'mpeg') || str_contains($mime, 'mp3')) {
                $ext = 'mp3';
            }

            $apiUrl = 'https://openrouter.ai/api/v1/audio/transcriptions';

            $postData = [
                'model' => 'openai/whisper-large-v3-turbo',
                'language' => 'ru',
                'input_audio' => [
                    'data' => $base64Audio,
                    'format' => $ext,
                ],
            ];

            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->openRouterKey,
                'Content-Type: application/json',
            ]);

            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($this->logOpenRouter) {
                $logMsg = sprintf(
                    "=== %s ===\n>>> TO OPENROUTER WHISPER: файл голосовое.%s (Base64 encoded)\n<<< FROM OPENROUTER [HTTP %d]: %s\n\n",
                    date('Y-m-d H:i:s'),
                    $ext,
                    $httpCode,
                    $response ?: 'Ошибка cURL: ' . $curlError
                );
                file_put_contents('openrouter_debug.log', $logMsg, FILE_APPEND);
            }

            if ($curlError || $httpCode !== 200 || !$response) {
                return null;
            }

            $result = json_decode($response, true);
            $transcriptionText = !empty($result['text']) ? trim($result['text']) : null;

            if ($this->logUserRequests && $transcriptionText !== null) {
                $usage = $result['usage'] ?? [];
                $cost = $usage['cost'] ?? 0;
                $duration = round(microtime(true) - $startApi, 2);
                $log = sprintf(
                    "[%s] User: @%s | Transcription | Cost: %s | Duration: %.2fs\n",
                    date('Y-m-d H:i:s'),
                    $this->username,
                    is_numeric($cost) ? number_format((float)$cost, 6) : 'N/A',
                    $duration
                );
                file_put_contents('user_requests.log', $log, FILE_APPEND);
            }

            return $transcriptionText;
        } finally {
            if (is_file($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }
}

// ============================================================
// ТОЧКА ВХОДА
// ============================================================

$bot = new SmartChecklistAIBot();
$status = $bot->run();

// Логируем финальный статус после завершения фоновой работы
$bot->logBotStatus($status);