<?php

declare(strict_types=1);

require_once __DIR__ . '/../_env.php';

/**
 * TranscriptionClient — клиент распознавания речи (STT) с переключаемым провайдером.
 *
 * На вход подаётся бинарное аудио (например, скачанное голосовое из Telegram),
 * на выход — текст. Поддерживаются два маршрута:
 *   - cloudflare : Cloudflare Workers AI @cf/openai/whisper-large-v3-turbo (по умолчанию)
 *   - openrouter : OpenRouter audio/transcriptions (openai/whisper-large-v3-turbo)
 *
 * Есть режим фолбэка: transcribeWithFallback() пробует провайдеров по порядку
 * (primary + STT_FALLBACK) и возвращает первый успешный результат.
 *
 * Пример:
 *   require_once __DIR__ . '/clients/transcription_client.php';
 *
 *   $stt = new TranscriptionClient();
 *   $res = $stt->transcribeWithFallback($audioData);   // ?TranscriptionResult
 *   echo $res?->text();
 *
 * Переменные окружения:
 *   STT_PROVIDER           cloudflare|openrouter   (по умолчанию cloudflare)
 *   STT_FALLBACK           провайдер(ы) фолбэка через запятую (по умолчанию openrouter)
 *   STT_LANGUAGE           язык распознавания (по умолчанию ru)
 *   STT_TIMEOUT            общий таймаут запроса, сек (по умолчанию 60)
 *   STT_CONNECT_TIMEOUT    таймаут соединения, сек (по умолчанию 15)
 *   STT_MAX_RETRIES        повторы при 408/429/5xx (по умолчанию 2)
 *   CLOUDFLARE_ACCOUNT_ID, CLOUDFLARE_API_TOKEN — доступ к Cloudflare
 *   OPENROUTER_KEY | OPENROUTER_API_KEY — доступ к OpenRouter
 *   STT_CLOUDFLARE_MODEL / STT_OPENROUTER_MODEL — переопределение модели
 *   LOG_STT, STT_LOG_FILE — логирование запросов/ответов (по умолчанию выкл.)
 */
final class TranscriptionException extends RuntimeException
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
 * Результат распознавания речи.
 */
final class TranscriptionResult
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $usage
     */
    public function __construct(
        private readonly string $text,
        private readonly string $provider,
        private readonly string $model,
        private readonly float $duration,
        private readonly ?float $cost = null,
        private readonly array $usage = [],
        private readonly array $data = [],
    ) {
    }

    public function text(): string
    {
        return $this->text;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function model(): string
    {
        return $this->model;
    }

    /** Длительность запроса в секундах. */
    public function duration(): float
    {
        return $this->duration;
    }

    /** Стоимость в USD; null — провайдер не сообщил цену (так делает Cloudflare). */
    public function cost(): ?float
    {
        return $this->cost;
    }

    /** @return array<string, mixed> */
    public function usage(): array
    {
        return $this->usage;
    }

    /** @return array<string, mixed> */
    public function raw(): array
    {
        return $this->data;
    }
}

/**
 * Клиент распознавания речи (Cloudflare Workers AI или OpenRouter Whisper).
 */
final class TranscriptionClient
{
    public const CLOUDFLARE = 'cloudflare';
    public const OPENROUTER = 'openrouter';

    /**
     * @var array<string, array{
     *     kind: string,
     *     key_env: list<string>,
     *     model_env: string,
     *     default_model: string,
     *     account_env?: string
     * }>
     */
    private const PROVIDERS = [
        self::CLOUDFLARE => [
            'kind' => 'cloudflare',
            'key_env' => ['CLOUDFLARE_API_TOKEN'],
            'model_env' => 'STT_CLOUDFLARE_MODEL',
            'default_model' => '@cf/openai/whisper-large-v3-turbo',
            'account_env' => 'CLOUDFLARE_ACCOUNT_ID',
        ],
        self::OPENROUTER => [
            'kind' => 'openrouter',
            'key_env' => ['OPENROUTER_KEY', 'OPENROUTER_API_KEY'],
            'model_env' => 'STT_OPENROUTER_MODEL',
            'default_model' => 'openai/whisper-large-v3-turbo',
        ],
    ];

    private const RETRY_STATUSES = [408, 409, 429, 500, 502, 503, 504, 529];

    private string $provider;
    /** @var list<string> */
    private array $fallback;

    /** @var array<string, string> */
    private array $keys = [];
    /** @var array<string, string> */
    private array $models = [];

    private string $language;
    private float $timeout;
    private int $connectTimeout;
    private int $maxRetries;

    private bool $log;
    private string $logFile;

