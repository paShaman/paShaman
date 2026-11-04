import { db } from 'sdk';
import { and, asc, eq } from 'sdk/db';
import { users, records, sessions } from '../schema.js';
import { ADMIN_ID, DEFAULT_ANNUAL_DAYS, QUOTA_TYPES, SESSION_TTL } from './config.js';
import { daysInRange, effectiveTo, daysInYear, overlapsRange, formatInterval, nowMoscow } from './utils.js';

function nowSec() {
  return Math.floor(Date.now() / 1000);
}

// ---------------------------------------------------------------------------
// Пользователи
// ---------------------------------------------------------------------------

export async function loadUsers() {
  return db.select().from(users).all();
}

export async function findUser(id) {
  const row = await db.select().from(users).where(eq(users.id, Number(id))).get();
  return row ?? null;
}

export async function isAdmin(id) {
  return String(id) === String(ADMIN_ID);
}

export async function isAllowed(id) {
  if (await isAdmin(id)) return true;
  const user = await findUser(id);
  return user !== null && user.active === true;
}

export async function touchUser(id, from) {
  const first = String(from.first_name ?? '').trim();
  const last = String(from.last_name ?? '').trim();
  let name = first;
  if (last) name = name ? `${name} ${last}` : last;
  const username = String(from.username ?? '');

  const user = await findUser(id);
  if (!user) return;

  const currentName = String(user.name ?? '');
  const nameLocked = Boolean(user.name_manual);
  let newName = currentName;
  if (name && !nameLocked) newName = name;

  if (newName === currentName && String(user.username ?? '') === username) return;

  await db
    .update(users)
    .set({ name: newName, username })
    .where(eq(users.id, Number(id)))
    .run();
}

export async function addUser(id, name, username, role, annualDays) {
  const existing = await findUser(id);
  if (existing) {
    await db
      .update(users)
      .set({ name, username, role, active: true, annual_days: annualDays ?? null })
      .where(eq(users.id, Number(id)))
      .run();
    return;
  }
  await db
    .insert(users)
    .values({
      id: Number(id),
      name,
      username: username ?? '',
      role: role ?? 'user',
      annual_days: annualDays ?? null,
      active: true,
      created_at: nowMoscow(),
    })
    .run();
}

export async function deactivateUser(id) {
  const user = await findUser(id);
  if (!user) return false;
  await db
    .update(users)
    .set({ active: false })
    .where(eq(users.id, Number(id)))
    .run();
  return true;
}

export async function activateUser(id) {
  const user = await findUser(id);
  if (!user) return false;
  await db
    .update(users)
    .set({ active: true })
    .where(eq(users.id, Number(id)))
    .run();
  return true;
}

// ---------------------------------------------------------------------------
// Записи отсутствий
// ---------------------------------------------------------------------------

export async function loadRecords() {
  const rows = await db.select().from(records).orderBy(asc(records.date_from), asc(records.id)).all();
  return { next_id: 1, records: rows };
}

export async function findRecord(id) {
  return db.select().from(records).where(eq(records.id, Number(id))).get();
}

export async function recordsForUserYear(userId, year) {
  const rows = await db.select().from(records).where(eq(records.user_id, Number(userId))).all();
  const result = [];
  for (const r of rows) {
    if (daysInYear(r, year) <= 0) continue;
    result.push(r);
  }
  result.sort((a, b) => String(a.date_from).localeCompare(String(b.date_from)));
  return result;
}

export async function plannedRecords(userId, fromYear) {
  const start = `${String(fromYear).padStart(4, '0')}-01-01`;
  const rows = await db.select().from(records).where(eq(records.user_id, Number(userId))).all();
  const result = [];
  for (const r of rows) {
    if (effectiveTo(r) < start) continue;
    result.push(r);
  }
  result.sort((a, b) => String(a.date_from).localeCompare(String(b.date_from)));
  return result;
}

export async function planYears(userId) {
  const current = new Date().getFullYear();
  const years = { [current]: true };
  const rows = await db.select().from(records).where(eq(records.user_id, Number(userId))).all();
  for (const r of rows) {
    const from = Number(String(r.date_from).slice(0, 4));
    const to = Number(String(effectiveTo(r)).slice(0, 4));
    for (let y = Math.max(from, current); y <= to; y++) years[y] = true;
  }
  return Object.keys(years).map(Number).sort((a, b) => a - b);
}

export async function numberedRecords(userId, year) {
  const recordsList = year === null
    ? await plannedRecords(userId, new Date().getFullYear())
    : await recordsForUserYear(userId, year);
  return recordsList.map((r, i) => ({ num: i + 1, record: r }));
}

export async function recordByNumber(userId, number, year) {
  const numbered = await numberedRecords(userId, year);
  return numbered.find((n) => n.num === number)?.record ?? null;
}

export async function numberOfRecord(userId, recordId, year) {
  const numbered = await numberedRecords(userId, year);
  const found = numbered.find((n) => n.record.id === recordId);
  return found ? found.num : null;
}

export async function numberOfRecordById(recordId, fallbackUserId, year) {
  const record = await findRecord(recordId);
  const ownerId = record ? String(record.user_id) : String(fallbackUserId);
  return numberOfRecord(ownerId, recordId, year);
}

