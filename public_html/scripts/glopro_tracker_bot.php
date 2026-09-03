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
 *   /issues                  — текущие задачи
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
 *
 * cron
 *   powershell -Command "while ($true) { php glopro_tracker_bot.php; Start-Sleep -Seconds 300 }"
 */
final class GloProTrackerBot
{
    /** Пространство имён Atom-фида Redmine. */
    private const ATOM_NS = 'http://www.w3.org/2005/Atom';

    /** Лимит символов для rich message (sendRichMessage). */
    private const RICH_MAX = 32768;
    /** Лимит символов для обычного sendMessage. */
    private const TEXT_MAX = 4096;

    /** Токен Telegram-бота из окружения (GLOPRO_TG_TOKEN). */
    private ?string $tgToken = null;
    /** Префикс имени файла лога (glopro_cron / glopro_listen). */
    private ?string $logPrefix = null;
    /** Флаг: логировать сырые апдейты getUpdates (для отладки). */
    private bool $logTgUpdates = false;

    public function __construct()
    {
        // Токен берём из окружения; если не задан — остаётся null (нужен только в --listen).
        $this->tgToken = trim((string)getenv('GLOPRO_TG_TOKEN')) ?: null;

        // Часовой пояс по умолчанию — Europe/Moscow, переопределяется GLOPRO_TZ.
        date_default_timezone_set(trim((string)getenv('GLOPRO_TZ')) ?: 'Europe/Moscow');

        $this->ensureLogsDir();
    }

    /**
     * Точка входа: диспетчер режимов запуска по аргументам командной строки.
     *
     * --listen — long-polling (интерактивный режим, слушает команды в Telegram).
     * --users  — вывод реестра зарегистрированных пользователей.
     * (без флагов) — cron: проверка задач для всех пользователей.
     */
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

        // Нет ни одного зарегистрированного пользователя — делать нечего.
        $users = $this->loadUsers();
        if ($users === []) {
            $this->log('Нет зарегистрированных пользователей — нечего проверять.');
            return 0;
        }

        $this->log('Запуск cron: пользователей ' . count($users));

        // Проверяем задачи и шлём уведомления каждому пользователю отдельно.
        foreach ($users as $chatId => $user) {
            $this->processUser($chatId, $user);
        }

