<?php

declare(strict_types=1);

require_once __DIR__ . '/../_env.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;

/**
 * MadelineClient — клиент MadelineProto (MTProto, отправка от имени пользователя).
 *
 * Инкапсулирует жизненный цикл сессии (session_<userId>), прогрев peer-базы,
 * очистку зависших lock/ipc-файлов, отправку/редактирование медиа и нативных
 * todo-чек-листов (inputMediaTodo).
 *
 * Пример:
 *   require_once __DIR__ . '/clients/madeline_client.php';
 *
 *   $tg = new MadelineClient();                 // TG_APP_ID / TG_APP_HASH из .env
 *   $tg->ensureStarted($userId, $chatId);       // init + start + прогрев peer
 *   $tg->sendTodoList($chatId, $entries, $replyToMessageId);
 *
 * Логирование: ->logger(fn (string $m) => ...); при отсутствии логгера молчит.
 *
 * Переменные окружения:
 *   TG_APP_ID, TG_APP_HASH          — доступ приложения (можно передать в конструктор)
 *   MADELINE_STALE_LOCK_SECONDS     — возраст lock-файла, после которого он считается зомби (60)
 *   MADELINE_SESSION_PREFIX         — префикс имени сессии (по умолчанию "session_")
 */
final class MadelineException extends RuntimeException
{
}

/**
 * Клиент MadelineProto.
 */
final class MadelineClient
{
    private const STALE_LOCK_FILES = [
        'ipc',
        'callback.ipc',
        'ipcState.php',
        'lock',
        'lightState.php.lock',
        'safe.php.lock',
    ];

    private int $appId;
    private string $appHash;
    private string $sessionPrefix;
    private int $staleLockSeconds;

    private ?API $api = null;
    private ?string $sessionName = null;

    /** @var callable(string): void|null */
    private $logger = null;

    public function __construct(?int $appId = null, ?string $appHash = null)
    {
        $this->appId = $appId ?? (int) getenv('TG_APP_ID');
        $this->appHash = $appHash !== null && trim($appHash) !== ''
            ? trim($appHash)
            : trim((string) getenv('TG_APP_HASH'));

        $this->sessionPrefix = trim((string) (getenv('MADELINE_SESSION_PREFIX') ?: 'session_'));
        $this->staleLockSeconds = (int) (getenv('MADELINE_STALE_LOCK_SECONDS') ?: 60);
    }

