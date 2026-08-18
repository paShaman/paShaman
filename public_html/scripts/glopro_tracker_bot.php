<?php

include __DIR__ . '/_env.php';

/**
 * GloPro Redmine трекер-бот (мультипользовательский).
 *
 * Каждый сотрудник регистрирует свою ссылку issues.atom (со своим ключом) через
 * Telegram-бота, после чего бот по cron проверяет задачи и присылает изменения
 * каждому в его чат.
 *
 * Режимы:
 *   php public_html/scripts/glopro_tracker_bot.php          # cron: проверка всех пользователей, изменения — в Telegram
 *   php public_html/scripts/glopro_tracker_bot.php --listen # long-polling: регистрация/управление командами
 *   php public_html/scripts/glopro_tracker_bot.php --users  # список зарегистрированных пользователей
 *
 * Команды (в Telegram, в режиме --listen):
 *   /start, /help            — подсказка
 *   /setkey <ссылка>         — зарегистрировать свой issues.atom (с key=...), alias /key
 *   /status                  — статус подписки
 *   /test                    — текущие задачи
 *   /stop, /unsub            — отключить уведомления
 *   /chatid, /id             — показать ID чата
 *
 * Хранение (в public_html/scripts/logs/):
 *   glopro_users.json            — реестр пользователей (chat_id -> atom_url)
 *   glopro_state_<chat_id>.json  — состояние задач каждого пользователя
 *   glopro.offset                — offset long-polling
 *
 * Конфигурация (getenv):
 *   GLOPRO_TG_TOKEN — токен Telegram-бота
 *   GLOPRO_TZ       — часовой пояс (по умолчанию Europe/Moscow)
 */
final class GloProTrackerBot
{
    private const ATOM_NS = 'http://www.w3.org/2005/Atom';

    private ?string $tgToken = null;
    private ?string $logPrefix = null;

    public function __construct()
    {
        $this->tgToken = trim((string)getenv('GLOPRO_TG_TOKEN')) ?: null;

        date_default_timezone_set(trim((string)getenv('GLOPRO_TZ')) ?: 'Europe/Moscow');

        $this->ensureLogsDir();
    }

    public function run(array $argv): int
    {
        if (in_array('--listen', $argv, true)) {
            return $this->listen();
        }

        if (in_array('--users', $argv, true)) {
            return $this->listUsers();
        }

        return $this->runCron();
    }

    // ---------------------------------------------------------------------
    // Cron
    // ---------------------------------------------------------------------