    public function __construct(?string $provider = null)
    {
        $this->provider = self::normalizeProvider(
            $provider ?? (string) (getenv('STT_PROVIDER') ?: self::CLOUDFLARE)
        );
        $this->fallback = self::parseFallback((string) (getenv('STT_FALLBACK') ?: self::OPENROUTER));

        $this->language = trim((string) (getenv('STT_LANGUAGE') ?: 'ru'));
        $this->timeout = (float) (getenv('STT_TIMEOUT') ?: 60);
        $this->connectTimeout = (int) (getenv('STT_CONNECT_TIMEOUT') ?: 15);
        $this->maxRetries = (int) (getenv('STT_MAX_RETRIES') ?: 2);

        $this->log = getenv('LOG_STT') === 'true';
        $this->logFile = (string) (getenv('STT_LOG_FILE') ?: (__DIR__ . '/../stt_debug.log'));
    }

    public function useProvider(string $provider): self
    {
        $this->provider = self::normalizeProvider($provider);

        return $this;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    /** @param string|list<string> $providers */
    public function fallback(string|array $providers): self
    {
        $this->fallback = array_values(array_filter(
            array_map([self::class, 'normalizeProvider'], (array) $providers),
            static fn (string $p): bool => $p !== $this->provider
        ));

        return $this;
    }

    public function language(string $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function model(string $model): self
    {
        $this->models[$this->provider] = $model;

        return $this;
    }

    public function apiKey(string $apiKey): self
    {
        $this->keys[$this->provider] = $apiKey;

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

    /** Распознаёт аудио текущим провайдером (бросает TranscriptionException при ошибке). */
    public function transcribe(string $audioData, ?string $language = null): TranscriptionResult
    {
        if ($audioData === '') {
            throw new TranscriptionException('Пустые аудиоданные.');
        }

        return match (self::PROVIDERS[$this->provider]['kind']) {
            'cloudflare' => $this->transcribeCloudflare($audioData, $language ?? $this->language),
            'openrouter' => $this->transcribeOpenRouter($audioData, $language ?? $this->language),
        };
    }

    /**
     * Пробует провайдеров по порядку (текущий + fallback) и возвращает первый
     * успешный результат, либо null, если все не сработали.
     */
    public function transcribeWithFallback(string $audioData, ?string $language = null): ?TranscriptionResult
    {
        $chain = array_merge([$this->provider], $this->fallback);

        foreach ($chain as $provider) {
            try {
                return $this->useProvider($provider)->transcribe($audioData, $language);
            } catch (TranscriptionException) {
                // пробуем следующего провайдера
            }
        }

        return null;
    }

    // ----------------------------------------------------------------
    // Провайдеры
    // ----------------------------------------------------------------

    private function transcribeCloudflare(string $audioData, string $language): TranscriptionResult
    {
        $account = trim((string) getenv(self::PROVIDERS[self::CLOUDFLARE]['account_env']));
        if ($account === '') {
            throw new TranscriptionException('Не задан CLOUDFLARE_ACCOUNT_ID.');
        }

        $key = $this->resolvedKey(self::CLOUDFLARE);
        $model = $this->resolvedModel(self::CLOUDFLARE);

        $url = 'https://api.cloudflare.com/client/v4/accounts/' . rawurlencode($account)
            . '/ai/run/' . $model;

        $payload = ['audio' => base64_encode($audioData)];
        if ($language !== '') {
            $payload['language'] = $language;
        }

        $start = microtime(true);
        $response = $this->request($url, $payload, ['Authorization: Bearer ' . $key]);
        $duration = round(microtime(true) - $start, 2);

        $data = $this->decode($response['body'], $response['status']);
        $text = trim((string) ($data['result']['text'] ?? ''));

        if (($data['success'] ?? false) !== true || $text === '') {
            throw new TranscriptionException('Cloudflare не вернул текст распознавания.', $response['status'], $response['body']);
        }

        return new TranscriptionResult($text, self::CLOUDFLARE, $model, $duration, null, [], $data);
    }

    private function transcribeOpenRouter(string $audioData, string $language): TranscriptionResult
    {
        $key = $this->resolvedKey(self::OPENROUTER);
        $model = $this->resolvedModel(self::OPENROUTER);

        $payload = [
            'model' => $model,
            'input_audio' => [
                'data' => base64_encode($audioData),
                'format' => self::audioFormat($audioData),
            ],
        ];
        if ($language !== '') {
            $payload['language'] = $language;
        }

        $start = microtime(true);
        $response = $this->request('https://openrouter.ai/api/v1/audio/transcriptions', $payload, ['Authorization: Bearer ' . $key]);
        $duration = round(microtime(true) - $start, 2);

        $data = $this->decode($response['body'], $response['status']);
        $text = trim((string) ($data['text'] ?? ''));

        if ($text === '') {
            throw new TranscriptionException('OpenRouter не вернул текст распознавания.', $response['status'], $response['body']);
        }

        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];
        $cost = is_numeric($usage['cost'] ?? null) ? (float) $usage['cost'] : null;

        return new TranscriptionResult($text, self::OPENROUTER, $model, $duration, $cost, $usage, $data);
    }

    // ----------------------------------------------------------------
    // Разрешение параметров
    // ----------------------------------------------------------------

    /** @return list<string> */
    private static function parseFallback(string $value): array
    {
        $providers = [];

        foreach (explode(',', $value) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $providers[] = self::normalizeProvider($part);
        }

        return array_values(array_unique($providers));
    }

    private static function normalizeProvider(string $provider): string
    {
        $normalized = match (strtolower(trim($provider))) {
            'cloudflare', 'cf' => self::CLOUDFLARE,
            'openrouter', 'open_router', 'or' => self::OPENROUTER,
            default => '',
        };

        if (!isset(self::PROVIDERS[$normalized])) {
            throw new TranscriptionException(sprintf(
                'Неизвестный провайдер STT: "%s" (доступны: %s).',
                $provider,
                implode(', ', array_keys(self::PROVIDERS))
            ));
        }

        return $normalized;
    }

    private function resolvedKey(string $provider): string
    {
        $key = $this->keys[$provider] ?? null;

        if ($key === null || trim($key) === '') {
            foreach (self::PROVIDERS[$provider]['key_env'] as $envName) {
                $value = getenv($envName);
                if ($value !== false && trim($value) !== '') {
                    $key = $value;
                    break;
                }
            }
        }

        if ($key === null || trim($key) === '') {
            throw new TranscriptionException(sprintf(
                'Не задан ключ провайдера STT "%s" (ожидается %s).',
                $provider,
                implode(' или ', self::PROVIDERS[$provider]['key_env'])
            ));
        }

        return trim($key);
    }

    private function resolvedModel(string $provider): string
    {
        if (isset($this->models[$provider]) && trim($this->models[$provider]) !== '') {
            return trim($this->models[$provider]);
        }

        $fromEnv = getenv(self::PROVIDERS[$provider]['model_env']);

        return $fromEnv !== false && trim($fromEnv) !== ''
            ? trim($fromEnv)
            : self::PROVIDERS[$provider]['default_model'];
    }

    private static function audioFormat(string $audioData): string
    {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($audioData);

        return (is_string($mime) && (str_contains($mime, 'mpeg') || str_contains($mime, 'mp3'))) ? 'mp3' : 'ogg';
    }

    /** @return array<string, mixed> */
    private function decode(string $body, int $status): array
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new TranscriptionException('Некорректный JSON в ответе STT: ' . $exception->getMessage(), $status, $body);
        }

        if (!is_array($data)) {
            throw new TranscriptionException('Некорректный ответ STT.', $status, $body);
        }

        return $data;
    }

