<?php

declare(strict_types=1);

require_once __DIR__ . '/../_env.php';

/**
 * OpenRouterClient — клиент аккаунтных запросов к OpenRouter.
 *
 * Это баланс именно аккаунта OpenRouter (его кредиты).
 *
 * Баланс берётся из credits-эндпоинта:
 *   GET https://openrouter.ai/api/v1/credits
 *   { "data": { "total_credits": 20.0, "total_usage": 5.5 } }
 *
 * Остаток (remaining) = total_credits - total_usage, валюта — USD.
 *
 * Пример:
 *   require_once __DIR__ . '/clients/openrouter_client.php';
 *
 *   $balance = (new OpenRouterClient())->balance();
 *   echo $balance['remaining'];   // остаток в USD
 *
 * Переменные окружения (читаются через _env.php, файл .env в корне проекта):
 *   OPENROUTER_KEY — API-ключ OpenRouter
 *   OPENROUTER_BALANCE_URL             — переопределение эндпоинта баланса
 *   OPENROUTER_TIMEOUT                 — таймаут запроса, сек (по умолчанию 30)
 *   OPENROUTER_CONNECT_TIMEOUT         — таймаут соединения, сек (по умолчанию 10)
 *   OPENROUTER_PROXY — прокси для запросов (по умолчанию берётся TG_PROXY)
 */
final class OpenRouterException extends RuntimeException
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

final class OpenRouterClient
{
    private const BALANCE_URL = 'https://openrouter.ai/api/v1/credits';

    /** @var list<string> */
    private const KEY_ENV = ['OPENROUTER_KEY'];

    private ?string $apiKey;
    private float $timeout;
    private int $connectTimeout;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey;
        $this->timeout = (float) (getenv('OPENROUTER_TIMEOUT') ?: 30);
        $this->connectTimeout = (int) (getenv('OPENROUTER_CONNECT_TIMEOUT') ?: 10);
    }

    /**
     * Баланс аккаунта OpenRouter в USD.
     *
     * @return array{total_credits: float, total_usage: float, remaining: float, currency: string}
     */
    public function balance(): array
    {
        $url = trim((string) (getenv('OPENROUTER_BALANCE_URL') ?: self::BALANCE_URL));

        [$status, $body, $error] = $this->request($url);

        if ($error !== null) {
            throw new OpenRouterException('Ошибка соединения с OpenRouter: ' . $error, null, $body === '' ? null : $body);
        }

        if ($status < 200 || $status >= 300) {
            throw new OpenRouterException(sprintf('OpenRouter вернул HTTP %d', $status), $status, $body);
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new OpenRouterException('Некорректный JSON в ответе OpenRouter: ' . $exception->getMessage(), $status, $body);
        }

        if (!is_array($data)) {
            throw new OpenRouterException('Некорректный ответ OpenRouter.', $status, $body);
        }

        $credits = $data['data']['total_credits'] ?? null;
        $usage = $data['data']['total_usage'] ?? null;

        if (!is_numeric($credits) && !is_numeric($usage)) {
            throw new OpenRouterException('В ответе OpenRouter нет данных о кредитах.', $status, $body);
        }

        $totalCredits = (float) $credits;
        $totalUsage = (float) $usage;

        return [
            'total_credits' => $totalCredits,
            'total_usage' => $totalUsage,
            'remaining' => $totalCredits - $totalUsage,
            'currency' => 'USD',
        ];
    }

    private function resolvedKey(): string
    {
        $key = $this->apiKey;

        if ($key === null || trim($key) === '') {
            foreach (self::KEY_ENV as $envName) {
                $value = getenv($envName);
                if ($value !== false && trim($value) !== '') {
                    $key = $value;
                    break;
                }
            }
        }

        if ($key === null || trim($key) === '') {
            throw new OpenRouterException('Не задан API-ключ OpenRouter (ожидается ' . implode(' или ', self::KEY_ENV) . ').');
        }

        return trim($key);
    }

    /**
     * @return array{0: int, 1: string, 2: ?string}
     */
    private function request(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->resolvedKey(),
            ],
            CURLOPT_TIMEOUT => max(1, (int) ceil($this->timeout)),
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
        ]);

        curl_setopt_array($ch, openrouterProxyCurlOptions());

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            $status,
            is_string($body) ? $body : '',
            $error !== '' ? $error : null,
        ];
    }
}
