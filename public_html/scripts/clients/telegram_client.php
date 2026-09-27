<?php

declare(strict_types=1);

require_once __DIR__ . '/../_env.php';

/**
 * TelegramClient — единый клиент Telegram Bot API для скриптов.
 *
 * Оборачивает вызовы https://api.telegram.org/bot<token>/<method>, сам подставляет
 * прокси (через tgProxyCurlOptionsForUrl из _env.php), повторяет запросы при
 * 408/429/5xx с учётом retry_after и умеет логировать обмен.
 *
 * Пример:
 *   require_once __DIR__ . '/clients/telegram_client.php';
 *
 *   $tg = new TelegramClient(getenv('TG_TOKEN_STAT'));
 *   $tg->sendMessage($chatId, 'Привет', 'Markdown');
 *
 *   $res = $tg->sendMessage($chatId, 'Привет');   // TelegramResult
 *   $res->ok();
 *
 * Переменные окружения:
 *   TG_TOKEN           — токен по умолчанию, если не передан в конструктор
 *   TG_TIMEOUT         — общий таймаут запроса, сек (по умолчанию 15)
 *   TG_CONNECT_TIMEOUT — таймаут соединения, сек (по умолчанию 10)
 *   TG_MAX_RETRIES     — повторы при 408/429/5xx (по умолчанию 2)
 *   TG_PROXY           — прокси для api.telegram.org (см. _env.php)
 *   LOG_TELEGRAM, TELEGRAM_LOG_FILE — логирование обмена (по умолчанию выкл.)
 */
final class TelegramException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $status = null,
        private readonly ?string $body = null,
    ) {
        parent::__construct($message, $status ?? 0);
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }
}

/**
 * Разобранный ответ Telegram (envelope { ok, result, description, error_code }).
 */
final class TelegramResult
{
    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data)
    {
    }

    public function ok(): bool
    {
        return ($this->data['ok'] ?? false) === true;
    }

    public function result(): mixed
    {
        return $this->data['result'] ?? null;
    }

    public function description(): ?string
    {
        $description = $this->data['description'] ?? null;

        return is_string($description) ? $description : null;
    }

    public function errorCode(): ?int
    {
        $code = $this->data['error_code'] ?? null;

        return is_numeric($code) ? (int) $code : null;
    }

    /** Сколько секунд просит подождать Telegram при 429. */
    public function retryAfter(): ?int
    {
        $retryAfter = $this->data['parameters']['retry_after'] ?? null;

        return is_numeric($retryAfter) ? (int) $retryAfter : null;
    }

    public function messageId(): ?int
    {
        $result = $this->data['result'] ?? null;

        return is_array($result) && isset($result['message_id']) ? (int) $result['message_id'] : null;
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->data;
    }
}

/**
 * Клиент Telegram Bot API.
 */
final class TelegramClient
{
    private const RETRY_STATUSES = [408, 409, 429, 500, 502, 503, 504, 529];

    private ?string $token;
    private float $timeout;
    private int $connectTimeout;
    private int $maxRetries;
    private bool $log;
    private string $logFile;

    public function __construct(?string $token = null)
    {
        $this->token = $token !== null && trim($token) !== '' ? trim($token) : null;

        $this->timeout = (float) (getenv('TG_TIMEOUT') ?: 15);
        $this->connectTimeout = (int) (getenv('TG_CONNECT_TIMEOUT') ?: 10);
        $this->maxRetries = (int) (getenv('TG_MAX_RETRIES') ?: 2);

        $this->log = getenv('LOG_TELEGRAM') === 'true';
        $this->logFile = (string) (getenv('TELEGRAM_LOG_FILE') ?: (__DIR__ . '/../telegram_debug.log'));
    }

    public function token(string $token): self
    {
        $this->token = trim($token);

        return $this;
    }

    public function timeout(float $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function retries(int $maxRetries): self
    {
        $this->maxRetries = max(0, $maxRetries);

        return $this;
    }

    // ----------------------------------------------------------------
    // Методы Bot API
    // ----------------------------------------------------------------

    /** @param array<string, mixed> $params */
    public function call(string $method, array $params = []): TelegramResult
    {
        $url = $this->url($method);

        $json = $params === []
            ? null
            : json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        [$status, $body, $error, $decoded] = $this->requestWithRetries($url, $json);

        $this->log(sprintf(
            ">>> TO TELEGRAM [%s]\n%s\n<<< FROM TELEGRAM [HTTP %d]%s\n%s",
            $method,
            $json ?? '(no body)',
            $status,
            $error !== null ? ' cURL: ' . $error : '',
            $body
        ));

        if ($error !== null) {
            throw new TelegramException('Ошибка соединения с Telegram: ' . $error, null, $body === '' ? null : $body);
        }

        if (!is_array($decoded)) {
            throw new TelegramException('Некорректный ответ Telegram.', $status, $body);
        }

        return new TelegramResult($decoded);
    }

    /** @param array<string, mixed> $extra дополнительные поля payload */
    public function sendMessage(
        int|string $chatId,
        string $text,
        ?string $parseMode = null,
        ?array $replyMarkup = null,
        array $extra = [],
    ): TelegramResult {
        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
        ], $extra);

        if ($parseMode !== null && $parseMode !== '') {
            $params['parse_mode'] = $parseMode;
        }
        if ($replyMarkup !== null) {
            $params['reply_markup'] = $replyMarkup;
        }

