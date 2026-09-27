<?php

declare(strict_types=1);

require_once __DIR__ . '/../_env.php';

/**
 * JevClient — клиент TypeSafe Jev с переключаемым провайдером.
 *
 * Jev — это decision-модель TypeSafe: на вход подаётся `state` (текст или
 * структура) и набор типизированных вопросов (noul / choice / score), на выходе
 * — типизированные ответы с вероятностями. Это не чат-модель, поэтому
 * сообщение отправляется как `state`, а не как «промпт».
 *
 * Поддерживаются два маршрута, переключаемые одной строкой:
 *   - openrouter : https://openrouter.ai/api/alpha/decisions   (по умолчанию)
 *   - typesafe   : https://api.typesafe.ai/v1/systemone        (прямые запросы)
 *
 * Тела запроса и ответа у обоих маршрутов одинаковые, поэтому переключение
 * провайдера не меняет остальной код — только хост, путь и ключ.
 *
 * Пример:
 *   require_once __DIR__ . '/clients/jev_client.php';
 *
 *   $jev = new JevClient();                 // OpenRouter
 *   // $jev->useTypeSafe();                 // позже — напрямую в TypeSafe
 *
 *   $res = $jev->state('Оплата не проходит 3 дня, теряю продажи')
 *             ->choice('team', 'Какая команда должна обработать обращение?', [
 *                 'billing'   => 'Платежи и возвраты',
 *                 'technical' => 'Баги и интеграции',
 *                 'unclear'   => 'Недостаточно данных',
 *             ])
 *             ->noul('urgent', 'Клиент заблокирован прямо сейчас.')
 *             ->send();
 *
 *   echo $res->choice('team');             // 'billing'
 *   echo $res->confidence('team');         // 0.88
 *   var_dump($res->isYes('urgent', 0.9));  // bool
 *
 * Переменные окружения (читаются через _env.php, файл .env в корне проекта):
 *   JEV_PROVIDER      openrouter|typesafe   (по умолчанию openrouter)
 *   JEV_MODEL         модель, по умолчанию jev-latest (работает на обоих)
 *   JEV_TIMEOUT       общий таймаут запроса, сек (по умолчанию 30)
 *   JEV_CONNECT_TIMEOUT  таймаут соединения, сек (по умолчанию 10)
 *   JEV_MAX_RETRIES   повторы при 408/429/5xx, по умолчанию 2
 *   JEV_OPENROUTER_URL / JEV_TYPESAFE_URL — переопределение эндпоинта
 *   OPENROUTER_JEV_KEY — ключ для openrouter
 *   TYPESAFE_API_KEY — ключ для typesafe
 *   OPENROUTER_PROXY — прокси для маршрута openrouter (по умолчанию TG_PROXY)
 *   LOG_JEV, JEV_LOG_FILE — логирование запросов/ответов (по умолчанию выкл.)
 */
final class JevException extends RuntimeException
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
 * Разобранный ответ Jev с типизированными аксессорами по id вопроса.
 */
