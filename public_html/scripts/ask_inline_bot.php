<?php

require_once __DIR__ . '/../../vendor/autoload.php';
include __DIR__ . '/_env.php';
require_once __DIR__ . '/clients/deepseek_client.php';
require_once __DIR__ . '/clients/telegram_client.php';

// Устанавливаем Content-Type для ответа Telegram
header('Content-Type: application/json');

class DeepSeekInlineBot
{
    private string $tgToken;
    private int $tgChatId;
    private string $deepseekKey;
    private string $deepseekModel;

    // Список разрешенных Telegram ID (белый список)
    private const array ALLOWED_TELEGRAM_IDS = [
        224028930,  // Алёнка
        1780404823, // alpus
    ];

    // --- ВХОДЯЩИЕ ДАННЫЕ ---
    private ?string $inlineQueryId = null;
    private ?string $queryText = null;
    private ?int $userId = null;

    public function __construct()
    {
        $this->tgToken = (string)getenv('TG_TOKEN_ASK');
        $this->tgChatId = (int)getenv('TG_CHAT_ID');
        $this->deepseekKey = (string)getenv('DEEPSEEK_KEY');
        $this->deepseekModel = (string)getenv('DEEPSEEK_MODEL');
    }

    public function run(): string
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            return 'no_data';
        }

        // Проверка Webhook Secret Token
        $secret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
        if (!hash_equals($secret, getenv('TG_WEBHOOK_SECRET_ASK'))) {
            return 'unauthorized_webhook';
        }

        // Ловим именно inline_query
        if (!isset($data['inline_query'])) {
            return 'not_inline_query';
        }

        $inlineData = $data['inline_query'];
        $this->inlineQueryId = $inlineData['id'];
        $this->queryText = trim($inlineData['query'] ?? '');
        $this->userId = $inlineData['from']['id'] ?? null;

        // Проверка доступа
        if (!$this->isAccessAllowed()) {
            return 'forbidden';
        }

        // Если пользователь ещё ничего не ввёл
        if ($this->queryText === '') {
            return 'empty_query';
        }

        $lastChar = mb_substr($this->queryText, -1);
        if (!in_array($lastChar, ['.', '?'])) {
            return 'waiting_for_end_of_input';
        }

        // Обрабатываем запрос в DeepSeek
        $this->handleInlineAsk();

        return 'ok';
    }

    private function isAccessAllowed(): bool
    {
        return ($this->userId === $this->tgChatId)
            || in_array($this->userId, self::ALLOWED_TELEGRAM_IDS);
    }

    /**
     * Основная логика: опрашивает ИИ и сразу возвращает результат в Telegram
     */
    private function handleInlineAsk(): void
    {
        $answer = $this->askDeepSeekDirect($this->queryText);

        // Убираем из ответа звёздочки, подчёркивания, бэктики и решётки заголовков
        $cleanDescription = preg_replace('/[\*_`#]/', '', $answer);
        $cleanDescription = trim(preg_replace('/\s+/', ' ', $cleanDescription));

        $safeQuery = htmlspecialchars($this->queryText, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $safeAnswer = htmlspecialchars($answer, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safeAnswer = preg_replace('/(\*\*|__)(.*?)\1/', '<b>$2</b>', $safeAnswer);

        $messagePrefix = "<b>Вопрос:</b> {$safeQuery}\n\n<b>Ответ:</b>\n";
        $maxAnswerLen = 4096 - mb_strlen($messagePrefix, 'UTF-8') - 3;
        if (mb_strlen($safeAnswer, 'UTF-8') > $maxAnswerLen) {
            $safeAnswer = mb_substr($safeAnswer, 0, $maxAnswerLen, 'UTF-8') . '…';
        }

        $results = [
            [
                'type' => 'article',
                'id' => uniqid(more_entropy: true),
                'title' => 'Ответ от DeepSeek',
                'description' => mb_substr($cleanDescription, 0, 100, 'UTF-8') . '...',
                'input_message_content' => [
                    'message_text' => $messagePrefix . $safeAnswer,
                    'parse_mode' => 'HTML',
                ]
            ]
        ];

        $this->sendInlineAnswer($results);
    }

    /**
     * Отправка заглушки (когда текста нет или нет доступа)
     */
    private function answerSimple(string $title, string $text): void
    {
        $safeQuery = htmlspecialchars($this->queryText ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safeText = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $results = [
            [
                'type' => 'article',
                'id' => uniqid(more_entropy: true),
                'title' => $title,
                'description' => $text,
                'input_message_content' => [
                    'message_text' => "<b>Вопрос:</b> {$safeQuery}\n\n<b>Ошибка:</b>\n{$safeText}",
                    'parse_mode' => 'HTML',
                ]
            ]
        ];
        $this->sendInlineAnswer($results, isFallback: true);
    }

    private function askDeepSeekDirect(string $text): string
    {
        try {
            $content = (new DeepSeekClient($this->deepseekKey, DeepSeekClient::DEEPSEEK, $this->deepseekModel))
                ->system('Ты — утилита для быстрых ответов задач. Твоя цель — дать краткий и быстрый ответ без лишнего форматирования.')
                ->user($text)
                ->temperature(0.6)
                ->timeout(60)
                ->send()
                ->content();
        } catch (DeepSeekException $e) {
            return "⚠️ Ошибка генерации или превышен тайм-аут ожидания ответа ИИ.";
        }

        return $content ?? "Ошибка: пустой ответ API.";
    }

    private function sendInlineAnswer(array $results, bool $isFallback = false): void
    {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $hasError = false;
        $proxyLabel = tgProxyCurlOptions() ? 'TG_PROXY' : 'direct';

        try {
            $result = (new TelegramClient($this->tgToken))
                ->answerInlineQuery($this->inlineQueryId, $results, ['is_personal' => true]);

            if (!$result->ok()) {
                $hasError = true;
                file_put_contents(
                    $logDir . '/tg_api_errors.log',
                    sprintf("%s | proxy: %s | Response: %s\n", date('Y-m-d H:i:s'), $proxyLabel, json_encode($result->raw(), JSON_UNESCAPED_UNICODE)),
                    FILE_APPEND
                );
            }
        } catch (TelegramException $e) {
            $hasError = true;
            file_put_contents(
                $logDir . '/ask_errors.log',
                sprintf("%s | proxy: %s | Error: %s\n", date('Y-m-d H:i:s'), $proxyLabel, $e->getMessage()),
                FILE_APPEND
            );
        }

        if ($hasError && !$isFallback) {
            $this->answerSimple('Ошибка', 'Сократите вопрос или попробуйте позже');
        }
    }
}

$bot = new DeepSeekInlineBot();
echo json_encode(['status' => $bot->run()]);