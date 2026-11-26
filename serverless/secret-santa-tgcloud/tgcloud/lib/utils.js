import { MAX_URL_LENGTH, TZ, TZ_OFFSET } from './config.js';

export function esc(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

export function stripHtml(value) {
  return String(value ?? '')
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
  const source = String(text ?? '');
  if (source.length <= limit) return [source];

  const chunks = [];
  let current = '';

  for (const line of source.split('\n')) {
    const candidate = current ? `${current}\n${line}` : line;
    if (candidate.length <= limit) {
      current = candidate;
      continue;
    }
    if (current) chunks.push(current);
    let rest = line;
    current = '';
    while (rest.length > limit) {
      chunks.push(rest.slice(0, limit));
      rest = rest.slice(limit);
    }
    current = rest;
  }

  if (current) chunks.push(current);
  return chunks.length > 0 ? chunks : [source];
}

export function normalizeSpaces(value) {
  return String(value ?? '')
    .replace(/[\r\n\t\u00a0]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

export function nowSec() {
  return Math.floor(Date.now() / 1000);
}

function moscowParts(date = new Date()) {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: TZ,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    hourCycle: 'h23',
  }).formatToParts(date);
  const get = (type) => parts.find((part) => part.type === type)?.value ?? '';
  return {
    year: get('year'),
    month: get('month'),
    day: get('day'),
    hour: get('hour'),
    minute: get('minute'),
  };
}

export function moscowToday() {
  const p = moscowParts();
  return new Date(`${p.year}-${p.month}-${p.day}T00:00:00${TZ_OFFSET}`);
}

export function parseDateRu(value) {
  const match = String(value ?? '').trim().match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/);
  if (!match) return null;
  const day = Number(match[1]);
  const month = Number(match[2]);
  const year = Number(match[3]);
  if (day < 1 || day > 31 || month < 1 || month > 12 || year < 2024 || year > 2100) {
    return null;
  }
  const iso = `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(
    day,
  ).padStart(2, '0')}T00:00:00${TZ_OFFSET}`;
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return null;
  const check = moscowParts(date);
  if (
    Number(check.year) !== year ||
    Number(check.month) !== month ||
    Number(check.day) !== day
  ) {
    return null;
  }
  return date;
}

export function formatDateRu(date) {
  if (!date) return '—';
  return new Intl.DateTimeFormat('ru-RU', {
    timeZone: TZ,
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  }).format(new Date(date));
}

