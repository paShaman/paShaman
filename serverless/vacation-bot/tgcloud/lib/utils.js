import { ABSENCE_TYPES, QUOTA_TYPES, TZ } from './config.js';

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

export function splitMessage(text, limit = 4096) {
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

export function normalizeSpaces(value) {
  return String(value)
    .replace(/[\r\n\t\u00a0]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

export function now() {
  return new Date();
}

export function nowMoscow() {
  return new Intl.DateTimeFormat('sv-SE', {
    timeZone: 'Europe/Moscow',
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

export function shortDate(ymd, withYear = true) {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(ymd));
  if (!m) return String(ymd);
  return withYear ? `${m[3]}.${m[2]}.${m[1]}` : `${m[3]}.${m[2]}`;
}

export function formatInterval(from, to) {
  if (from === to) return shortDate(from);
  if (String(from).slice(0, 7) === String(to).slice(0, 7)) {
    return `${String(from).slice(8, 10)}–${shortDate(to)}`;
  }
  return `${shortDate(from)} – ${shortDate(to)}`;
}

export function daysInRange(from, to) {
  const a = new Date(from + 'T00:00:00');
  const b = new Date(to + 'T00:00:00');
  return Math.round((b - a) / 86400000) + 1;
}

export function effectiveTo(record) {
  const end = String(record.date_to ?? '');
  const finished = String(record.finished_at ?? '');
  if (finished !== '' && finished < end) return finished;
  return end;
}

export function effectiveDays(record) {
  return daysInRange(String(record.date_from), effectiveTo(record));
}

export function isActiveOn(record, date) {
  return String(record.date_from) <= date && date <= effectiveTo(record);
}

export function overlapsRange(record, from, to) {
  return String(record.date_from) <= to && effectiveTo(record) >= from;
}

export function daysInYear(record, year) {
  const yearStart = `${String(year).padStart(4, '0')}-01-01`;
  const yearEnd = `${String(year).padStart(4, '0')}-12-31`;
  const from = String(record.date_from) > yearStart ? String(record.date_from) : yearStart;
  const to = effectiveTo(record) < yearEnd ? effectiveTo(record) : yearEnd;
  if (from > to) return 0;
  return daysInRange(from, to);
}

export function parseRanges(input) {
  input = String(input).replace(/[—–]/g, '-').trim();

  const lower = input.toLowerCase();
  if (lower === 'сегодня' || lower === 'завтра') {
    const day = lower === 'сегодня' ? new Date() : new Date(Date.now() + 86400000);
    const ymd = day.toISOString().slice(0, 10);
    return [{ from: ymd, to: ymd }];
  }

  const parts = input.split(/[,;\n]+/);
  const ranges = [];

  for (const part of parts) {
    let p = String(part).trim().replace(/\s*-\s*/, '-');
    if (p === '') continue;

    let m;
    if ((m = /^(\d{1,2})\.(\d{1,2})\.(\d{4})-(\d{1,2})\.(\d{1,2})\.(\d{4})$/.exec(p))) {
      ranges.push({
        from: `${m[3]}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`,
        to: `${m[6]}-${m[5].padStart(2, '0')}-${m[4].padStart(2, '0')}`,
      });
    } else if ((m = /^(\d{1,2})\.(\d{1,2})-(\d{1,2})\.(\d{1,2})$/.exec(p))) {
      const y = monthDayYear(Number(m[2]), Number(m[1]));
      let from = `${String(y).padStart(4, '0')}-${String(m[2]).padStart(2, '0')}-${String(m[1]).padStart(2, '0')}`;
      let to = `${String(y).padStart(4, '0')}-${String(m[4]).padStart(2, '0')}-${String(m[3]).padStart(2, '0')}`;
      if (to < from) {
        const d = new Date(to + 'T00:00:00');
        d.setFullYear(d.getFullYear() + 1);
        to = d.toISOString().slice(0, 10);
      }
      ranges.push({ from, to });
    } else if ((m = /^(\d{1,2})\.(\d{1,2})(?:\.(\d{4}))?(?:\s*\+\s*|\s+)(\d{1,3})\s*(?:день|дня|дней|day|days)?$/i.exec(p))) {
      const year = m[3] ? Number(m[3]) : monthDayYear(Number(m[2]), Number(m[1]));
      const count = Number(m[4]);
      if (count < 1) throw new Error('Количество дней должно быть больше нуля.');
      const from = new Date(`${String(year).padStart(4, '0')}-${String(m[2]).padStart(2, '0')}-${String(m[1]).padStart(2, '0')}T00:00:00`);
      const to = new Date(from.getTime() + (count - 1) * 86400000);
      ranges.push({
        from: from.toISOString().slice(0, 10),
        to: to.toISOString().slice(0, 10),
      });
    } else if ((m = /^(\d{1,2})\.(\d{1,2})\.(\d{4})$/.exec(p))) {
      const ymd = `${m[3]}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`;
      ranges.push({ from: ymd, to: ymd });
    } else if ((m = /^(\d{1,2})\.(\d{1,2})$/.exec(p))) {
      const y = monthDayYear(Number(m[2]), Number(m[1]));
      const ymd = `${String(y).padStart(4, '0')}-${String(m[2]).padStart(2, '0')}-${String(m[1]).padStart(2, '0')}`;
      ranges.push({ from: ymd, to: ymd });
    } else {
      throw new Error(`Не понял даты: «${part}». Формат: 01.07.2026-14.07.2026 или 01.07-14.07.`);
    }

    if (ranges[ranges.length - 1].to < ranges[ranges.length - 1].from) {
      throw new Error(`Дата начала позже даты окончания: «${part}».`);
    }
  }

  if (ranges.length === 0) throw new Error('Не указаны даты.');
  return ranges;
}

export function monthDayYear(month, day) {
  const year = new Date().getFullYear();
  const candidate = new Date(`${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}T00:00:00`);
  const threshold = new Date(Date.now() - DATE_ROLLOVER_DAYS * 86400000);
  threshold.setHours(0, 0, 0, 0);
  return candidate < threshold ? year + 1 : year;
}

export function parseYear(arg) {
  arg = String(arg ?? '').trim();
  return /^\d{4}$/.test(arg) ? Number(arg) : null;
}

export function typeLabel(type) {
  const emoji = ABSENCE_TYPES[type] ?? '•';
  return `${emoji} ${type}`;
}

export function recordSummary(record) {
  return `${typeLabel(record.type)} · ${formatInterval(record.date_from, effectiveTo(record))} (${effectiveDays(record)} к.д.)`;
}

export function btn(text, callbackData) {
  return { text, callback_data: callbackData };
}

export function inline(rows) {
  return { inline_keyboard: rows };
}

export function menuKeyboard(requesterId, isAdmin) {
  const rows = [
    [btn('➕ Добавить', 'menu:add'), btn('📋 Мои', 'menu:my')],
    [btn('🏖 Остаток', 'menu:left'), btn('📌 Сегодня', 'menu:today')],
    [btn('📅 Неделя', 'menu:week'), btn('🗓 Месяц', 'menu:month')],
    [btn('🔜 Скоро', 'menu:soon'), btn('👥 Сотрудники', 'menu:users')],
    [btn('🗂 Все отсутствия', 'menu:all')],
  ];
  if (isAdmin) rows.push([btn('⚙️ Админ: сотрудники', 'menu:admin')]);
  rows.push([btn('❓ Помощь', 'menu:help')]);
  return inline(rows);
}

export function displayName(user) {
  const name = String(user.name ?? '').trim();
  if (name !== '') return name;
  const username = String(user.username ?? '').trim();
  return username !== '' ? `@${username}` : String(user.id ?? '—');
}

export function displayNameForId(users, id) {
  const user = users.find((u) => String(u.id) === String(id));
  return user ? displayName(user) : `ID ${id}`;
}
