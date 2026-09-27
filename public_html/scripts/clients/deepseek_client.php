<?php

declare(strict_types=1);

require_once __DIR__ . '/../_env.php';

/**
 * DeepSeekClient — клиент прямых запросов к DeepSeek.
 *
 * DeepSeek — обычная чат-модель с OpenAI-совместимым API, поэтому «сообщение»
 * здесь — это массив messages (system / user / assistant), а ответ приходит в
 * choices[0].message.content. Кроме чата поддерживается запрос баланса
 * (/user/balance), который используется в ai_balances_stat.php.
 *
 * Пример:
 *   require_once __DIR__ . '/clients/deepseek_client.php';
 *
 *   $ds = new DeepSeekClient();
 *
 *   $res = $ds->system('Ты — краткий ассистент.')
 *             ->user('Сколько будет 2+2?')
 *             ->send();
 *
 *   echo $res->content();                  // '4'
 *   echo $res->totalTokens();              // расход токенов
 *
 *   // Или одной строкой:
 *   echo (new DeepSeekClient())->ask('Привет!', 'Отвечай по-русски.');
 *
 *   // Баланс:
 *   $balance = (new DeepSeekClient())->balance();
 *
 * Переменные окружения (читаются через _env.php, файл .env в корне проекта):
 *   DEEPSEEK_MODEL         модель (по умолчанию deepseek-flash)
 *   DEEPSEEK_TIMEOUT       общий таймаут запроса, сек (по умолчанию 60)
 *   DEEPSEEK_CONNECT_TIMEOUT  таймаут соединения, сек (по умолчанию 10)
 *   DEEPSEEK_MAX_RETRIES   повторы при 408/429/5xx, по умолчанию 2
 *   DEEPSEEK_URL           переопределение эндпоинта чата
 *   DEEPSEEK_BALANCE_URL   переопределение эндпоинта баланса
 *   DEEPSEEK_KEY           API-ключ
 *   LOG_DEEPSEEK, DEEPSEEK_LOG_FILE — логирование запросов/ответов (по умолчанию выкл.)
 */
final class DeepSeekException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $status = null,
        private readonly ?string $body = null,
        private readonly ?string $requestId = null,
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

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}

/**
 * Разобранный ответ DeepSeek с удобными аксессорами по первому choice.
 */
final class DeepSeekResult
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers нормализованные заголовки (lowercase)
     */
    public function __construct(
        private readonly array $data,
        private readonly string $provider,
        private readonly array $headers = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->data;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function id(): ?string
    {
        $id = $this->data['id'] ?? null;

        return is_string($id) ? $id : null;
    }

    public function model(): ?string
    {
        $model = $this->data['model'] ?? null;

        return is_string($model) ? $model : null;
    }

    /** @return list<array<string, mixed>> */
    public function choices(): array
    {
        $choices = $this->data['choices'] ?? [];

        return is_array($choices) ? array_values($choices) : [];
    }

    /** @return array<string, mixed>|null */
    public function choice(int $index = 0): ?array
    {
        $choice = $this->choices()[$index] ?? null;

        return is_array($choice) ? $choice : null;
    }

    /** Текст ответа (choices[$index].message.content). */
    public function content(int $index = 0): ?string
    {
        $content = $this->choice($index)['message']['content'] ?? null;

        return is_string($content) ? $content : null;
    }

    /** Цепочка рассуждений (deepseek-reasoner), если модель её вернула. */
    public function reasoning(int $index = 0): ?string
    {
        $reasoning = $this->choice($index)['message']['reasoning_content'] ?? null;

        return is_string($reasoning) ? $reasoning : null;
    }

    public function finishReason(int $index = 0): ?string
    {
        $reason = $this->choice($index)['finish_reason'] ?? null;

        return is_string($reason) ? $reason : null;
    }

    /** @return array<string, mixed> */
    public function usage(): array
    {
        $usage = $this->data['usage'] ?? [];

        return is_array($usage) ? $usage : [];
    }

    public function promptTokens(): int
    {
        return (int) ($this->usage()['prompt_tokens'] ?? 0);
    }

    public function completionTokens(): int
    {
        return (int) ($this->usage()['completion_tokens'] ?? 0);
    }

    public function totalTokens(): int
    {
        $usage = $this->usage();

        if (isset($usage['total_tokens'])) {
            return (int) $usage['total_tokens'];
        }

        return $this->promptTokens() + $this->completionTokens();
    }

    /** Токены, взятые из кеша промпта (DeepSeek). */
    public function cacheHitTokens(): int
    {
        return (int) ($this->usage()['prompt_cache_hit_tokens'] ?? 0);
    }

    /** Токены промпта, не попавшие в кеш (DeepSeek). */
    public function cacheMissTokens(): int
    {
        return (int) ($this->usage()['prompt_cache_miss_tokens'] ?? 0);
    }

    /** Стоимость в USD; null — провайдер не сообщил цену (так делает DeepSeek). */
    public function cost(): ?float
    {
        $cost = $this->usage()['cost'] ?? null;

        return is_numeric($cost) ? (float) $cost : null;
    }

    public function requestId(): ?string
    {
        foreach (['x-request-id', 'x-generation-id'] as $header) {
            if (!empty($this->headers[$header])) {
                return $this->headers[$header];
            }
        }

        return $this->id();
    }
}