export function formatDateTimeRu(date) {
  if (!date) return '—';
  return new Intl.DateTimeFormat('ru-RU', {
    timeZone: TZ,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(date));
}

export function formatMoney(value, currency = 'RUB') {
  const number = Number(value);
  if (!Number.isFinite(number)) return '—';
  try {
    return new Intl.NumberFormat('ru-RU', {
      style: 'currency',
      currency,
      maximumFractionDigits: 0,
    }).format(number);
  } catch {
    return `${number} ${currency}`;
  }
}

export function formatBudget(min, max, currency = 'RUB') {
  if (min === max) return formatMoney(min, currency);
  return `${formatMoney(min, currency)} — ${formatMoney(max, currency)}`;
}

export function formatPriceRange(min, max, currency = 'RUB') {
  if (min == null && max == null) return '';
  if (min != null && max != null) return formatBudget(min, max, currency);
  return formatMoney(min ?? max, currency);
}

export function validUrl(value) {
  const text = String(value ?? '').trim();
  if (!text) return true;
  if (text.length > MAX_URL_LENGTH) return false;
  try {
    const url = new URL(text);
    return url.protocol === 'http:' || url.protocol === 'https:';
  } catch {
    return false;
  }
}

export function normalizeCode(value) {
  return String(value ?? '')
    .trim()
    .toUpperCase()
    .replace(/[^A-Z0-9]/g, '');
}

export function codeFromStartPayload(payload) {
  const text = String(payload ?? '').trim();
  if (!text.toLowerCase().startsWith('join_')) return null;
  return normalizeCode(text.slice(5)) || null;
}

export function displayName(user) {
  if (!user) return 'Участник';
  const first = String(user.firstName ?? '').trim();
  const last = String(user.lastName ?? '').trim();
  const full = `${first} ${last}`.trim();
  if (full) return full;
  const username = String(user.username ?? '').trim();
  return username ? `@${username}` : 'Участник';
}

export function pluralRu(n, one, few, many) {
  const value = Math.abs(Number(n) || 0);
  const mod10 = value % 10;
  const mod100 = value % 100;
  if (mod10 === 1 && mod100 !== 11) return one;
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return few;
  return many;
}

export function clampInt(value, min, max) {
  const number = Number(value);
  if (!Number.isFinite(number)) return null;
  return Math.min(max, Math.max(min, Math.trunc(number)));
}

export function parsePositiveInt(value) {
  const text = String(value ?? '').trim();
  if (!/^\d+$/.test(text)) return null;
  const number = Number(text);
  return Number.isSafeInteger(number) ? number : null;
}

export function toBase36(value) {
  return Number(value).toString(36);
}

export function fromBase36(value) {
  const text = String(value ?? '').trim().toLowerCase();
  if (!/^[0-9a-z]+$/.test(text)) return null;
  const number = parseInt(text, 36);
  return Number.isSafeInteger(number) && number > 0 ? number : null;
}

export function callbackLength(data) {
  return new TextEncoder().encode(String(data)).length;
}

export function isPast(date) {
  if (!date) return false;
  return new Date(date).getTime() < Date.now();
}

export function sameMoscowDay(a, b) {
  const pa = moscowParts(new Date(a));
  const pb = moscowParts(new Date(b));
  return pa.year === pb.year && pa.month === pb.month && pa.day === pb.day;
}

export function truncate(value, limit) {
  const text = String(value ?? '');
  return text.length <= limit ? text : `${text.slice(0, Math.max(0, limit - 1))}…`;
}

export function bytesToBase64(bytes) {
  const abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
  let result = '';
  for (let i = 0; i < bytes.length; i += 3) {
    const a = bytes[i];
    const b = i + 1 < bytes.length ? bytes[i + 1] : 0;
    const c = i + 2 < bytes.length ? bytes[i + 2] : 0;
    const triple = (a << 16) | (b << 8) | c;
    result += abc[(triple >> 18) & 63];
    result += abc[(triple >> 12) & 63];
    result += i + 1 < bytes.length ? abc[(triple >> 6) & 63] : '=';
    result += i + 2 < bytes.length ? abc[triple & 63] : '=';
  }
  return result;
}

export function base64ToBytes(value) {
  const text = String(value ?? '').replace(/\s+/g, '');
  if (!text || text.length % 4 !== 0 || !/^[A-Za-z0-9+/]+={0,2}$/.test(text)) return null;
  const abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
  const lookup = new Map([...abc].map((char, index) => [char, index]));
  const bytes = [];
  for (let i = 0; i < text.length; i += 4) {
    const chunk = text.slice(i, i + 4);
    const values = [...chunk].map((char) => (char === '=' ? 0 : lookup.get(char)));
    if (values.some((v) => v === undefined)) return null;
    const triple = (values[0] << 18) | (values[1] << 12) | (values[2] << 6) | values[3];
    bytes.push((triple >> 16) & 255);
    if (chunk[2] !== '=') bytes.push((triple >> 8) & 255);
    if (chunk[3] !== '=') bytes.push(triple & 255);
  }
  return Uint8Array.from(bytes);
}

export function detectImageMime(bytes) {
  if (!bytes || bytes.length < 4) return null;
  if (bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff) return 'image/jpeg';
  if (
    bytes[0] === 0x89 &&
    bytes[1] === 0x50 &&
    bytes[2] === 0x4e &&
    bytes[3] === 0x47
  ) {
    return 'image/png';
  }
  return null;
}