    // ----------------------------------------------------------------
    // Транспорт
    // ----------------------------------------------------------------

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $headers
     * @return array{status: int, body: string}
     */
    private function request(string $url, array $payload, array $headers): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $headers = array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers);

        $attempt = 0;
        $backoff = 0.5;

        while (true) {
            [$status, $body, $error] = $this->post($url, $json, $headers);

            $this->log(sprintf(
                ">>> TO STT [%s / %s]\n<<< FROM STT [HTTP %d]%s\n%s",
                $this->provider,
                $this->resolvedModel($this->provider),
                $status,
                $error !== null ? ' cURL: ' . $error : '',
                $body
            ));

            $retryable = $error !== null || in_array($status, self::RETRY_STATUSES, true);
            if (!$retryable || $attempt >= $this->maxRetries) {
                if ($error !== null) {
                    throw new TranscriptionException('Ошибка соединения со STT: ' . $error, null, $body === '' ? null : $body);
                }

                return ['status' => $status, 'body' => $body];
            }

            usleep((int) round($backoff * (1 - (mt_rand(0, 250) / 1000)) * 1_000_000));
            $backoff = min($backoff * 2, 5.0);
            $attempt++;
        }
    }

    /**
     * @param list<string> $headers
     * @return array{0: int, 1: string, 2: ?string}
     */
    private function post(string $url, string $json, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => max(1, (int) ceil($this->timeout)),
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return [$status, is_string($body) ? $body : '', $error !== '' ? $error : null];
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