    private function runCron(): int
    {
        $this->logPrefix = 'glopro_cron';

        $users = $this->loadUsers();
        if ($users === []) {
            $this->log('Нет зарегистрированных пользователей — нечего проверять.');
            return 0;
        }

        $this->log('Запуск cron: пользователей ' . count($users));

        foreach ($users as $chatId => $user) {
            $this->processUser((string)$chatId, $user);
        }

        $this->log('Cron завершён.');
        return 0;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function processUser(string $chatId, array $user): void
    {
        $url = trim((string)($user['atom_url'] ?? ''));
        if ($url === '') {
            $this->log("⚠️ Пользователь {$chatId}: нет atom_url, пропуск.");
            return;
        }

        try {
            $issues = $this->fetchIssues($url);
        } catch (RuntimeException $e) {
            $this->log("❌ Пользователь {$chatId}: " . $e->getMessage());
            return;
        }

        $stateFile = $this->stateFile($chatId);
        $changes = $this->diffIssues($this->loadState($stateFile), $issues);
        $this->saveState($stateFile, $issues);

        if ($changes === []) {
            $this->log("Пользователь {$chatId}: изменений нет (задач " . count($issues) . ').');
            return;
        }

        $message = $this->buildChangesMessage($changes, count($issues));
        $ok = $this->sendTelegram($chatId, $message);
        $this->log(($ok ? '✅' : '❌') . " Пользователь {$chatId}: изменений " . count($changes) . ($ok ? '' : ' (ошибка отправки)') . '.');
    }

    // ---------------------------------------------------------------------
    // Long-polling
    // ---------------------------------------------------------------------

    private function listen(): int
    {
        if ($this->tgToken === null || $this->tgToken === '') {
            $this->out("Ошибка: не задан GLOPRO_TG_TOKEN.\n");
            return 2;
        }

        $this->logPrefix = 'glopro_listen';
        $offsetFile = __DIR__ . '/logs/glopro.offset';

        $lastUpdateId = $this->readOffset($offsetFile);
        $this->log("🚀 Long-polling запущен, offset={$lastUpdateId}");

        while (true) {
            try {
                $updates = $this->getUpdates($lastUpdateId + 1);
                if ($updates === null) {
                    sleep(5);
                    continue;
                }

                foreach ($updates as $update) {
                    $updateId = $update['update_id'] ?? null;
                    if ($updateId !== null) {
                        $lastUpdateId = (int)$updateId;
                        $this->saveOffset($offsetFile, $lastUpdateId);
                    }

                    if (isset($update['message'])) {
                        $this->handleMessage($update['message']);
                    }
                }
            } catch (\Throwable $e) {
                $this->log('Исключение: ' . $e->getMessage());
                sleep(5);
            }
        }
    }

    /**
     * Забирает апдейты из Telegram (long polling).
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function getUpdates(int $offset): ?array
    {
        $params = [
            'offset' => $offset,
            'timeout' => 50,
            'allowed_updates' => json_encode(['message']),
        ];
        $url = 'https://api.telegram.org/bot' . $this->tgToken . '/getUpdates?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($error) {
            $this->log('⚠️ cURL ошибка: ' . $error);
            return null;
        }
        if ($response === false || $httpCode !== 200) {
            $this->log('⚠️ Telegram API HTTP ' . $httpCode . ': ' . substr((string)$response, 0, 300));
            return null;
        }

        $data = json_decode((string)$response, true);
        if (!$data || !($data['ok'] ?? false)) {
            $code = $data['error_code'] ?? 0;
            if ($code === 401) {
                $this->log('❌ Неверный GLOPRO_TG_TOKEN');
                exit(1);
            }
            if ($code === 409) {
                $this->log('⚠️ 409 Conflict — уже работает другой экземпляр бота');
                exit(1);
            }
            $this->log('⚠️ Некорректный ответ Telegram API: ' . substr((string)$response, 0, 300));
            return null;
        }

        return $data['result'] ?? [];
    }

    /**
     * @param array<string, mixed> $message
     */
    private function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $from = $message['from'] ?? [];
        $fromId = $from['id'] ?? null;
        $text = trim((string)($message['text'] ?? ''));

        if ($text === '' || $chatId === null) {
            return;
        }

        $parts = preg_split('/\s+/', $text, 2);
        $rawCommand = $parts[0] ?? '';
        $arg = isset($parts[1]) ? trim($parts[1]) : '';

        // Отрезаем @botname, если команда пришла в группе.
        $command = explode('@', $rawCommand, 2)[0];

        $name = trim((string)($from['first_name'] ?? ''));
        if (isset($from['last_name'])) {
            $name = trim($name . ' ' . (string)$from['last_name']);
        }
        $username = (string)($from['username'] ?? '');

        $reply = match ($command) {
            '/start', '/help' => $this->cmdHelp(),
            '/chatid', '/id' => "Chat ID: {$chatId}",
            '/setkey', '/key' => $this->cmdSetKey((string)$chatId, $name, $username, $arg),
            '/status' => $this->cmdStatus((string)$chatId),
            '/stop', '/unsub' => $this->cmdStop((string)$chatId),
            '/test' => $this->cmdTest((string)$chatId),
            default => null,
        };

        if ($reply === null) {
            return;
        }

        $ok = $this->sendTelegram($chatId, $reply);

        $extra = '';
        if (in_array($command, ['/setkey', '/key'], true) && $arg !== '') {
            $extra = ', host=' . (parse_url($this->normalizeUrl($arg), PHP_URL_HOST) ?: '?');
        }
        $this->log(($ok ? '✅' : '❌') . " {$command} от from_id=" . var_export($fromId, true) . ", chat={$chatId}{$extra}" . ($ok ? '' : ' (ошибка отправки)'));
    }

