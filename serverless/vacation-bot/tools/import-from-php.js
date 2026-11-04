import { spawnSync } from 'child_process';
import { readFileSync } from 'fs';

/**
 * Перенос данных из PHP-версии vacation_bot в Serverless.
 *
 * Перед запуском:
 *   1. Впиши свой Telegram ID в переменную ADMIN_ID ниже.
 *   2. Разверни бота: npx tgcloud login && npx tgcloud push && npx tgcloud migrate
 *   3. Запусти: node tools/import-from-php.js
 */

const ADMIN_ID = '115462629'; // <-- впиши свой Telegram ID (узнать можно через /id у любого бота)

const isWindows = process.platform === 'win32';
const npxBin = isWindows ? 'cmd' : 'npx';

const logsDir = '../../public_html/scripts/logs';

function readJson(name) {
  try {
    return JSON.parse(readFileSync(`${logsDir}/${name}`, 'utf8'));
  } catch {
    return {};
  }
}

// --- Читаем старые JSON-файлы из PHP-версии ---

const usersObj = readJson('vacations_users.json');
const users = Object.values(usersObj ?? {});

const recordsObj = readJson('vacations_records.json');
const records = (recordsObj && recordsObj.records) ?? [];

const sessionsObj = readJson('vacations_sessions.json');
const sessions = Object.values(sessionsObj ?? {});

console.log(`Найдено: пользователей=${users.length}, записей=${records.length}, сессий=${sessions.length}`);

if (!ADMIN_ID) {
  console.error('Ошибка: укажи ADMIN_ID в начале скрипта.');
  process.exit(1);
}

if (users.length === 0 && records.length === 0 && sessions.length === 0) {
  console.log('Нет данных для импорта.');
  process.exit(0);
}

const payload = JSON.stringify({ users, records, sessions });
const ctx = JSON.stringify({ initData: { user: { id: Number(ADMIN_ID) } } });

const npxArgs = isWindows
  ? ['/c', 'npx', 'tgcloud', 'run', 'endpoints/import', payload, '--ctx', ctx]
  : ['tgcloud', 'run', 'endpoints/import', payload, '--ctx', ctx];

console.log('Запуск импорта в Serverless...');
const result = spawnSync(npxBin, npxArgs, {
  stdio: 'inherit',
  cwd: process.cwd(),
  encoding: 'utf8',
});

if (result.status === 0) {
  console.log('Импорт завершён.');
} else {
  console.error(`Импорт завершён с кодом ${result.status}.`);
  process.exit(1);
}
