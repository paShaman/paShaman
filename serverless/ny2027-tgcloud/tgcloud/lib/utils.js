import { SIZES, TZ } from './config.js';

export function esc(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

export function stripHtml(value) {
  return String(value)
    .replace(/<[^>]*>/g, '')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#039;/g, "'");
}

export function visibleLen(value) {
  return stripHtml(value).length;
}

export function splitMessage(text, limit = 3996) {
  if (text.length <= limit) return [text];

  const chunks = [];
  let current = '';

  for (const line of text.split('\n')) {
    const candidate = current ? `${current}\n${line}` : line;
    if (candidate.length <= limit) {
      current = candidate;
      continue;
    }
    if (current) {
      chunks.push(current);
      current = '';
    }
    let rest = line;
    while (rest.length > limit) {
      chunks.push(rest.slice(0, limit));
      rest = rest.slice(limit);
    }
    current = rest;
  }

  if (current) chunks.push(current);
  return chunks.length > 0 ? chunks : [text];
}

export function nowMoscow() {
  return new Intl.DateTimeFormat('sv-SE', {
    timeZone: TZ,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
    hourCycle: 'h23',
  }).format(new Date());
}

export function normalizeSpaces(value) {
  return String(value)
    .replace(/[\r\n\t\u00a0]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

export function validFio(value) {
  const fio = normalizeSpaces(value);
  if (fio.length < 5 || fio.length > 120) return false;
  if (/\d/.test(fio)) return false;

  const words = fio.split(' ').filter(Boolean);
  if (words.length < 2 || words.length > 6) return false;

  return words.every((word) => word.length >= 2 && /^[\p{L}\-.']+$/u.test(word));
}

export function validCompany(value) {
  const company = normalizeSpaces(value);
  return company.length >= 2 && company.length <= 120 && /[\p{L}\p{N}]/u.test(company);
}

export function btn(text, callbackData) {
  return { text, callback_data: callbackData };
}

export function inline(rows) {
  return { inline_keyboard: rows };
}

export function registerKeyboard() {
  return inline([[btn('📝 Заполнить анкету', 'reg:start')]]);
}

export function welcomeKeyboard(registered) {
  const gameRow = [btn('🎮 Сыграть в игру', 'game:start')];
  if (registered) {
    return inline([
      [btn('✏️ Изменить данные', 'reg:edit'), btn('📋 Моя анкета', 'reg:my')],
      [btn('🗑 Удалить анкету', 'reg:delete')],
      gameRow,
    ]);
  }
  return inline([
    [btn('📝 Заполнить анкету', 'reg:start')],
    gameRow,
  ]);
}

export function editKeyboard() {
  return inline([
    [btn('✏️ Изменить данные', 'reg:edit')],
    [btn('🗑 Удалить анкету', 'reg:delete')],
  ]);
}

export function sizeKeyboard() {
  const rows = [];
  let row = [];
  for (const size of SIZES) {
    row.push(btn(size, `reg:size:${size}`));
    if (row.length === 3) {
      rows.push(row);
      row = [];
    }
  }
  if (row.length > 0) rows.push(row);
  rows.push([btn('❌ Отмена', 'reg:cancel')]);
  return inline(rows);
}

export function confirmKeyboard() {
  return inline([
    [btn('✅ Подтвердить', 'reg:save'), btn('✏️ Изменить', 'reg:edit')],
    [btn('❌ Отмена', 'reg:cancel')],
  ]);
}

export function deleteConfirmKeyboard() {
  return inline([[btn('🗑 Да, удалить', 'reg:delete_confirm'), btn('↩️ Отмена', 'reg:delete_cancel')]]);
}

export function adminDeleteKeyboard(targetId, token) {
  return inline([
    [btn('🗑 Да, удалить', `adm:del:${targetId}:${token}`), btn('❌ Отмена', 'adm:cancel')],
  ]);
}

export function adminDeleteAllKeyboard(count) {
  return inline([
    [btn(`🗑 Удалить все (${count})`, 'adm:delall:confirm'), btn('❌ Отмена', 'adm:cancel')],
  ]);
}

export function broadcastConfirmKeyboard(total) {
  return inline([
    [btn(`✅ Отправить всем (${total})`, 'bc:send'), btn('❌ Отмена', 'bc:cancel')],
  ]);
}

export function recordSummaryHtml(record) {
  return `👤 <b>ФИО:</b> ${esc(record.fio)}\n🏢 <b>Компания:</b> ${esc(
    record.company,
  )}\n👕 <b>Размер:</b> ${esc(record.size)}`;
}

export function confirmHtml(data) {
  return `Проверьте данные анкеты:\n\n👤 <b>ФИО:</b> ${esc(
    data.fio ?? '',
  )}\n🏢 <b>Компания:</b> ${esc(data.company ?? '')}\n👕 <b>Размер:</b> ${esc(
    data.size ?? '',
  )}\n\nВсё верно?`;
}

export function successHtml(record) {
  return `🎉 <b>Анкета заполнена!</b>\n\n${recordSummaryHtml(
    record,
  )}\n\n📅 <b>24.12.2026, Санкт-Петербург</b>\n⛸ 13:30–16:00 — ICEBURG ARENA, Парашютная ул., 11\n🏒 19:00 — СКА Арена, Шанхайские Драконы — Динамо Мск\n\nЖдём вас! 🐐`;
}

export function formatBroadcastHtml(text) {
  return `📢 <b>Сообщение от GloPro</b>\n──────────\n\n${esc(
    text,
  )}\n\n──────────\n🐐 <i>Корпоратив NY2027</i>`;
}

export function recordVersion(record) {
  return String(record?.createdAt ?? '').replace(/\D/g, '') || '0';
}

function csvQuote(value) {
  return `"${String(value ?? '').replace(/"/g, '""')}"`;
}

export function buildCsv(active, archive) {
  const rows = [
    ...active.map((record) => ({ record, status: 'Активна', deletedAt: '' })),
    ...archive.map((record) => ({
      record,
      status: 'Удалена',
      deletedAt: String(record.deletedAt ?? ''),
    })),
  ];
  rows.sort((a, b) => String(a.record.createdAt).localeCompare(String(b.record.createdAt)));

  const header = [
    'Дата заполнения анкеты',
    'ФИО',
    'Компания',
    'Размер',
    'Telegram ID',
    'Username',
    'Имя в Telegram',
    'Обновлено',
    'Статус',
    'Удалено',
  ].map(csvQuote);

  const lines = [header.join(';')];
  for (const row of rows) {
    const record = row.record;
    lines.push(
      [
        record.createdAt,
        record.fio,
        record.company,
        record.size,
        record.chatId,
        record.username,
        record.tgName,
        record.updatedAt,
        row.status,
        row.deletedAt,
      ]
        .map(csvQuote)
        .join(';'),
    );
  }

  return `\uFEFF${lines.join('\r\n')}\r\n`;
}