        $this->log('Cron завершён.');
        return 0;
    }

    /**
     * Обрабатывает одного пользователя: скачивает его фид, сравнивает с прошлым
     * состоянием и при наличии изменений отправляет уведомление в Telegram.
     *
     * @param array<string, mixed> $user
     */
    private function processUser(string $chatId, array $user): void
    {
        $url = trim((string)($user['atom_url'] ?? ''));
        if ($url === '') {
            $this->log("⚠️ Пользователь {$chatId}: нет atom_url, пропуск.");
            return;
        }

        // Скачиваем актуальный список задач по ссылке пользователя.
        try {
            $issues = $this->fetchIssues($url);
        } catch (RuntimeException $e) {
            // Redmine недоступен или вернул ошибку — пропускаем, не трогая состояние.
            $this->log("❌ Пользователь {$chatId}: " . $e->getMessage());
            return;
        }

        // Сравниваем новое состояние с сохранённым и сразу обновляем файл состояния.
        $stateFile = $this->stateFile($chatId);
        $changes = $this->diffIssues($this->loadState($stateFile), $issues);
        $this->saveState($stateFile, $issues);

        if ($changes === []) {
            $this->log("Пользователь {$chatId}: изменений нет (задач " . count($issues) . ').');
            return;
        }

        // Формируем HTML-сообщение об изменениях и отправляем в чат пользователя.
        $message = $this->buildChangesMessage($changes, parse_url($url, PHP_URL_HOST) ?: 'Redmine');
        $ok = $this->sendTelegram($chatId, $message);
        $this->log(($ok ? '✅' : '❌') . " Пользователь {$chatId}: изменений " . count($changes) . ($ok ? '' : ' (ошибка отправки)') . '.');
    }

    // ---------------------------------------------------------------------
    // Long-polling
    // ---------------------------------------------------------------------

    private function listen(): int
    {
        // Режим --listen требует токен (в cron-режиме он не нужен).
        if ($this->tgToken === null || $this->tgToken === '') {
            $this->out("Ошибка: не задан GLOPRO_TG_TOKEN.\n");
            return 2;
        }

        $this->logPrefix = 'glopro_listen';
        $offsetFile = __DIR__ . '/logs/glopro.offset';

        // Продолжаем с последнего обработанного update_id (чтобы не потерять апдейты).
        $lastUpdateId = $this->readOffset($offsetFile);
        $this->log("🚀 Long-polling запущен, offset={$lastUpdateId}");

        // Бесконечный цикл long-polling: блокирующий getUpdates с длинным таймаутом.
        while (true) {
            try {
                // offset+1 — запрашиваем только новые апдейты.
                $updates = $this->getUpdates($lastUpdateId + 1);
                if ($updates === null) {
                    // Сетевой сбой или ошибка API — ждём и пробуем снова.
                    sleep(5);
                    continue;
                }

                foreach ($updates as $update) {
                    // Фиксируем offset ДО обработки, чтобы при сбое апдейт не пришёл повторно.
                    $updateId = $update['update_id'] ?? null;
                    if ($updateId !== null) {
                        $lastUpdateId = (int)$updateId;
                        $this->saveOffset($offsetFile, $lastUpdateId);
                    }

                    // Нас интересуют только сообщения (allowed_updates = ["message"]).
                    if (isset($update['message'])) {
                        $this->handleMessage($update['message']);
                    }
                }
            } catch (\Throwable $e) {
                // Любая непредвиденная ошибка не должна ронять процесс — логируем и ждём.
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
        // Long polling: timeout=50 — сервер держит соединение до 50 сек, пока не появятся апдейты.
        $params = [
            'offset' => $offset,
            'timeout' => 50,
            'allowed_updates' => json_encode(['message']),
        ];
        $url = 'https://api.telegram.org/bot' . $this->tgToken . '/getUpdates?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,   // чуть больше, чем timeout long-polling
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        // Проблемы на уровне соединения — вернём null (вызывающий подождёт и повторит).
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
            // Фатальные ошибки API: неверный токен (401) или конфликт (409 — уже работает
            // другой экземпляр бота) — продолжать бессмысленно, завершаем процесс.
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

        $updates = $data['result'] ?? [];

        // Отладочный лог сырых апдейтов (включается вызовом setLogTgUpdates или вручную).
        if ($updates !== [] && $this->logTgUpdates) {
            $this->log('TG getUpdates: апдейтов ' . count($updates) . "\n"
                . $this->truncate(json_encode($updates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 2000));
        }

        return $updates;
    }

    /**
     * Разбирает сообщение от пользователя и отвечает на команду.
     *
     * @param array<string, mixed> $message
     */
    private function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $from = $message['from'] ?? [];
        $fromId = $from['id'] ?? null;
        $text = trim((string)($message['text'] ?? ''));

        // Пустые сообщения (например, media без текста) пропускаем.
        if ($text === '' || $chatId === null) {
            return;
        }

        // Разделяем команду и аргумент: "/setkey https://... " -> ["/setkey", "https://..."].
        $parts = preg_split('/\s+/', $text, 2);
        $rawCommand = $parts[0] ?? '';
        $arg = isset($parts[1]) ? trim($parts[1]) : '';

        // Отрезаем @botname, если команда пришла в группе.
        $command = explode('@', $rawCommand, 2)[0];

        // Собираем имя пользователя для реестра (first_name + last_name, либо @username).
        $name = trim((string)($from['first_name'] ?? ''));
        if (isset($from['last_name'])) {
            $name = trim($name . ' ' . (string)$from['last_name']);
        }
        $username = (string)($from['username'] ?? '');

        // Диспетчер команд. match не знает команды — вернёт null, и мы просто молчим.
        $reply = match ($command) {
            '/start', '/help' => $this->cmdHelp(),
            '/chatid', '/id' => "🆔: {$chatId}",
            '/setkey', '/key' => $this->cmdSetKey((string)$chatId, $name, $username, $arg),
            '/status' => $this->cmdStatus((string)$chatId),
            '/stop', '/unsub' => $this->cmdStop((string)$chatId),
            '/issues' => $this->cmdIssues((string)$chatId),
            default => null,
        };

        if ($reply === null) {
            return;
        }

        // Отправляем ответ и пишем в лог факт команды (с хостом Redmine для /setkey).
        $ok = $this->sendTelegram($chatId, $reply);

        $extra = '';
        if (in_array($command, ['/setkey', '/key'], true) && $arg !== '') {
            $extra = ', host=' . (parse_url($this->normalizeUrl($arg), PHP_URL_HOST) ?: '?');
        }
        $this->log(($ok ? '✅' : '❌') . " {$command} от from_id=" . var_export($fromId, true) . ", chat={$chatId}{$extra}" . ($ok ? '' : ' (ошибка отправки)'));
    }

    private function cmdHelp(): string
    {
        return "<b>Привет! Я слежу за задачами в Redmine и присылаю изменения.</b>\n\n"
            . "1. Открой нужный фильтр задач в Redmine и скопируй ссылку «Atom» (issues.atom) целиком, с key=...\n"
            . "2. Пришли её командой:\n<code>/setkey &lt;ссылка&gt;</code>\n\n"
            . "Дальше я сам буду проверять задачи и сообщать о новых, смене статуса и обновлениях.\n\n"
            . "Команды: <code>/status</code> — статус, <code>/issues</code> — текущие задачи, <code>/stop</code> — отключить, <code>/chatid</code> — ID чата.";
    }

    private function cmdSetKey(string $chatId, string $name, string $username, string $arg): string
    {
        $url = $this->normalizeUrl($arg);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return "<b>Пришли ссылку целиком:</b>\n<code>/setkey https://cp.glopro.ru/issues.atom?...&amp;key=...</code>\n\n"
                . 'Скопируй её из Redmine (кнопка «Atom» внизу списка задач).';
        }

        // Проверяем ссылку «живым» запросом: если фид не грузится — не регистрируем.
        try {
            $issues = $this->fetchIssues($url);
        } catch (RuntimeException $e) {
            return 'Не удалось получить задачи по этой ссылке: ' . $this->esc($e->getMessage()) . "\n\n"
                . 'Проверь, что ссылка скопирована целиком (с key=...). '
                . 'Если Redmine временно недоступен — попробуй ещё раз через пару минут.';
        }

        // Разбираем фильтры из ссылки, чтобы показывать их в /status.
        $filters = $this->parseAtomFilters($url);

        // Сохраняем пользователя в реестр под блокировкой (защита от гонок с cron).
        $this->withUserLock(function () use ($chatId, $name, $username, $url, $filters) {
            $users = $this->loadUsers();
            $users[$chatId] = [
                'chat_id'    => $chatId,
                'name'       => $name,
                'username'   => $username,
                'atom_url'   => $url,
                'filters'    => $filters,
                'created_at' => date('c'),
            ];
            $this->saveUsers($users);
        });

        // Сохраняем текущее состояние, чтобы первый cron не прислал все задачи как новые.
        $this->saveState($this->stateFile($chatId), $issues);

        $message = "<b>Готово!</b> Ключ принят, задач в выдаче: <b>" . count($issues) . '</b>';
        if ($filters !== []) {
            $message .= "\n\n🔎 Фильтры:\n"
                . implode("\n", array_map(fn(string $f): string => '  • ' . $f, $filters));
        }
        $message .= "\n\nТеперь я буду сообщать об изменениях. Проверить подписку: <code>/status</code>";

        return $message;
    }

    private function cmdStatus(string $chatId): string
    {
        $users = $this->loadUsers();
        if (!isset($users[$chatId])) {
            return "❌ Ты ещё не зарегистрирован.\n\nОтправь /setkey <ссылка issues.atom>, чтобы начать получать уведомления.";
        }

        $url = (string)($users[$chatId]['atom_url'] ?? '');
        $host = parse_url($url, PHP_URL_HOST) ?: '—';

        // Показываем время последней успешной проверки из файла состояния.
        $savedAt = $this->loadStateSavedAt($this->stateFile($chatId));
        $lastCheck = '—';
        if ($savedAt !== null) {
            $lastCheck = (new DateTimeImmutable($savedAt))->format('d.m.Y H:i');
        }

        // Фильтры сохраняются при /setkey; для старых записей разбираем URL на лету.
        $filters = (array)($users[$chatId]['filters'] ?? []);
        if ($filters === []) {
            $filters = $this->parseAtomFilters($url);
        }

        $lines = ['<b>📊 Статус подписки</b>'];
        $lines[] = '🐞 Redmine: <code>' . $this->esc($host) . '</code>';
        $lines[] = '✅ Подписка: активна';
        $lines[] = '🕒 Последняя проверка: ' . $lastCheck;

        if ($filters !== []) {
            $lines[] = '';
            $lines[] = '🔎 Фильтры (' . count($filters) . '):';
            foreach ($filters as $filter) {
                $lines[] = '  • ' . $filter;
            }
        }

        $lines[] = '';
        $lines[] = 'Команды: <code>/issues</code> — текущие задачи, <code>/stop</code> — отключить.';

        return implode("\n", $lines);
    }

    private function cmdStop(string $chatId): string
    {
        // Удаляем пользователя из реестра под блокировкой.
        $removed = false;
        $this->withUserLock(function () use ($chatId, &$removed) {
            $users = $this->loadUsers();
            if (isset($users[$chatId])) {
                unset($users[$chatId]);
                $this->saveUsers($users);
                $removed = true;
            }
        });

        // Заодно удаляем файл состояния, чтобы при повторной подписке cron
        // не прислал все текущие задачи как «новые».
        $stateFile = $this->stateFile($chatId);
        if (is_file($stateFile)) {
            @unlink($stateFile);
        }

        return $removed ? 'Подписка отключена. Чтобы вернуться — <code>/setkey &lt;ссылка&gt;</code>.' : 'Ты и так не был подписан.';
    }

    private function cmdIssues(string $chatId): string
    {
        $users = $this->loadUsers();
        $url = trim((string)($users[$chatId]['atom_url'] ?? ''));
        if ($url === '') {
            return 'Сначала зарегистрируйся: <code>/setkey &lt;ссылка issues.atom&gt;</code>.';
        }

        // По команде /issues показываем актуальный список задач, не трогая сохранённое состояние.
        try {
            $issues = $this->fetchIssues($url);
        } catch (RuntimeException $e) {
            return 'Ошибка: ' . $this->esc($e->getMessage());
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

        // Таблица: chat_id | имя | хост Redmine.
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

        // Парсим Atom-фид как XML. LIBXML_NONET запрещает сетевые подгрузки DTD,
        // libxml_use_internal_errors — подавляет warnings при мусорном XML.
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

        // Каждый <atom:entry> — одна задача.
        $issues = [];
        foreach ($xpath->query('//atom:entry') as $entry) {
            if ($entry instanceof DOMElement) {
                $issues[] = $this->parseEntry($entry, $xpath);
            }
        }

        // Для каждой задачи уточняем реальную дату последнего обновления и автора
        // по её собственному Atom-фиду (в фиде списка эти данные неточные).
        return $this->enrichIssues($issues, $url);
    }

    /**
     * Уточняет для каждой задачи реальную дату последнего обновления и того, кто её
     * обновил. По каждой задаче запрашивается её собственный Atom-фид
     * (https://<host>/issues/<id>.atom?key=...): <updated> и <author> в общем фиде
     * списка соответствуют не последнему изменению, а неактуальному updated_on.
     *
     * Запросы выполняются параллельно порциями, чтобы не растягивать cron.
     *
     * @param array<int, array<string, mixed>> $issues
     * @return array<int, array<string, mixed>>
     */
    private function enrichIssues(array $issues, string $feedUrl): array
    {
        // Уже загружен детальный фид одной задачи (issues/<id>.atom): его записи —
        // это и есть журнал изменений, уточнять нечего.
        if (preg_match('#/issues/\d+\.atom$#', (string)parse_url($feedUrl, PHP_URL_PATH))) {
            return $issues;
        }
        if ($issues === []) {
            return $issues;
        }

        $parts = parse_url($feedUrl);
        if (!isset($parts['scheme'], $parts['host'])) {
            return $issues;
        }
        parse_str((string)($parts['query'] ?? ''), $params);
        $key = trim((string)($params['key'] ?? ''));
        if ($key === '') {
            return $issues;
        }

        // Индексируем позиции записей по id задачи.
        $posById = [];
        foreach ($issues as $i => $issue) {
            $id = (int)$issue['id'];
            if ($id > 0) {
                $posById[$id][] = $i;
            }
        }
        if ($posById === []) {
            return $issues;
        }

        $base = $parts['scheme'] . '://' . $parts['host'] . '/issues/';

        // Скачиваем детальные фиды задач порциями (не больше 8 параллельных запросов).
        foreach (array_chunk(array_keys($posById), 8) as $chunk) {
            $details = $this->fetchIssueDetailsBatch($base, $key, $chunk);
            foreach ($details as $id => $detail) {
                foreach ($posById[$id] as $i) {
                    $issues[$i]['updated'] = $detail['updated'];
                    $issues[$i]['updated_by'] = $detail['updated_by'];
                }
            }
        }

        return $issues;
    }

    /**
     * Параллельно скачивает Atom-фиды набора задач и возвращает для каждого id
     * дату последнего изменения и автора, сделавшего его.
     *
     * @param int[] $ids
     * @return array<int, array{updated: array{unix: int, text: string}, updated_by: string}>
     */
    private function fetchIssueDetailsBatch(string $base, string $key, array $ids): array
    {
        $mh = curl_multi_init();
        $handles = [];
        foreach ($ids as $id) {
            $url = $base . $id . '.atom?' . http_build_query(['key' => $key]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                CURLOPT_ENCODING => '',
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $handles[$id] = $ch;
            curl_multi_add_handle($mh, $ch);
        }

        // Крутим мульти-curl, пока все запросы не завершатся.
        $running = 0;
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 1.0);
            }
        } while ($running && $status === CURLM_OK);

        $details = [];
        foreach ($handles as $id => $ch) {
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $body = curl_multi_getcontent($ch);
            curl_multi_remove_handle($mh, $ch);

            if ($body === false || $httpCode !== 200) {
                $this->log("⚠️ Не удалось получить детали задачи #{$id}: Redmine вернул HTTP {$httpCode}");
                continue;
            }

            $detail = $this->parseIssueDetailFeed((string)$body);
            if ($detail !== null) {
                $details[$id] = $detail;
            }
        }
        curl_multi_close($mh);

        return $details;
    }

    /**
     * Парсит Atom-фид одной задачи и возвращает данные последней записи журнала —
     * реальную дату и автора последнего изменения.
     *
     * @return array{updated: array{unix: int, text: string}, updated_by: string}|null
     */
    private function parseIssueDetailFeed(string $xml): ?array
    {
        $dom = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$loaded) {
            return null;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('atom', self::ATOM_NS);

        // Каждая <atom:entry> в фиде задачи — одна запись журнала (изменение).
        // Ищем самую свежую запись по дате, а не по позиции в документе.
        $bestEntry = null;
        $bestUnix = -1;
        foreach ($xpath->query('//atom:entry') as $entry) {
            if (!$entry instanceof DOMElement) {
                continue;
            }
            $iso = trim($xpath->evaluate('string(atom:updated)', $entry));
            if ($iso === '') {
                continue;
            }
            try {
                $unix = (new DateTimeImmutable($iso))->getTimestamp();
            } catch (\Throwable) {
                continue;
            }
            // >= — при одинаковых датах берём запись, идущую позже в документе.
            if ($bestEntry === null || $unix >= $bestUnix) {
                $bestEntry = $entry;
                $bestUnix = $unix;
            }
        }

        if ($bestEntry === null) {
            return null;
        }

        return [
            'updated'    => $this->parseDate(trim($xpath->evaluate('string(atom:updated)', $bestEntry))),
            'updated_by' => trim($xpath->evaluate('string(atom:author/atom:name)', $bestEntry)),
        ];
    }

    /**
     * Извлекает из одного <atom:entry> все поля задачи.
     *
     * @return array<string, mixed>
     */
    private function parseEntry(DOMElement $entry, DOMXPath $xpath): array
    {
        // Заголовок задачи, ссылка на неё, дата обновления, автор и описание.
        $title = trim($xpath->evaluate('string(atom:title)', $entry));
        $link = trim($xpath->evaluate('string(atom:link[@rel="alternate"]/@href)', $entry));
        if ($link === '') {
            // Запасной вариант: берём первую ссылку без указания rel.
            $link = trim($xpath->evaluate('string(atom:link/@href)', $entry));
        }
        $updated = trim($xpath->evaluate('string(atom:updated)', $entry));
        $author = trim($xpath->evaluate('string(atom:author/atom:name)', $entry));
        $description = trim($xpath->evaluate('string(atom:content)', $entry));

        $parsed = $this->parseTitle($title);

        return [
            'id'          => $parsed['id'],
            'project'     => $parsed['project'],
            'tracker'     => $parsed['tracker'],
            'status'      => $parsed['status'],
            'subject'     => $parsed['subject'],
            'url'         => $link,
            'updated'     => $this->parseDate($updated),
            'updated_by'  => '',
            'author'      => $author,
            'description' => $description,
        ];
    }

    /**
     * Разбирает заголовок задачи вида "Project - Tracker #123 (Status): Subject"
     * или без проекта: "Tracker #123 (Status): Subject".
     *
     * @return array{id: int, project: string, tracker: string, status: string, subject: string}
     */
    private function parseTitle(string $title): array
    {
        // Основной формат Redmine: "Проект - Трекер #123 (Статус): Тема".
        if (preg_match('/^(?P<project>.+?)\s+-\s+(?P<tracker>.+?)\s+#(?P<id>\d+)\s+\((?P<status>[^)]+)\):\s*(?P<subject>.*)$/su', $title, $m)) {
            return [
                'id'      => (int)$m['id'],
                'project' => trim($m['project']),
                'tracker' => trim($m['tracker']),
                'status'  => trim($m['status']),
                'subject' => trim($m['subject']),
            ];
        }

        // Без префикса проекта: "Tracker #123 (Status): Subject" — трекер встаёт на место проекта.
        if (preg_match('/^(?P<tracker>.+?)\s+#(?P<id>\d+)\s+\((?P<status>[^)]+)\):\s*(?P<subject>.*)$/su', $title, $m)) {
            return [
                'id'      => (int)$m['id'],
                'project' => '',
                'tracker' => trim($m['tracker']),
                'status'  => trim($m['status']),
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
            'id'      => $id,
            'project' => '',
            'tracker' => '',
            'status'  => $status,
            'subject' => $title,
        ];
    }

    /**
     * Приводит ISO-дату из фида к массиву: unix-время и форматированный текст.
     *
     * Фиды Redmine отдают время в UTC (суффикс Z), а бот работает в таймзоне
     * GLOPRO_TZ (по умолчанию Europe/Moscow) — текст форматируем именно в ней.
     *
     * @return array{unix: int, text: string}
     */
    private function parseDate(string $iso): array
    {
        if ($iso === '') {
            return ['unix' => 0, 'text' => ''];
        }

        // setTimezone обязателен: DateTimeImmutable с явным "Z" иначе сохраняет UTC.
        $dt = (new DateTimeImmutable($iso))->setTimezone(new DateTimeZone(date_default_timezone_get()));
        return [
            'unix' => $dt->getTimestamp(),
            'text' => $dt->format('d.m.Y H:i'),
        ];
    }

    /**
     * Разбирает atom_url и возвращает читаемый список непустых фильтров Redmine.
     *
     * Поддерживает и новый формат (f[]/op[]/v[]), и классические параметры
     * вида status_id=open&assigned_to_id=me. Служебные параметры (key, sort,
     * utf8, set_filter, group_by, c[], t[]...) игнорируются.
     *
     * @return string[] строки вида «Статус: не 3, 10» или «Статус: открытые»
     */
    private function parseAtomFilters(string $url): array
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if ($query === null || $query === '') {
            return [];
        }
        parse_str($query, $params);

        // Человекочитаемые названия полей фильтра.
        $labels = [
            'query_id'         => 'Сохранённый запрос',
            'project_id'       => 'Проект',
            'tracker_id'       => 'Трекер',
            'status_id'        => 'Статус',
            'priority_id'      => 'Приоритет',
            'author_id'        => 'Автор',
            'assigned_to_id'   => 'Исполнитель',
            'category_id'      => 'Категория',
            'fixed_version_id' => 'Версия',
            'subject'          => 'Тема',
            'watcher_id'       => 'Наблюдатель',
            'last_updated_by'  => 'Последний обновивший',
        ];

        // Операторы фильтра и их текст. Пустая строка — значение перечисляется как есть.
        $ops = [
            '='  => '',
            '!'  => '',
            'o'  => 'открытые',
            'c'  => 'закрытые',
            '*'  => 'любые',
            '!*' => 'без значения',
            '~'  => 'содержит: ',
            '!~' => 'не содержит: ',
            '><' => 'между ',
            '>=' => '≥ ',
            '<=' => '≤ ',
            '>'  => '> ',
            '<'  => '< ',
            't'  => 'сегодня',
            'y'  => 'вчера',
            'w'  => 'текущая неделя',
            'lw' => 'прошлая неделя',
            'm'  => 'текущий месяц',
        ];

        // Особые значения фильтров (специальные значения Redmine).
        $valueNames = [
            'me'          => 'Я',
            'my_projects' => 'мои проекты',
            'subprojects' => 'и подпроекты',
        ];

        // Названия статусов GloPro Redmine (id => название).
        $statusNames = [
            '1'  => 'Новая',
            '11' => 'Принято',
            '2'  => 'В работе',
            '3'  => 'Решена',
            '4'  => 'Обратная связь',
            '9'  => 'Тестирование',
            '10' => 'В препрод',
            '8'  => 'В продакшн',
            '5'  => 'Закрыта',
            '6'  => 'Отклонена',
            '12' => 'Технический долг',
        ];

        // Новый формат: f[]=status_id&op[status_id]=o&v[status_id][]=...
        $fields = is_array($params['f'] ?? null) ? array_values($params['f']) : [];
        $filters = [];

        foreach ($fields as $field) {
            $field = trim((string)$field);
            if ($field === '' || !isset($labels[$field])) {
                continue;
            }

            $op = (string)($params['op'][$field] ?? '=');
            $values = is_array($params['v'][$field] ?? null) ? $params['v'][$field] : [];

            $pretty = [];
            foreach ($values as $value) {
                $value = trim((string)$value);
                if ($value === '') {
                    continue;
                }
                $pretty[] = $this->esc($valueNames[$value] ?? ($field === 'status_id' ? ($statusNames[$value] ?? $value) : $value));
            }

            $label = $labels[$field];
            $opText = $ops[$op] ?? ($op !== '' && $op !== '=' ? $op . ' ' : '');

            // Операторы без значений (открытые/закрытые/любые) — выводим только текст.
            if (in_array($op, ['o', 'c', '*', '!*', 't', 'y', 'w', 'lw', 'm'], true)) {
                $filters[] = $label . ': ' . $opText;
            } elseif ($pretty !== []) {
                // Отрицание выделяем явно: «Статус: НЕ (значения)».
                if ($op === '!') {
                    $filters[] = $label . ' (<b>НЕ</b>): ' . implode(', ', $pretty);
                } else {
                    $filters[] = $label . ': ' . $opText . implode(', ', $pretty);
                }
            }
        }

        // Классический формат: status_id=open&assigned_to_id=me&...
        foreach ($params as $key => $value) {
            if (!is_string($key) || !isset($labels[$key]) || in_array($key, $fields, true)) {
                continue;
            }

            $values = is_array($value) ? array_values($value) : [$value];
            $pretty = [];
            foreach ($values as $v) {
                $v = trim((string)$v);
                if ($v === '') {
                    continue;
                }

                // Отрицание в классическом формате: status_id=!5.
                $negated = false;
                if ($v[0] === '!') {
                    $negated = true;
                    $v = trim(substr($v, 1));
                    if ($v === '') {
                        continue;
                    }
                }

                if ($v === 'open') {
                    $pretty[] = ($negated ? '<b>НЕ</b> ' : '') . 'открытые';
                    continue;
                }
                if ($v === 'closed') {
                    $pretty[] = ($negated ? '<b>НЕ</b> ' : '') . 'закрытые';
                    continue;
                }
                $name = $valueNames[$v] ?? ($key === 'status_id' ? ($statusNames[$v] ?? $v) : $v);
                $pretty[] = ($negated ? '<b>НЕ</b> ' : '') . $this->esc($name);
            }

            if ($pretty !== []) {
                $filters[] = $labels[$key] . ': ' . implode(', ', $pretty);
            }
        }

        return $filters;
    }

    /**
     * Собирает «краткое» сообщение для /issues: заголовок, счётчик и таблица задач.
     *
     * @param array<int, array<string, mixed>> $issues
     */
    private function buildSummary(array $issues, string $host): string
    {
        $count = count($issues);
        $lines = ['<b>🐞 Redmine:</b> ' . $this->esc($host), "📋 Задач в выдаче: <b>{$count}</b>"];

        if ($count === 0) {
            return implode("\n", $lines) . "\n";
        }

        $lines[] = '';
        $lines = array_merge($lines, $this->buildTable($issues));

        return implode("\n", $lines);
    }

    /**
     * Строит HTML-таблицу задач: Номер | Статус | Тема | Обновлено.
     *
     * @param array<int, array<string, mixed>> $issues
     * @param array<int, string> $oldStatuses id => прежний статус (для «было:»)
     * @return string[]
     */
    private function buildTable(array $issues, array $oldStatuses = []): array
    {
        $lines = ['<table bordered>'];
        $lines[] = '<tr><th>Номер</th><th>Статус</th><th>Тема</th><th>Обновлено</th></tr>';

        foreach ($issues as $issue) {
            $id = $issue['id'];
            // Номер задачи делаем ссылкой на саму задачу в Redmine (если ссылка есть).
            $number = $id && $issue['url'] !== ''
                ? '<a href="' . $this->esc($issue['url']) . '"><b>' . $id . '</b></a>'
                : ($id ? '<b>' . $id . '</b>' : '—');

            $status = $issue['status'] !== '' ? '<i>' . $this->esc($issue['status']) . '</i>' : '—';
            // Для сменивших статус задач приписываем прежний статус мелким текстом.
            if (isset($oldStatuses[$id]) && $oldStatuses[$id] !== '') {
                $status .= ' <br><mark>(было: ' . $this->esc($oldStatuses[$id]) . ')</mark>';
            }

            $lines[] = '<tr>';
            $lines[] = '  <td>' . $number . '</td>';
            $lines[] = '  <td>' . $status . '</td>';
            $lines[] = '  <td>' . $this->esc($issue['subject']) . '</td>';

            // «Обновлено»: реальные дата и автор последнего изменения (из фида задачи).
            $updated = $issue['updated']['text'];
            $updatedBy = trim((string)($issue['updated_by'] ?? ''));
            if ($updatedBy !== '') {
                $updated .= '<br><i>' . $this->esc($updatedBy) . '</i>';
            }
            $lines[] = '  <td>' . $updated . '</td>';
            $lines[] = '</tr>';
        }

        $lines[] = '</table>';

        return $lines;
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

        // Файл состояния: {"saved_at": ..., "issues": [...]}.
        $data = json_decode($raw, true);
        return is_array($data['issues'] ?? null) ? $data['issues'] : [];
    }

    /**
     * Возвращает время последней успешной проверки из файла состояния (для /status).
     */
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
     * Сохраняет состояние задач пользователя вместе с меткой времени.
     *
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
     * Логика:
     * - задачи, которых не было раньше — type=new;
     * - сменился статус — type=status (со старым статусом);
     * - статус тот же, но updated новее — type=updated;
     * - задачи, пропавшие из выдачи — type=removed.
     *
     * @param array<int, array<string, mixed>> $prev
     * @param array<int, array<string, mixed>> $cur
     * @return array<int, array{type: string, issue: array<string, mixed>, old_status?: string}>
     */
    private function diffIssues(array $prev, array $cur): array
    {
        // Индексируем оба списка по id задачи для быстрого поиска.
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
                // Раньше задачи не было — она новая.
                $changes[] = ['type' => 'new', 'issue' => $issue];
                continue;
            }

            $old = $prevById[$id];
            if ($old['status'] !== $issue['status']) {
                $changes[] = ['type' => 'status', 'issue' => $issue, 'old_status' => (string)$old['status']];
            } elseif (($issue['updated']['unix'] ?? 0) > ($old['updated']['unix'] ?? 0)) {
                // Статус не менялся, но задача была обновлена (комментарий, поля и т.п.).
                $changes[] = ['type' => 'updated', 'issue' => $issue, 'old_status' => (string)$old['status']];
            }
        }

        foreach ($prevById as $id => $old) {
            if (!isset($curById[$id])) {
                // Задача пропала из выдачи (например, изменён фильтр).
                //$changes[] = ['type' => 'removed', 'issue' => $old];
            }
        }

        return $changes;
    }

    /**
     * Группирует изменения по типу и собирает итоговое HTML-сообщение.
     *
     * @param array<int, array{type: string, issue: array<string, mixed>, old_status?: string}> $changes
     */
    private function buildChangesMessage(array $changes, string $host): string
    {
        $count = count($changes);
        $lines = ['<b>🐞 Redmine:</b> ' . $this->esc($host), "🛠️ Изменений: {$count}"];

        // Сгруппируем изменения по типу: new / status / updated.
        $groups = [];
        foreach ($changes as $change) {
            $groups[$change['type']][] = $change;
        }

        $labels = [
            'new'     => '🆕 Новые задачи',
            'status'  => '🔁 Сменили статус',
            'updated' => '📝 Обновлены',
        ];

        // Каждую группу выводим отдельным блоком со своей таблицей.
        foreach ($labels as $type => $label) {
            if (empty($groups[$type])) {
                continue;
            }

            $issues = [];
            $oldStatuses = [];
            foreach ($groups[$type] as $change) {
                $issues[] = $change['issue'];
                if ($type === 'status') {
                    $oldStatuses[(int)$change['issue']['id']] = (string)$change['old_status'];
                }
            }

            $lines[] = '';
            $lines[] = '<b>' . $label . '</b>';
            $lines = array_merge($lines, $this->buildTable($issues, $oldStatuses));
        }

        return implode("\n", $lines);
    }

    // ---------------------------------------------------------------------
    // Пользователи (реестр)
    // ---------------------------------------------------------------------

    /** Путь к реестру пользователей. */
    private function usersFile(): string
    {
        return __DIR__ . '/logs/glopro_users.json';
    }

    /** Путь к файлу состояния задач конкретного пользователя. */
    private function stateFile(string $chatId): string
    {
        return __DIR__ . '/logs/glopro_state_' . $chatId . '.json';
    }

    /**
     * Загружает реестр пользователей (chat_id => данные пользователя).
     *
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
     * Сохраняет реестр пользователей на диск.
     *
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
        // Открываем файл блокировки (создаётся при необходимости) и берём LOCK_EX.
        $handle = fopen(__DIR__ . '/logs/glopro_users.lock', 'c');
        flock($handle, LOCK_EX);
        try {
            return $fn();
        } finally {
            // finally гарантирует снятие блокировки даже при исключении внутри $fn.
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    // ---------------------------------------------------------------------
    // Telegram
    // ---------------------------------------------------------------------

    /**
     * Отправляет HTML-сообщение. Rich message целиком (лимит 32768), без разбивки;
     * при неудаче — фолбек на sendMessage с разбивкой по 4096.
     */
    private function sendTelegram(int|string $chatId, string $html): bool
    {
        // Пробуем rich message: поддерживает таблицы/details и вмещает до 32768 символов.
        if (mb_strlen($html) <= self::RICH_MAX && $this->sendRichMessage($chatId, $html)) {
            return true;
        }

        // Rich message не прошёл (ошибка API, слишком длинное) — конвертируем в обычный HTML.
        $fallback = $this->richToSendMessageHtml($html);

        $allOk = true;
        foreach ($this->splitMessage($fallback) as $chunk) {
            // Сначала с parse_mode=HTML.
            if ($this->sendMessage($chatId, $chunk, 'HTML')) {
                continue;
            }
            // Если HTML не принят (кривой тег) — отправляем как plain text.
            if ($this->sendMessage($chatId, html_entity_decode(strip_tags($chunk)), null)) {
                continue;
            }
            $allOk = false;
        }

        return $allOk;
    }

    /**
     * Отправляет rich message (Telegram Bot API sendRichMessage).
     * Позволяет использовать в HTML details/summary/таблицы.
     */
    private function sendRichMessage(int|string $chatId, string $html): bool
    {
        $url = 'https://api.telegram.org/bot' . $this->tgToken . '/sendRichMessage';

        $postFields = [
            'chat_id'      => $chatId,
            // В rich HTML переносы строк схлопываются, поэтому \n -> <br>.
            'rich_message' => json_encode(['html' => str_replace("\n", '<br>', $html)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false || $httpCode >= 400) {
            return false;
        }

        $result = json_decode((string)$response, true);
        return isset($result['ok']) && $result['ok'] === true;
    }

    /**
     * Отправляет обычное сообщение (sendMessage) с optional parse_mode.
     */
    private function sendMessage(int|string $chatId, string $text, ?string $parseMode = null): bool
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true, // не разворачиваем превью ссылок в сообщении
        ];
        if ($parseMode !== null) {
            $payload['parse_mode'] = $parseMode;
        }

        $ch = curl_init('https://api.telegram.org/bot' . $this->tgToken . '/sendMessage');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false || $httpCode >= 400) {
            return false;
        }

        $result = json_decode((string)$response, true);
        return isset($result['ok']) && $result['ok'] === true;
    }

    /**
     * Убирает из rich HTML теги, непонятные sendMessage parse_mode=HTML
     * (details/summary/p/таблицы), оставляя b/i/code/a/pre.
     */
    private function richToSendMessageHtml(string $html): string
    {
        // Блочные теги заменяем на перенос строки, ячейки таблиц — на пробелы.
        $patterns = [
            '#</?(?:details|summary|p|table|thead|tbody|tr|div|h6)>#' => "\n",
            '#<br\s*/?>#' => "\n",
            '#</?small>#' => '',
            '#<(td|th)[^>]*>#' => ' ',
            '#</(td|th)>#' => ' ',
        ];

        $html = (string)preg_replace(array_keys($patterns), array_values($patterns), $html);

        return trim($html);
    }

    /**
     * Обрезает текст для логов, добавляя счётчик скрытых символов.
     */
    private function truncate(string $text, int $max = 500): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max) . '… (+' . (mb_strlen($text) - $max) . ' симв.)';
    }

    /**
     * Режет сообщение на куски не больше 4096 символов (лимит Telegram).
     * Разрез старается делать по границе строк, чтобы не рвать HTML-теги.
     *
     * @return string[]
     */
    private function splitMessage(string $text): array
    {
        if (mb_strlen($text) <= self::TEXT_MAX) {
            return [$text];
        }

        $chunks = [];
        $current = '';
        foreach (explode("\n", $text) as $line) {
            // Строка не влезает в текущий кусок — закрываем кусок и начинаем новый.
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

    /**
     * Скачивает URL через cURL и возвращает тело ответа.
     * Кидает RuntimeException при сетевой ошибке или HTTP != 200.
     */
    private function fetchUrl(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true, // редиректы Redmine
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', // имитируем браузер
            CURLOPT_ENCODING => '', // автоматическое сжатие (gzip)
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
        // Telegram может вставить перевод строки при переносе ссылки — склеиваем.
        $url = preg_replace('/\s+/', '', $url);

        // Каждый символ вне диапазона ASCII кодируем как %XX (для корректного HTTP-запроса).
        return (string)preg_replace_callback('/[^\x20-\x7E]/u', static function (array $m): string {
            return rawurlencode($m[0]);
        }, $url);
    }

    /**
     * Читает сохранённый offset long-polling (последний обработанный update_id).
     */
    private function readOffset(string $file): int
    {
        $saved = trim((string)@file_get_contents($file));
        return $saved !== '' && ctype_digit($saved) ? (int)$saved : 0;
    }

    /**
     * Сохраняет offset long-polling на диск.
     */
    private function saveOffset(string $file, int $offset): void
    {
        file_put_contents($file, (string)$offset, LOCK_EX);
    }

    /**
     * Гарантирует наличие каталога логов.
     */
    private function ensureLogsDir(): void
    {
        $dir = __DIR__ . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    /**
     * Пишет строку в stdout и в дневной файл лога (glopro_<prefix>_<дата>.log),
     * если задан logPrefix.
     */
    private function log(string $msg): void
    {
        $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $msg);
        echo $line;
        if ($this->logPrefix !== null) {
            $file = __DIR__ . '/logs/' . $this->logPrefix . '_' . date('Y-m-d') . '.log';
            file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        }
    }

    /** Печать в stdout без доп. форматирования (для режимов --users и т.п.). */
    private function out(string $text): void
    {
        echo $text;
    }

    /** HTML-экранирование текста (защита от битых тегов в сообщениях от Telegram). */
    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

exit(new GloProTrackerBot()->run($argv ?? []));