    private function cmdHelp(): string
    {
        return "Привет! Я слежу за задачами в Redmine и присылаю изменения.\n\n"
            . "1. Открой нужный фильтр задач в Redmine и скопируй ссылку «Atom» (issues.atom) целиком, с key=...\n"
            . "2. Пришли её командой:\n/setkey <ссылка>\n\n"
            . "Дальше я сам буду проверять задачи и сообщать о новых, смене статуса и обновлениях.\n\n"
            . "Команды: /status — статус, /test — текущие задачи, /stop — отключить, /chatid — ID чата.";
    }

    private function cmdSetKey(string $chatId, string $name, string $username, string $arg): string
    {
        $url = $this->normalizeUrl($arg);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return "Пришли ссылку целиком:\n/setkey https://cp.glopro.ru/issues.atom?...&key=...\n\n"
                . "Скопируй её из Redmine (кнопка «Atom» внизу списка задач).";
        }

        try {
            $issues = $this->fetchIssues($url);
        } catch (RuntimeException $e) {
            return 'Не удалось получить задачи по этой ссылке: ' . $e->getMessage() . "\n\n"
                . 'Проверь, что ссылка скопирована целиком (с key=...). '
                . 'Если Redmine временно недоступен — попробуй ещё раз через пару минут.';
        }

        $this->withUserLock(function () use ($chatId, $name, $username, $url) {
            $users = $this->loadUsers();
            $users[$chatId] = [
                'chat_id' => $chatId,
                'name' => $name,
                'username' => $username,
                'atom_url' => $url,
                'created_at' => date('c'),
            ];
            $this->saveUsers($users);
        });

        // Сохраняем текущее состояние, чтобы первый cron не прислал все задачи как новые.
        $this->saveState($this->stateFile($chatId), $issues);