/**
 * Клиент прямых запросов к DeepSeek (OpenAI-совместимый API).
 */
final class DeepSeekClient
{
    public const DEEPSEEK = 'deepseek';

    private const DEFAULT_ENDPOINT = 'https://api.deepseek.com/chat/completions';
    private const DEFAULT_BALANCE_ENDPOINT = 'https://api.deepseek.com/user/balance';
    private const DEFAULT_MODEL = 'deepseek-flash';

    private const RETRY_STATUSES = [408, 409, 429, 500, 502, 503, 504, 529];

    private ?string $apiKey;
    private ?string $model;
    private ?string $endpoint = null;

    /** @var list<array{role: string, content: string}> */
    private array $messages = [];

    /** @var array<string, mixed> */
    private array $options = [];

    /** @var list<string> */
    private array $extraHeaders = [];

    private float $timeout;
    private int $connectTimeout;
    private int $maxRetries;

    private bool $log;
    private string $logFile;

    public function __construct(
        ?string $apiKey = null,
        ?string $provider = null,
        ?string $model = null,
    ) {
        // $provider оставлен для обратной совместимости: поддерживается только прямой DeepSeek.
        if ($provider !== null && !in_array(strtolower(trim($provider)), ['', self::DEEPSEEK, 'ds'], true)) {
            throw new DeepSeekException(sprintf(
                'Провайдер "%s" не поддерживается: DeepSeekClient работает только напрямую с DeepSeek.',
                $provider
            ));
        }

        $this->apiKey = $apiKey;
        $this->model = $model;

        $this->timeout = (float) (getenv('DEEPSEEK_TIMEOUT') ?: 60);
        $this->connectTimeout = (int) (getenv('DEEPSEEK_CONNECT_TIMEOUT') ?: 10);
        $this->maxRetries = (int) (getenv('DEEPSEEK_MAX_RETRIES') ?: 2);

        $this->log = getenv('LOG_DEEPSEEK') === 'true';
        $this->logFile = (string) (getenv('DEEPSEEK_LOG_FILE') ?: (__DIR__ . '/../deepseek_debug.log'));
    }

    public static function deepSeek(?string $apiKey = null, ?string $model = null): self
    {
        return new self($apiKey, self::DEEPSEEK, $model);
    }

    public function provider(): string
    {
        return self::DEEPSEEK;
    }

    // ----------------------------------------------------------------
    // Построение запроса
    // ----------------------------------------------------------------

    public function system(string $content): self
    {
        return $this->message('system', $content);
    }

    public function user(string $content): self
    {
        return $this->message('user', $content);
    }

    public function assistant(string $content): self
    {
        return $this->message('assistant', $content);
    }

    public function message(string $role, string $content): self
    {
        $this->messages[] = ['role' => $role, 'content' => $content];

        return $this;
    }

    /** @param list<array{role: string, content: string}> $messages */
    public function messages(array $messages): self
    {
        $this->messages = array_values($messages);

        return $this;
    }