    /** @param callable(string): void|null $logger */
    public function logger(?callable $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function appId(): int
    {
        return $this->appId;
    }

    public function appHash(): string
    {
        return $this->appHash;
    }

    public function api(): ?API
    {
        return $this->api;
    }

    public function isStarted(): bool
    {
        return $this->api !== null;
    }

    public function sessionName(int|string $sessionUserId): string
    {
        return $this->sessionPrefix . $sessionUserId;
    }

    /**
     * Создаёт API-инстанс для сессии (без start()).
     * Полезно, когда нужен «сырой» MadelineProto (например, интерактивный логин).
     */
    public function connect(int|string $sessionUserId): API
    {
        $this->sessionName = $this->sessionName($sessionUserId);

        // Чистим зависшие lock/socket файлы от упавшего IPC-воркера,
        // чтобы start() не завис навсегда, ожидая мёртвый процесс.
        $this->cleanStaleLocks($this->sessionName, $this->staleLockSeconds);

        $settings = new Settings();

        $appInfo = new AppInfo();
        $appInfo->setApiId($this->appId);
        $appInfo->setApiHash($this->appHash);
        $settings->setAppInfo($appInfo);

        $settings->getLogger()->setLevel(Logger::LEVEL_ERROR);

        $this->api = new API($this->sessionName, $settings);

        return $this->api;
    }

    /** Создаёт сессию и запускает её (start() при первом входе — интерактивный). */
    public function start(int|string $sessionUserId): API
    {
        $api = $this->api ?? $this->connect($sessionUserId);
        $api->start();

        return $api;
    }

    /**
     * Гарантирует запущенную сессию; при первом старте прогревает peer-базу.
     */
    public function ensureStarted(int|string $sessionUserId, int|string|null $peer = null): void
    {
        if ($this->api !== null) {
            return;
        }

        $this->start($sessionUserId);

        if ($peer !== null) {
            $this->warmPeer($peer);
        }
    }

    /**
     * Прогревает peer-базу: если getInfo() не знает peer (новый чат/группа),
     * тянет полный список диалогов и пробует снова.
     */
    public function warmPeer(int|string $peer): void
    {
        $api = $this->requireApi();

        try {
            $api->getInfo($peer);
        } catch (\Throwable $e) {
            $this->log(sprintf('getInfo(%s) failed: %s — прогреваю getDialogIds()', $peer, $e->getMessage()));
            $api->getDialogIds();
        }
    }

    /** @param array<string, mixed> $media @return array<string, mixed> */
    public function sendMedia(int|string $peer, array $media): array
    {
        return $this->requireApi()->messages->sendMedia(peer: $peer, media: $media);
    }

    /** @param array<string, mixed> $media @return array<string, mixed> */
    public function editMessage(int|string $peer, int $messageId, array $media): array
    {
        return $this->requireApi()->messages->editMessage(peer: $peer, id: $messageId, media: $media);
    }

    /**
     * Отправляет/редактирует нативный todo-чек-лист (inputMediaTodo).
     *
     * @param list<array<string, mixed>> $entries элементы вида ['text' => '...'] (+ прочие поля)
     */
    public function sendTodoList(
        int|string $peer,
        array $entries,
        ?int $replyToMessageId = null,
        string $title = '📋 Список задач',
    ): bool {
        $media = $this->todoMedia($this->formatTodoEntries($entries), $title);

        $result = $replyToMessageId !== null
            ? $this->editMessage($peer, $replyToMessageId, $media)
            : $this->sendMedia($peer, $media);

        return !empty($result['updates'][0]['message']['id']) // edit
            || !empty($result['updates'][0]['id']);            // new
    }

    /**
     * Удаляет зависшие lock/ipc-socket файлы сессии, оставшиеся от аварийно
     * завершившегося IPC-воркера. Не трогает safe.php/lightState.php —
     * только служебные файлы, которые пересоздаются автоматически.
     */
    public function cleanStaleLocks(string $sessionDir, ?int $staleAfterSeconds = null): void
    {
        if (!is_dir($sessionDir)) {
            return;
        }

        $staleAfterSeconds ??= $this->staleLockSeconds;
        $now = time();
        $removed = [];

        foreach (self::STALE_LOCK_FILES as $name) {
            $file = $sessionDir . '/' . $name;
            if (!file_exists($file)) {
                continue;
            }

            $mtime = @filemtime($file);
            if ($mtime !== false && ($now - $mtime) > $staleAfterSeconds && @unlink($file)) {
                $removed[] = $name;
            }
        }

        if ($removed !== []) {
            $this->log('removed stale ipc/lock files: ' . implode(', ', $removed));
        }
    }

    // ----------------------------------------------------------------

    /** @param list<array<string, mixed>> $entries @return list<array<string, mixed>> */
    private function formatTodoEntries(array $entries): array
    {
        $formatted = [];

        foreach ($entries as $entry) {
            $entry['_'] = 'todoItem';
            $entry['title'] = [
                '_' => 'textWithEntities',
                'text' => $entry['text'] ?? '',
                'entities' => [],
            ];
            $formatted[] = $entry;
        }

        return $formatted;
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    private function todoMedia(array $entries, string $title): array
    {
        return [
            '_' => 'inputMediaTodo',
            'todo' => [
                '_' => 'todoList',
                'title' => [
                    '_' => 'textWithEntities',
                    'text' => $title,
                    'entities' => [],
                ],
                'list' => $entries,
                'others_can_append' => true,
                'others_can_complete' => true,
            ],
        ];
    }

    private function requireApi(): API
    {
        if ($this->api === null) {
            throw new MadelineException('Сессия MadelineProto не запущена: вызовите start()/ensureStarted().');
        }

        return $this->api;
    }

    private function log(string $message): void
    {
        if ($this->logger !== null) {
            ($this->logger)($message);
        }
    }
}