export async function addRecords(userId, type, ranges, comment) {
  const now = nowMoscow();
  const created = [];

  for (const range of ranges) {
    const days = daysInRange(range.from, range.to);
    const record = {
      user_id: Number(userId),
      type,
      date_from: range.from,
      date_to: range.to,
      days,
      comment,
      finished_at: null,
      created_at: now,
      updated_at: now,
    };
    await db.insert(records).values(record).run();
    created.push(record);
  }

  return created;
}

export async function deleteRecord(id, requesterId) {
  const record = await findRecord(id);
  if (!record) return { ok: false, err: 'not_found' };
  if (String(record.user_id) !== String(requesterId) && !(await isAdmin(requesterId))) {
    return { ok: false, err: 'not_owner' };
  }
  await db.delete(records).where(eq(records.id, Number(id))).run();
  return { ok: true, rec: record };
}

export async function updateRecordDates(id, requesterId, range) {
  const record = await findRecord(id);
  if (!record) return { ok: false, err: 'not_found' };
  if (String(record.user_id) !== String(requesterId) && !(await isAdmin(requesterId))) {
    return { ok: false, err: 'not_owner' };
  }
  const days = daysInRange(range.from, range.to);
  await db
    .update(records)
    .set({ date_from: range.from, date_to: range.to, days, finished_at: null, updated_at: nowMoscow() })
    .where(eq(records.id, Number(id)))
    .run();
  return { ok: true };
}

export async function updateRecordComment(id, requesterId, comment) {
  const record = await findRecord(id);
  if (!record) return { ok: false, err: 'not_found' };
  if (String(record.user_id) !== String(requesterId) && !(await isAdmin(requesterId))) {
    return { ok: false, err: 'not_owner' };
  }
  await db
    .update(records)
    .set({ comment, updated_at: nowMoscow() })
    .where(eq(records.id, Number(id)))
    .run();
  return { ok: true };
}

export async function finishRecord(id, requesterId, date) {
  const record = await findRecord(id);
  if (!record) return { ok: false, err: 'not_found' };
  if (String(record.user_id) !== String(requesterId) && !(await isAdmin(requesterId))) {
    return { ok: false, err: 'not_owner' };
  }
  if (date < String(record.date_from) || date > String(record.date_to)) {
    return { ok: false, err: 'out_of_range' };
  }
  await db
    .update(records)
    .set({ finished_at: date, updated_at: nowMoscow() })
    .where(eq(records.id, Number(id)))
    .run();
  return { ok: true };
}

// ---------------------------------------------------------------------------
// Сессии диалога
// ---------------------------------------------------------------------------

export async function getSession(userId) {
  const row = await db.select().from(sessions).where(eq(sessions.user_id, Number(userId))).get();
  if (!row) return null;
  if (Number(row.ts) < nowSec() - SESSION_TTL) {
    await clearSession(userId);
    return null;
  }
  return row.data ?? null;
}

export async function setSession(userId, patch) {
  const now = nowSec();
  const current = (await getSession(userId)) ?? {};
  const data = { ...current, ...patch, ts: now };
  const existing = await db.select().from(sessions).where(eq(sessions.user_id, Number(userId))).get();
  if (existing) {
    await db
      .update(sessions)
      .set({ step: data.step, data, ts: now })
      .where(eq(sessions.user_id, Number(userId)))
      .run();
  } else {
    await db
      .insert(sessions)
      .values({ user_id: Number(userId), step: data.step, data, ts: now })
      .run();
  }
}

export async function clearSession(userId) {
  await db.delete(sessions).where(eq(sessions.user_id, Number(userId))).run();
}

// ---------------------------------------------------------------------------
// Отсутствия на дату
// ---------------------------------------------------------------------------

export async function findOverlaps(userId, ranges) {
  const result = [];
  const rows = await db.select().from(records).all();
  for (const r of rows) {
    const own = String(r.user_id) === String(userId);
    for (const range of ranges) {
      if (overlapsRange(r, range.from, range.to)) {
        result.push({ name: own ? 'вы' : '', interval: formatInterval(r.date_from, effectiveTo(r)), own });
        break;
      }
    }
  }
  return result;
}

// ---------------------------------------------------------------------------
// Остаток отпуска
// ---------------------------------------------------------------------------

export async function balanceFor(userId, year) {
  const user = await findUser(userId);
  let norm = DEFAULT_ANNUAL_DAYS;
  let defaultNorm = true;
  if (user && Number.isInteger(user.annual_days) && user.annual_days > 0) {
    norm = user.annual_days;
    defaultNorm = false;
  }

  let used = 0;
  const recs = [];
  const rows = await db.select().from(records).where(eq(records.user_id, Number(userId))).all();
  for (const r of rows) {
    if (!QUOTA_TYPES.includes(String(r.type))) continue;
    const days = daysInYear(r, year);
    if (days <= 0) continue;
    used += days;
    recs.push(r);
  }
  recs.sort((a, b) => String(a.date_from).localeCompare(String(b.date_from)));

  return { norm, used, remaining: norm - used, default_norm: defaultNorm, records: recs };
}
