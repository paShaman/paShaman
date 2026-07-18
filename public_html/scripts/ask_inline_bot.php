<?php

require_once __DIR__ . '/../../vendor/autoload.php';
include __DIR__ . '/_env.php';

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

        if (mb_strlen($answer) > 4000) {
            $answer = mb_substr($answer, 0, 4000) . '…';
        }

        // Безопасно экранируем текст для HTML
        $safeQuery = htmlspecialchars($this->queryText, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Переводим маркдаун жирный от DeepSeek (если есть) в HTML, а остальное экранируем.
        // Если DeepSeek возвращает сложную разметку, лучше просто сделать htmlspecialchars на весь ответ,
        // либо сделать базовую замену **текст** на <b>текст</b>.
        $safeAnswer = htmlspecialchars($answer, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safeAnswer = preg_replace('/(\*\*|__)(.*?)\1/', '<b>$2</b>', $safeAnswer);

        // Убираем из ответа звёздочки, подчёркивания, бэктики и решётки заголовков
        $cleanDescription = preg_replace('/[\*_`#]/', '', $answer);

        // На всякий случай схлопнем лишние пробелы, если они образовались
        $cleanDescription = trim(preg_replace('/\s+/', ' ', $cleanDescription));

        $results = [
            [
                'type' => 'article',
                'id' => uniqid(more_entropy: true),
                'title' => 'Ответ от DeepSeek',
                'description' => mb_substr($cleanDescription, 0, 100) . '...',
                'input_message_content' => [
                    'message_text' => "<b>Вопрос:</b> {$safeQuery}\n\n<b>Ответ:</b>\n{$safeAnswer}",
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
        // Безопасно экранируем текст для HTML
        $safeQuery = htmlspecialchars($this->queryText, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $results = [
            [
                'type' => 'article',
                'id' => uniqid(more_entropy: true),
                'title' => $title,
                'description' => $text,
                'input_message_content' => [
                    'message_text' => "<b>Вопрос:</b> {$safeQuery}\n\n<b>Ошибка:</b>\n{$text}",
                    'parse_mode' => 'HTML',
                ]
            ]
        ];
        $this->sendInlineAnswer($results);
    }

    private function askDeepSeekDirect(string $text): string
    {
        $url = 'https://api.deepseek.com/chat/completions';
        $payload = [
            'model' => $this->deepseekModel,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Ты — утилита для быстрых ответов задач. Твоя цель — дать краткий и быстрый ответ без лишнего форматирования.",
                ],
                ['role' => 'user', 'content' => $text]
            ],
            'temperature' => 0.6,
            'stream' => false,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->deepseekKey,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (!$response || $httpCode !== 200) {
            return "⚠️ Ошибка генерации или превышен тайм-аут ожидания ответа ИИ.";
        }

        $res = json_decode($response, true);
        return $res['choices'][0]['message']['content'] ?? "Ошибка: пустой ответ API.";
    }

    private function sendInlineAnswer(array $results): void
    {
        $url = 'https://api.telegram.org/bot' . $this->tgToken . '/answerInlineQuery';
        $payload = [
            'inline_query_id' => $this->inlineQueryId,
            'results' => $results,
            'is_personal' => true
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);

        $hasError = false;

        if ($curlError) {
            $hasError = true;
            file_put_contents(
                'ask_errors.log',
                sprintf("%s | URL: %s | cURL Error: %s\n%s\n", date('Y-m-d H:i:s'), $url, $curlError, print_r($payload, true)),
                FILE_APPEND
            );
        }

        if ($response) {
            $resArr = json_decode($response, true);
            if (isset($resArr['ok']) && $resArr['ok'] === false) {
                $hasError = true;
                file_put_contents(
                    'tg_api_errors.log',
                    sprintf("%s | URL: %s | Response: %s\n%s\n", date('Y-m-d H:i:s'), $url, $response, print_r($payload, true)),
                    FILE_APPEND
                );
            }
        }

        if ($hasError) {
            $this->answerSimple('Ошибка', 'Сократите вопрос или попробуйте позже');
        }
    }
}

$bot = new DeepSeekInlineBot();
echo json_encode(['status' => $bot->run()]);