        return $this->call('sendMessage', $params);
    }

    /** @param array<string, mixed> $extra */
    public function editMessageText(
        int|string $chatId,
        int $messageId,
        string $text,
        ?string $parseMode = null,
        ?array $replyMarkup = null,
        array $extra = [],
    ): TelegramResult {
        $params = array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ], $extra);

        if ($parseMode !== null && $parseMode !== '') {
            $params['parse_mode'] = $parseMode;
        }
        if ($replyMarkup !== null) {
            $params['reply_markup'] = $replyMarkup;
        }

        return $this->call('editMessageText', $params);
    }

    public function deleteMessage(int|string $chatId, int $messageId): TelegramResult
    {
        return $this->call('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    public function sendChatAction(int|string $chatId, string $action): TelegramResult
    {
        return $this->call('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action,
        ]);
    }

    /** @param array<string, mixed> $params */
    public function answerCallbackQuery(string $callbackQueryId, array $params = []): TelegramResult
    {
        return $this->call('answerCallbackQuery', array_merge(['callback_query_id' => $callbackQueryId], $params));
    }

    /** @param list<array<string, mixed>> $results @param array<string, mixed> $params */
    public function answerInlineQuery(string $inlineQueryId, array $results, array $params = []): TelegramResult
    {
        return $this->call('answerInlineQuery', array_merge([
            'inline_query_id' => $inlineQueryId,
            'results' => $results,
        ], $params));
    }

    /** Возвращает file_path из getFile или null при ошибке. */
    public function getFile(string $fileId): ?string
    {
        $result = $this->call('getFile', ['file_id' => $fileId])->result();

        return is_array($result) && isset($result['file_path']) ? (string) $result['file_path'] : null;
    }

    public function fileUrl(string $filePath): string
    {
        return 'https://api.telegram.org/file/bot' . $this->resolvedToken() . '/' . $filePath;
    }

    /** Скачивает файл по file_path и возвращает его содержимое или null. */
    public function downloadFile(string $filePath): ?string
    {
        $url = $this->fileUrl($filePath);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => max(1, (int) ceil($this->timeout)),
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
        ]);
        curl_setopt_array($ch, tgProxyCurlOptionsForUrl($url));

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $this->log(sprintf(
            ">>> TO TELEGRAM FILE [%s]\n<<< FROM TELEGRAM FILE [HTTP %d]%s",
            $url,
            $status,
            $error !== '' ? ' cURL: ' . $error : ''
        ));

        if (!is_string($body) || $body === '' || $error !== '') {
            return null;
        }

        return $body;
    }

    /** @param array<string, mixed> $params @return list<array<string, mixed>> */
    public function getUpdates(array $params = []): array
    {
        $result = $this->call('getUpdates', $params)->result();

        return is_array($result) ? array_values($result) : [];
    }

    /** @return array<string, mixed> */
    public function getWebhookInfo(): array
    {
        $result = $this->call('getWebhookInfo')->result();

        return is_array($result) ? $result : [];
    }

    /** @param array<string, mixed> $params */
    public function setWebhook(string $url, array $params = []): TelegramResult
    {
        return $this->call('setWebhook', array_merge(['url' => $url], $params));
    }

    /** @param array<string, mixed> $params */
    public function deleteWebhook(array $params = []): TelegramResult
    {
        return $this->call('deleteWebhook', $params);
    }

    // ----------------------------------------------------------------
    // Транспорт
    // ----------------------------------------------------------------

    private function resolvedToken(): string
    {
        if ($this->token !== null && trim($this->token) !== '') {
            return $this->token;
        }

        $fromEnv = getenv('TG_TOKEN');
        if ($fromEnv !== false && trim($fromEnv) !== '') {
            return trim($fromEnv);
        }

        throw new TelegramException('Не задан токен Telegram (передайте его в конструктор или задайте TG_TOKEN).');
    }

    private function url(string $method): string
    {
        return 'https://api.telegram.org/bot' . $this->resolvedToken() . '/' . $method;
    }

    /**
     * @return array{0: int, 1: string, 2: ?string, 3: mixed}
     */
    private function requestWithRetries(string $url, ?string $json): array
    {
        $attempt = 0;
        $backoff = 0.5;

        while (true) {
            $result = $this->request($url, $json);
            [$status, , $error, $decoded] = $result;

            $retryable = $error !== null || in_array($status, self::RETRY_STATUSES, true);

            if (!$retryable || $attempt >= $this->maxRetries) {
                return $result;
            }

            $retryAfter = is_array($decoded) ? ($decoded['parameters']['retry_after'] ?? null) : null;
            $delay = is_numeric($retryAfter) ? min((float) $retryAfter, 30.0) : $this->retryDelay($backoff);

            usleep((int) round($delay * 1_000_000));
            $backoff = min($backoff * 2, 5.0);
            $attempt++;
        }
    }

    private function retryDelay(float $backoff): float
    {
        return $backoff * (1 - (mt_rand(0, 250) / 1000));
    }

    /**
     * @return array{0: int, 1: string, 2: ?string, 3: mixed}
     */
    private function request(string $url, ?string $json): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => $json !== null,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_TIMEOUT => max(1, (int) ceil($this->timeout)),
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
        ]);

        if ($json !== null) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        curl_setopt_array($ch, tgProxyCurlOptionsForUrl($url));

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $bodyString = is_string($body) ? $body : '';
        $decoded = $bodyString === '' ? null : json_decode($bodyString, true);

        return [$status, $bodyString, $error !== '' ? $error : null, $decoded];
    }

    private function log(string $message): void
    {
        if (!$this->log) {
            return;
        }

        file_put_contents(
            $this->logFile,
            sprintf("=== %s ===\n%s\n\n", date('Y-m-d H:i:s'), $message),
            FILE_APPEND
        );
    }
}