final class JevResult
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

    public function model(): ?string
    {
        return isset($this->data['model']) ? (string) $this->data['model'] : null;
    }

    /** @return array<string, mixed> */
    public function usage(): array
    {
        $usage = $this->data['usage'] ?? [];

        return is_array($usage) ? $usage : [];
    }

    public function totalTokens(): int
    {
        $usage = $this->usage();

        if (isset($usage['total_tokens'])) {
            return (int) $usage['total_tokens'];
        }

        return (int) ($usage['input_tokens'] ?? 0) + (int) ($usage['output_tokens'] ?? 0);
    }

    /** Стоимость в USD; null — провайдер не сообщил цену (так делает TypeSafe). */
    public function cost(): ?float
    {
        $cost = $this->usage()['cost'] ?? null;

        return is_numeric($cost) ? (float) $cost : null;
    }

    public function requestId(): ?string
    {
        foreach (['x-generation-id', 'x-typesafe-request-id', 'x-request-id'] as $header) {
            if (!empty($this->headers[$header])) {
                return $this->headers[$header];
            }
        }

        $id = $this->data['id'] ?? $this->data['request_id'] ?? null;

        return is_string($id) ? $id : null;
    }

    /** @return array<string, array<string, mixed>> */
    public function answers(): array
    {
        $answers = $this->data['answers'] ?? [];

        return is_array($answers) ? $answers : [];
    }

    /** @return array<string, mixed>|null */
    public function answer(string $id): ?array
    {
        $answer = $this->answers()[$id] ?? null;

        return is_array($answer) ? $answer : null;
    }

    public function type(string $id): ?string
    {
        $type = $this->answer($id)['type'] ?? null;

        return is_string($type) ? $type : null;
    }

    /** Вероятность «да» для noul-ответа (0.0–1.0). */
    public function noul(string $id): ?float
    {
        $value = $this->answer($id)['noul'] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    public function isYes(string $id, float $threshold = 0.5): bool
    {
        $value = $this->noul($id);

        return $value !== null && $value >= $threshold;
    }

    public function choice(string $id): ?string
    {
        $value = $this->answer($id)['choice'] ?? null;

        return is_string($value) ? $value : null;
    }

    public function is(string $id, string $option): bool
    {
        return $this->choice($id) === $option;
    }

    /** Вероятность выбранного для choice/score варианта. */
    public function confidence(string $id): ?float
    {
        $value = $this->answer($id)['confidence'] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /** @return array<string, float> */
    public function probabilities(string $id): array
    {
        $probabilities = $this->answer($id)['probabilities'] ?? [];
        if (!is_array($probabilities)) {
            return [];
        }

        return array_map(static fn ($value): float => (float) $value, $probabilities);
    }

    /** Непрерывная оценка score-ответа (может лежать между уровнями). */
    public function score(string $id): ?float
    {
        $value = $this->answer($id)['score'] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /** @return array<string, string> */
    public function legend(string $id): array
    {
        $legend = $this->answer($id)['legend'] ?? [];
        if (!is_array($legend)) {
            return [];
        }

        return array_map(static fn ($value): string => (string) $value, $legend);
    }

    /** Ближайший целочисленный уровень score-ответа. */
    public function nearestLevel(string $id): ?int
    {
        $score = $this->score($id);

        return $score === null ? null : (int) round($score);
    }

    /** Описание ближайшего уровня score-ответа. */
    public function describe(string $id): ?string
    {
        $level = $this->nearestLevel($id);

        return $level === null ? null : ($this->legend($id)[(string) $level] ?? null);
    }
}

/**
 * Клиент запросов к Jev (OpenRouter decisions API или TypeSafe System One).
 */
final class JevClient
{
    public const OPENROUTER = 'openrouter';
    public const TYPESAFE = 'typesafe';

    /**
     * @var array<string, array{endpoint: string, url_env: string, key_env: list<string>, default_model: string}>
     */
    private const PROVIDERS = [
        self::OPENROUTER => [
            'endpoint' => 'https://openrouter.ai/api/alpha/decisions',
            'url_env' => 'JEV_OPENROUTER_URL',
            'key_env' => ['OPENROUTER_JEV_KEY'],
            'default_model' => 'jev-latest',
        ],
        self::TYPESAFE => [
            'endpoint' => 'https://api.typesafe.ai/v1/systemone',
            'url_env' => 'JEV_TYPESAFE_URL',
            'key_env' => ['TYPESAFE_API_KEY'],
            'default_model' => 'jev-latest',
        ],
    ];

    private const RETRY_STATUSES = [408, 409, 429, 500, 502, 503, 504, 529];

    private string $provider;
    private ?string $apiKey;
    private ?string $model;
    private ?string $endpoint = null;

    private string|array|null $state = null;

    /** @var array<string, array<string, mixed>> */
    private array $questions = [];

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
        $this->provider = self::normalizeProvider(
            $provider ?? (string) (getenv('JEV_PROVIDER') ?: self::OPENROUTER)
        );
        $this->apiKey = $apiKey;
        $this->model = $model;

        $this->timeout = (float) (getenv('JEV_TIMEOUT') ?: 30);
        $this->connectTimeout = (int) (getenv('JEV_CONNECT_TIMEOUT') ?: 10);
        $this->maxRetries = (int) (getenv('JEV_MAX_RETRIES') ?: 2);

        $this->log = getenv('LOG_JEV') === 'true';
        $this->logFile = (string) (getenv('JEV_LOG_FILE') ?: (__DIR__ . '/../jev_debug.log'));
    }

    public static function openRouter(?string $apiKey = null, ?string $model = null): self
    {
        return new self($apiKey, self::OPENROUTER, $model);
    }

    public static function typeSafe(?string $apiKey = null, ?string $model = null): self
    {
        return new self($apiKey, self::TYPESAFE, $model);
    }

    /** Переключение провайдера: 'openrouter' | 'typesafe'. */
    public function useProvider(string $provider): self
    {
        $this->provider = self::normalizeProvider($provider);

        return $this;
    }

    public function useOpenRouter(): self
    {
        return $this->useProvider(self::OPENROUTER);
    }

    public function useTypeSafe(): self
    {
        return $this->useProvider(self::TYPESAFE);
    }

    public function provider(): string
    {
        return $this->provider;
    }

    // ----------------------------------------------------------------
    // Построение запроса
    // ----------------------------------------------------------------

    /** @param string|array<mixed> $state текст или структурированное состояние */
    public function state(string|array $state): self
    {
        $this->state = $state;

        return $this;
    }

    /** @param array<string, mixed> $question */
    public function ask(string $id, array $question): self
    {
        $this->questions[$id] = $question;

        return $this;
    }

    /**
     * Да/нет вопрос. Вернёт вероятность «да» в ответе: $res->noul($id).
     *
     * @param string|array<mixed> $instructions
     * @param array{true?: string, false?: string}|null $criteria
     */
    public function noul(string $id, string|array $instructions, ?array $criteria = null): self
    {
        $question = ['type' => 'noul', 'instructions' => $instructions];
        if ($criteria !== null) {
            $question['criteria'] = $criteria;
        }

        return $this->ask($id, $question);
    }

    /**
     * Выбор одного варианта. $criteria — карта «вариант => описание» либо
     * простой список вариантов.
     *
     * @param string|array<mixed> $instructions
     * @param array<string|int, string|array<mixed>|null>|list<string> $criteria
     */
    public function choice(string $id, string|array $instructions, array $criteria): self
    {
        if (array_is_list($criteria)) {
            $criteria = array_fill_keys(array_map('strval', $criteria), null);
        }

        return $this->ask($id, [
            'type' => 'choice',
            'instructions' => $instructions,
            'criteria' => $criteria,
        ]);
    }

    /**
     * Оценка по упорядоченной шкале. $criteria — список описаний уровней
     * (минимум 2), нумерация с нуля.
     *
     * @param string|array<mixed> $instructions
     * @param list<string|array<mixed>> $criteria
     */
    public function score(string $id, string|array $instructions, array $criteria): self
    {
        return $this->ask($id, [
            'type' => 'score',
            'instructions' => $instructions,
            'criteria' => array_values($criteria),
        ]);
    }

    public function model(string $model): self
    {
        $this->model = $model;

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

    /** Сбросить state и вопросы (клиент остаётся настроенным). */
    public function reset(): self
    {
        $this->state = null;
        $this->questions = [];

        return $this;
    }

    // ----------------------------------------------------------------
    // Отправка
    // ----------------------------------------------------------------

    public function send(): JevResult
    {
        if ($this->state === null) {
            throw new JevException('Не задан state: вызовите state() перед send().');
        }

        if ($this->questions === []) {
            throw new JevException('Не задано ни одного вопроса: noul()/choice()/score() перед send().');
        }

        $payload = [
            'state' => $this->state,
            'model' => $this->resolvedModel(),
            'questions' => $this->questions,
        ];

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $headers = array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->resolvedKey(),
        ], $this->extraHeaders);

        $url = $this->resolvedEndpoint();
        [$status, $body, $responseHeaders, $error] = $this->requestWithRetries($url, $json, $headers);

        $this->log(sprintf(
            ">>> TO JEV [%s / %s]\n%s\n<<< FROM JEV [HTTP %d]%s\n%s",
            $this->provider,
            $this->resolvedModel(),
            $json,
            $status,
            $error !== null ? ' cURL: ' . $error : '',
            $body
        ));

        if ($error !== null) {
            throw new JevException('Ошибка соединения с Jev: ' . $error, null, $body === '' ? null : $body);
        }

        if ($status < 200 || $status >= 300) {
            throw new JevException(
                sprintf('Jev вернул HTTP %d', $status),
                $status,
                $body,
                $this->headerId($responseHeaders)
            );
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new JevException('Некорректный JSON в ответе Jev: ' . $exception->getMessage(), $status, $body);
        }

        if (!is_array($data) || !isset($data['answers']) || !is_array($data['answers'])) {
            throw new JevException('В ответе Jev нет поля answers.', $status, $body);
        }

        return new JevResult($data, $this->provider, $responseHeaders);
    }

    // ----------------------------------------------------------------
    // Разрешение параметров из провайдера и окружения
    // ----------------------------------------------------------------

    private static function normalizeProvider(string $provider): string
    {
        $normalized = match (strtolower(trim($provider))) {
            'openrouter', 'open_router', 'or' => self::OPENROUTER,
            'typesafe', 'type_safe', 'ts' => self::TYPESAFE,
            default => '',
        };

        if (!isset(self::PROVIDERS[$normalized])) {
            throw new JevException(sprintf(
                'Неизвестный провайдер Jev: "%s" (доступны: %s).',
                $provider,
                implode(', ', array_keys(self::PROVIDERS))
            ));
        }

        return $normalized;
    }

    private function resolvedKey(): string
    {
        $key = $this->apiKey;

        if ($key === null || trim($key) === '') {
            foreach (self::PROVIDERS[$this->provider]['key_env'] as $envName) {
                $value = getenv($envName);
                if ($value !== false && trim($value) !== '') {
                    $key = $value;
                    break;
                }
            }
        }

        if ($key === null || trim($key) === '') {
            throw new JevException(sprintf(
                'Не задан API-ключ для провайдера "%s" (ожидается %s).',
                $this->provider,
                implode(' или ', self::PROVIDERS[$this->provider]['key_env'])
            ));
        }

        return trim($key);
    }

    private function resolvedModel(): string
    {
        if ($this->model !== null && trim($this->model) !== '') {
            return trim($this->model);
        }

        $fromEnv = getenv('JEV_MODEL');

        return $fromEnv !== false && trim($fromEnv) !== ''
            ? trim($fromEnv)
            : self::PROVIDERS[$this->provider]['default_model'];
    }

    private function resolvedEndpoint(): string
    {
        if ($this->endpoint !== null && trim($this->endpoint) !== '') {
            return trim($this->endpoint);
        }

        $fromEnv = getenv(self::PROVIDERS[$this->provider]['url_env']);

        return $fromEnv !== false && trim($fromEnv) !== ''
            ? trim($fromEnv)
            : self::PROVIDERS[$this->provider]['endpoint'];
    }

    private function headerId(array $responseHeaders): ?string
    {
        foreach (['x-generation-id', 'x-typesafe-request-id', 'x-request-id'] as $header) {
            if (!empty($responseHeaders[$header])) {
                return $responseHeaders[$header];
            }
        }

        return null;
    }

    // ----------------------------------------------------------------
    // Транспорт
    // ----------------------------------------------------------------

    /**
     * @param list<string> $headers
     * @return array{0: int, 1: string, 2: array<string, string>, 3: ?string}
     */
    private function requestWithRetries(string $url, string $json, array $headers): array
    {
        $attempt = 0;
        $backoff = 0.5;

        while (true) {
            $result = $this->post($url, $json, $headers);
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
    private function post(string $url, string $json, array $headers): array
    {
        $responseHeaders = [];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
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

        if ($this->provider === self::OPENROUTER) {
            curl_setopt_array($ch, openrouterProxyCurlOptions());
        }

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