    public function model(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function temperature(float $temperature): self
    {
        return $this->option('temperature', $temperature);
    }

    public function maxTokens(int $maxTokens): self
    {
        return $this->option('max_tokens', $maxTokens);
    }

    /** Просит модель вернуть строго JSON-объект. */
    public function jsonMode(): self
    {
        return $this->option('response_format', ['type' => 'json_object']);
    }

    public function option(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    /** @param array<string, mixed> $options */
    public function options(array $options): self
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    public function apiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    public function endpoint(string $endpoint): self
    {
        $this->endpoint = $endpoint;

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

    public function withHeader(string $name, string $value): self
    {
        $this->extraHeaders[] = $name . ': ' . $value;

        return $this;
    }

    /** Сбросить messages и опции (клиент остаётся настроенным). */
    public function reset(): self
    {
        $this->messages = [];
        $this->options = [];

        return $this;
    }

    // ----------------------------------------------------------------
    // Отправка
    // ----------------------------------------------------------------

    public function send(): DeepSeekResult
    {
        if ($this->messages === []) {
            throw new DeepSeekException('Не задано ни одного сообщения: system()/user()/assistant() перед send().');
        }

        $payload = array_merge([
            'model' => $this->resolvedModel(),
            'messages' => $this->messages,
            'stream' => false,
        ], $this->options);

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $headers = array_merge($this->baseHeaders(), ['Content-Type: application/json']);
        $url = $this->resolvedEndpoint();

        [$status, $body, $responseHeaders, $error] = $this->requestWithRetries('POST', $url, $json, $headers);

        $this->log(sprintf(
            ">>> TO DEEPSEEK [%s / %s]\n%s\n<<< FROM DEEPSEEK [HTTP %d]%s\n%s",
            self::DEEPSEEK,
            $this->resolvedModel(),
            $json,
            $status,
            $error !== null ? ' cURL: ' . $error : '',
            $body
        ));

        if ($error !== null) {
            throw new DeepSeekException('Ошибка соединения с DeepSeek: ' . $error, null, $body === '' ? null : $body);
        }

        if ($status < 200 || $status >= 300) {
            throw new DeepSeekException(
                sprintf('DeepSeek вернул HTTP %d', $status),
                $status,
                $body,
                $this->headerRequestId($responseHeaders)
            );
        }

        $data = $this->decode($body, $status);

        if (!isset($data['choices']) || !is_array($data['choices'])) {
            throw new DeepSeekException('В ответе DeepSeek нет поля choices.', $status, $body);
        }

        return new DeepSeekResult($data, self::DEEPSEEK, $responseHeaders);
    }

    /** Одноразовый вызов с готовым массивом messages. */
    public function chat(array $messages, array $options = []): DeepSeekResult
    {
        return $this->reset()->messages($messages)->options($options)->send();
    }

    /** Короткий запрос одним текстом: вернёт content или null. */
    public function ask(string $prompt, ?string $system = null): ?string
    {
        $this->reset();
        if ($system !== null && trim($system) !== '') {
            $this->system($system);
        }
        $this->user($prompt);

        return $this->send()->content();
    }

    /**
     * Баланс аккаунта DeepSeek.
     *
     * @return array<string, mixed>
     */
    public function balance(): array
    {
        $url = trim((string) (getenv('DEEPSEEK_BALANCE_URL') ?: self::DEFAULT_BALANCE_ENDPOINT));

        [$status, $body, $responseHeaders, $error] = $this->requestWithRetries('GET', $url, null, $this->baseHeaders());

        $this->log(sprintf(
            ">>> TO DEEPSEEK BALANCE [%s]\n<<< FROM DEEPSEEK BALANCE [HTTP %d]%s\n%s",
            $url,
            $status,
            $error !== null ? ' cURL: ' . $error : '',
            $body
        ));

        if ($error !== null) {
            throw new DeepSeekException('Ошибка соединения с DeepSeek: ' . $error, null, $body === '' ? null : $body);
        }

        if ($status < 200 || $status >= 300) {
            throw new DeepSeekException(
                sprintf('DeepSeek вернул HTTP %d', $status),
                $status,
                $body,
                $this->headerRequestId($responseHeaders)
            );
        }

        return $this->decode($body, $status);
    }

    // ----------------------------------------------------------------
    // Разрешение параметров из окружения
    // ----------------------------------------------------------------

    /** @return list<string> */
    private function baseHeaders(): array
    {
        return array_merge([
            'Accept: application/json',
            'Authorization: Bearer ' . $this->resolvedKey(),
        ], $this->extraHeaders);
    }

    private function resolvedKey(): string
    {
        $key = $this->apiKey;

        if ($key === null || trim($key) === '') {
            $value = getenv('DEEPSEEK_KEY');
            if ($value !== false && trim($value) !== '') {
                $key = $value;
            }
        }

        if ($key === null || trim($key) === '') {
            throw new DeepSeekException('Не задан API-ключ DeepSeek (ожидается DEEPSEEK_KEY).');
        }

        return trim($key);
    }

    private function resolvedModel(): string
    {
        if ($this->model !== null && trim($this->model) !== '') {
            return trim($this->model);
        }

        $fromEnv = getenv('DEEPSEEK_MODEL');

        return $fromEnv !== false && trim($fromEnv) !== ''
            ? trim($fromEnv)
            : self::DEFAULT_MODEL;
    }

    private function resolvedEndpoint(): string
    {
        if ($this->endpoint !== null && trim($this->endpoint) !== '') {
            return trim($this->endpoint);
        }

        $fromEnv = getenv('DEEPSEEK_URL');

        return $fromEnv !== false && trim($fromEnv) !== ''
            ? trim($fromEnv)
            : self::DEFAULT_ENDPOINT;
    }

    private function headerRequestId(array $responseHeaders): ?string
    {
        foreach (['x-request-id', 'x-generation-id'] as $header) {
            if (!empty($responseHeaders[$header])) {
                return $responseHeaders[$header];
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function decode(string $body, int $status): array
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new DeepSeekException('Некорректный JSON в ответе DeepSeek: ' . $exception->getMessage(), $status, $body);
        }

        if (!is_array($data)) {
            throw new DeepSeekException('Некорректный ответ DeepSeek.', $status, $body);
        }

        return $data;
    }

    // ----------------------------------------------------------------
    // Транспорт
    // ----------------------------------------------------------------

    /**
     * @param list<string> $headers
     * @return array{0: int, 1: string, 2: array<string, string>, 3: ?string}
     */
    private function requestWithRetries(string $method, string $url, ?string $json, array $headers): array
    {
        $attempt = 0;
        $backoff = 0.5;

        while (true) {
            $result = $this->request($method, $url, $json, $headers);
            [$status, , , $error] = $result;

            $retryable = $error !== null || in_array($status, self::RETRY_STATUSES, true);

            if (!$retryable || $attempt >= $this->maxRetries) {
                return $result;
            }

            usleep((int) round($this->retryDelay($result[2], $backoff) * 1_000_000));
            $backoff = min($backoff * 2, 5.0);
            $attempt++;
        }
    }

    /** @param array<string, string> $headers */
    private function retryDelay(array $headers, float $backoff): float
    {
        $retryAfter = $headers['retry-after'] ?? null;
        if ($retryAfter !== null && $retryAfter !== '') {
            $seconds = is_numeric($retryAfter)
                ? (float) $retryAfter
                : (float) (strtotime($retryAfter) - time());

            if ($seconds > 0) {
                return min($seconds, 30.0);
            }
        }

        return $backoff * (1 - (mt_rand(0, 250) / 1000));
    }

    /**
     * @param list<string> $headers
     * @return array{0: int, 1: string, 2: array<string, string>, 3: ?string}
     */
    private function request(string $method, string $url, ?string $json, array $headers): array
    {
        $responseHeaders = [];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => max(1, (int) ceil($this->timeout)),
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_HEADERFUNCTION => static function ($handle, string $header) use (&$responseHeaders): int {
                $separator = strpos($header, ':');
                if ($separator !== false) {
                    $name = strtolower(trim(substr($header, 0, $separator)));
                    $responseHeaders[$name] = trim(substr($header, $separator + 1));
                }

                return strlen($header);
            },
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return [
            $status,
            is_string($body) ? $body : '',
            $responseHeaders,
            $error !== '' ? $error : null,
        ];
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