        return 'Готово! Ключ принят, задач в выдаче: ' . count($issues) . ".\n\n"
            . 'Теперь я буду сообщать об изменениях. Проверить подписку: /status';
    }

    private function cmdStatus(string $chatId): string
    {
        $users = $this->loadUsers();
        if (!isset($users[$chatId])) {
            return "Ты ещё не зарегистрирован.\n\nОтправь /setkey <ссылка issues.atom>, чтобы начать получать уведомления.";
        }

        $url = (string)($users[$chatId]['atom_url'] ?? '');
        $host = parse_url($url, PHP_URL_HOST) ?: '—';

        $savedAt = $this->loadStateSavedAt($this->stateFile($chatId));
        $lastCheck = '—';
        if ($savedAt !== null) {
            $lastCheck = (new DateTimeImmutable($savedAt))->format('d.m.Y H:i');
        }

        return "Статус:\n• Redmine: {$host}\n• Подписка: активна\n• Последняя проверка: {$lastCheck}\n\n"
            . "Команды: /test — текущие задачи, /stop — отключить.";
    }

    private function cmdStop(string $chatId): string
    {
        $removed = false;
        $this->withUserLock(function () use ($chatId, &$removed) {
            $users = $this->loadUsers();
            if (isset($users[$chatId])) {
                unset($users[$chatId]);
                $this->saveUsers($users);
                $removed = true;
            }
        });

        $stateFile = $this->stateFile($chatId);
        if (is_file($stateFile)) {
            @unlink($stateFile);
        }

        return $removed ? 'Подписка отключена. Чтобы вернуться — /setkey <ссылка>.' : 'Ты и так не был подписан.';
    }

    private function cmdTest(string $chatId): string
    {
        $users = $this->loadUsers();
        $url = trim((string)($users[$chatId]['atom_url'] ?? ''));
        if ($url === '') {
            return 'Сначала зарегистрируйся: /setkey <ссылка issues.atom>.';
        }

        try {
            $issues = $this->fetchIssues($url);
        } catch (RuntimeException $e) {
            return 'Ошибка: ' . $e->getMessage();
        }

        return $this->buildSummary($issues, parse_url($url, PHP_URL_HOST) ?: 'Redmine');
    }

    private function listUsers(): int
    {
        $users = $this->loadUsers();
        if ($users === []) {
            $this->out("Пользователи не зарегистрированы.\n");
            return 0;
        }

        $this->out('Зарегистрировано пользователей: ' . count($users) . "\n");
        foreach ($users as $chatId => $user) {
            $host = parse_url((string)($user['atom_url'] ?? ''), PHP_URL_HOST) ?: '—';
            $name = (string)($user['name'] ?? '');
            $username = (string)($user['username'] ?? '');
            $label = $name !== '' ? $name : ($username !== '' ? '@' . $username : '—');
            $this->out(sprintf("%s | %s | %s\n", $chatId, $label, $host));
        }
        return 0;
    }

    // ---------------------------------------------------------------------
    // Redmine
    // ---------------------------------------------------------------------

    /**
     * Скачивает и парсит Atom-фид, возвращает список задач.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchIssues(string $url): array
    {
        $xml = $this->fetchUrl($url);

        $dom = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$loaded) {
            throw new RuntimeException('Redmine вернул некорректный XML');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('atom', self::ATOM_NS);

        $issues = [];
        foreach ($xpath->query('//atom:entry') as $entry) {
            if ($entry instanceof DOMElement) {
                $issues[] = $this->parseEntry($entry, $xpath);
            }
        }

        return $issues;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseEntry(DOMElement $entry, DOMXPath $xpath): array
    {
        $title = trim($xpath->evaluate('string(atom:title)', $entry));
        $link = trim($xpath->evaluate('string(atom:link[@rel="alternate"]/@href)', $entry));
        if ($link === '') {
            $link = trim($xpath->evaluate('string(atom:link/@href)', $entry));
        }
        $updated = trim($xpath->evaluate('string(atom:updated)', $entry));
        $author = trim($xpath->evaluate('string(atom:author/atom:name)', $entry));
        $description = trim($xpath->evaluate('string(atom:content)', $entry));

        $parsed = $this->parseTitle($title);

        return [
            'id' => $parsed['id'],
            'project' => $parsed['project'],
            'tracker' => $parsed['tracker'],
            'status' => $parsed['status'],
            'subject' => $parsed['subject'],
            'url' => $link,
            'updated' => $this->parseDate($updated),
            'author' => $author,
            'description' => $description,
        ];
    }

    /**
     * Разбирает заголовок задачи вида "Project - Tracker #123 (Status): Subject".
     *
     * @return array{id: int, project: string, tracker: string, status: string, subject: string}
     */
    private function parseTitle(string $title): array
    {
        if (preg_match('/^(?P<project>.+?)\s+-\s+(?P<tracker>.+?)\s+#(?P<id>\d+)\s+\((?P<status>[^)]+)\):\s*(?P<subject>.*)$/su', $title, $m)) {
            return [
                'id' => (int)$m['id'],
                'project' => trim($m['project']),
                'tracker' => trim($m['tracker']),
                'status' => trim($m['status']),
                'subject' => trim($m['subject']),
            ];
        }

        // Запасной вариант: вытащим хотя бы id и статус.
        $id = 0;
        if (preg_match('/#(\d+)/', $title, $m)) {
            $id = (int)$m[1];
        }
        $status = '';
        if (preg_match('/\(([^)]+)\)/', $title, $m)) {
            $status = trim($m[1]);
        }

        return [
            'id' => $id,
            'project' => '',
            'tracker' => '',
            'status' => $status,
            'subject' => $title,
        ];
    }

    /**
     * @return array{unix: int, text: string}
     */
    private function parseDate(string $iso): array
    {
        if ($iso === '') {
            return ['unix' => 0, 'text' => ''];
        }

        $dt = new DateTimeImmutable($iso);
        return [
            'unix' => $dt->getTimestamp(),
            'text' => $dt->format('d.m.Y H:i'),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $issues
     */
    private function buildSummary(array $issues, string $host): string
    {
        $count = count($issues);
        $lines = ["Redmine: {$host}", "Задач в выдаче: {$count}"];

        if ($count === 0) {
            return implode("\n", $lines) . "\n";
        }

        $lines[] = '';
        foreach ($issues as $i => $issue) {
            $num = $i + 1;
            $id = $issue['id'] ? '#' . $issue['id'] : '—';
            $tracker = $issue['tracker'] !== '' ? ' · ' . $issue['tracker'] : '';
            $status = $issue['status'] !== '' ? ' · ' . $issue['status'] : '';
            $project = $issue['project'] !== '' ? ' · ' . $issue['project'] : '';

            $lines[] = "{$num}. {$id}{$tracker}{$status}{$project}";
            $lines[] = '   ' . $issue['subject'];
            $lines[] = '   Обновлена: ' . $issue['updated']['text'] . ($issue['url'] !== '' ? ' · ' . $issue['url'] : '');
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    // ---------------------------------------------------------------------
    // Состояние и дифф
    // ---------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadState(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            return [];
        }

        $data = json_decode($raw, true);
        return is_array($data['issues'] ?? null) ? $data['issues'] : [];
    }

    private function loadStateSavedAt(string $file): ?string
    {
        if (!is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        return isset($data['saved_at']) && is_string($data['saved_at']) ? $data['saved_at'] : null;
    }

    /**
     * @param array<int, array<string, mixed>> $issues
     */
    private function saveState(string $file, array $issues): void
    {
        $payload = json_encode(
            ['saved_at' => date('c'), 'issues' => $issues],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        file_put_contents($file, $payload, LOCK_EX);
    }

    /**
     * Сравнивает прошлое и текущее состояние, возвращает список изменений.
     *
     * @param array<int, array<string, mixed>> $prev
     * @param array<int, array<string, mixed>> $cur
     * @return array<int, array{type: string, issue: array<string, mixed>, old_status?: string}>
     */
    private function diffIssues(array $prev, array $cur): array
    {
        $prevById = [];
        foreach ($prev as $issue) {
            $prevById[(int)$issue['id']] = $issue;
        }
        $curById = [];
        foreach ($cur as $issue) {
            $curById[(int)$issue['id']] = $issue;
        }

        $changes = [];

        foreach ($cur as $issue) {
            $id = (int)$issue['id'];

            if (!isset($prevById[$id])) {
                $changes[] = ['type' => 'new', 'issue' => $issue];
                continue;
            }

            $old = $prevById[$id];
            if ($old['status'] !== $issue['status']) {
                $changes[] = ['type' => 'status', 'issue' => $issue, 'old_status' => (string)$old['status']];
            } elseif (($issue['updated']['unix'] ?? 0) > ($old['updated']['unix'] ?? 0)) {
                $changes[] = ['type' => 'updated', 'issue' => $issue, 'old_status' => (string)$old['status']];
            }
        }

        foreach ($prevById as $id => $old) {
            if (!isset($curById[$id])) {
                $changes[] = ['type' => 'removed', 'issue' => $old];
            }
        }

        return $changes;
    }

    /**
     * @param array<int, array{type: string, issue: array<string, mixed>, old_status?: string}> $changes
     */
    private function buildChangesMessage(array $changes, int $total): string
    {
        $count = count($changes);
        $lines = ["🔄 Redmine: изменений {$count}, всего задач {$total}"];

        $groups = [];
        foreach ($changes as $change) {
            $groups[$change['type']][] = $change;
        }

        $labels = [
            'new' => '🆕 Новые задачи:',
            'status' => '🔁 Сменили статус:',
            'updated' => '📝 Обновлены:',
            'removed' => '🚫 Вышли из фильтра:',
        ];

        foreach ($labels as $type => $label) {
            if (empty($groups[$type])) {
                continue;
            }
            $lines[] = '';
            $lines[] = $label;
            foreach ($groups[$type] as $change) {
                $lines[] = '• ' . $this->formatChange($change);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param array{type: string, issue: array<string, mixed>, old_status?: string} $change
     */
    private function formatChange(array $change): string
    {
        $issue = $change['issue'];
        $id = $issue['id'] ? '#' . $issue['id'] : '—';
        $tracker = $issue['tracker'] !== '' ? ' · ' . $issue['tracker'] : '';
        $status = $issue['status'] !== '' ? ' · ' . $issue['status'] : '';

        $line = "{$id}{$tracker}{$status} — {$issue['subject']}";

        if ($change['type'] === 'status') {
            $line .= " (было: {$change['old_status']})";
        } elseif ($change['type'] === 'updated' && $issue['updated']['text'] !== '') {
            $line .= ' (обновлена ' . $issue['updated']['text'] . ')';
        }

        if ($issue['url'] !== '') {
            $line .= "\n  " . $issue['url'];
        }

        return $line;
    }

    // ---------------------------------------------------------------------
    // Пользователи (реестр)
    // ---------------------------------------------------------------------

    private function usersFile(): string
    {
        return __DIR__ . '/logs/glopro_users.json';
    }

    private function stateFile(string $chatId): string
    {
        return __DIR__ . '/logs/glopro_state_' . $chatId . '.json';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadUsers(): array
    {
        $file = $this->usersFile();
        if (!is_file($file)) {
            return [];
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            return [];
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, array<string, mixed>> $users
     */
    private function saveUsers(array $users): void
    {
        file_put_contents(
            $this->usersFile(),
            json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    /**
     * Выполняет $fn под эксклюзивной блокировкой реестра пользователей
     * (защита от гонок между --listen и cron).
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    private function withUserLock(callable $fn)
    {
        $handle = fopen(__DIR__ . '/logs/glopro_users.lock', 'c');
        flock($handle, LOCK_EX);
        try {
            return $fn();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    // ---------------------------------------------------------------------
    // Telegram
    // ---------------------------------------------------------------------

    private function sendTelegram(int|string $chatId, string $text): bool
    {
        $allOk = true;
        foreach ($this->splitMessage($text) as $chunk) {
            if (!$this->sendTelegramChunk($chatId, $chunk)) {
                $allOk = false;
            }
        }
        return $allOk;
    }

    private function sendTelegramChunk(int|string $chatId, string $text): bool
    {
        $url = 'https://api.telegram.org/bot' . $this->tgToken . '/sendMessage';
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false || $httpCode >= 400) {
            return false;
        }

        $res = json_decode((string)$response, true);
        return isset($res['ok']) && $res['ok'] === true;
    }

    /**
     * Режет сообщение на куски не больше 4096 символов (лимит Telegram).
     *
     * @return string[]
     */
    private function splitMessage(string $text): array
    {
        if (mb_strlen($text) <= 4096) {
            return [$text];
        }

        $chunks = [];
        $current = '';
        foreach (explode("\n", $text) as $line) {
            if (mb_strlen($current) + mb_strlen($line) + 1 > 4096) {
                if ($current !== '') {
                    $chunks[] = $current;
                }
                $current = $line;
            } else {
                $current = ($current === '' ? '' : $current . "\n") . $line;
            }
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    // ---------------------------------------------------------------------
    // Утилиты
    // ---------------------------------------------------------------------

    private function fetchUrl(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            CURLOPT_ENCODING => '',
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // Принудительно IPv4
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        // Если нужно увидеть детальный лог подключения:
        // $verbose = fopen('php://temp', 'w+');
        // curl_setopt($ch, CURLOPT_VERBOSE, true);
        // curl_setopt($ch, CURLOPT_STDERR, $verbose);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($response === false) {
            throw new RuntimeException('Сетевая ошибка при обращении к Redmine: ' . $error);
        }
        if ($httpCode !== 200) {
            throw new RuntimeException("Redmine вернул HTTP {$httpCode}");
        }

        return (string)$response;
    }

    /**
     * Чистит ссылку, присланную пользователем: убирает пробелы/переносы строк
     * и процентно кодирует не-ASCII символы (например, ✓ в utf8=✓).
     */
    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        $url = preg_replace('/\s+/', '', $url);

        return (string)preg_replace_callback('/[^\x20-\x7E]/u', static function (array $m): string {
            return rawurlencode($m[0]);
        }, $url);
    }

    private function readOffset(string $file): int
    {
        $saved = trim((string)@file_get_contents($file));
        return $saved !== '' && ctype_digit($saved) ? (int)$saved : 0;
    }

    private function saveOffset(string $file, int $offset): void
    {
        file_put_contents($file, (string)$offset, LOCK_EX);
    }

    private function ensureLogsDir(): void
    {
        $dir = __DIR__ . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    private function log(string $msg): void
    {
        $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $msg);
        echo $line;
        if ($this->logPrefix !== null) {
            $file = __DIR__ . '/logs/' . $this->logPrefix . '_' . date('Y-m-d') . '.log';
            file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        }
    }

    private function out(string $text): void
    {
        echo $text;
    }
}

exit(new GloProTrackerBot()->run($argv ?? []));
