import { db } from 'sdk';
import { and, asc, desc, eq } from 'sdk/db';
import { broadcastDrafts, registrations, sessions, tickets, users } from '../schema.js';
import { SESSION_TTL, SIZES } from './config.js';
import { nowMoscow } from './utils.js';

// ---------------------------------------------------------------------------
// Пользователи
// ---------------------------------------------------------------------------

export async function rememberUser(chatId, from) {
  const id = Number(chatId);
  if (!Number.isFinite(id) || id <= 0) return;

  const old = await db.select().from(users).where(eq(users.chatId, id)).get();
  const username = String(from.username ?? '').trim();
  const name = `${String(from.first_name ?? '')} ${String(from.last_name ?? '')}`.trim();

  // Не трогаем базу на каждое сообщение: только новый пользователь, смена имени или разблокировка.
  if (old && old.username === username && old.name === name && !old.blocked) return;

  const now = nowMoscow();
  const record = {
    chatId: id,
    telegramId: Number(from.id ?? id),
    username,
    name,
    firstSeen: old?.firstSeen ?? now,
    lastSeen: now,
    blocked: false,
    blockedAt: null,
  };

  await db
    .insert(users)
    .values(record)
    .onConflictDoUpdate({
      target: users.chatId,
      set: {
        telegramId: record.telegramId,
        username,
        name,
        lastSeen: now,
        blocked: false,
        blockedAt: null,
      },
    })
    .run();

  if (!old) {
    console.log(
      `Новый пользователь бота: ID=${id}, username=${username ? `@${username}` : '—'}, имя=${
        name || '—'
      }`,
    );
  }
}

export async function loadUsers() {
  return db.select().from(users).all();
}

// ---------------------------------------------------------------------------
// Анкеты
// ---------------------------------------------------------------------------

export async function findRecord(chatId) {
  const id = Number(chatId);
  return db
    .select()
    .from(registrations)
    .where(and(eq(registrations.chatId, id), eq(registrations.status, 'active')))
    .orderBy(desc(registrations.id))
    .limit(1)
    .get();
}

export async function saveRecord(chatId, from, fio, company, size) {
  const id = Number(chatId);
  const old = await findRecord(id);
  const now = nowMoscow();
  const username = String(from.username ?? '').trim();
  const tgName = `${String(from.first_name ?? '')} ${String(from.last_name ?? '')}`.trim();

  let record;

  if (old) {
    await db
      .update(registrations)
      .set({ fio, company, size, username, tgName, updatedAt: now })
      .where(eq(registrations.id, old.id))
      .run();

    record = { ...old, fio, company, size, username, tgName, updatedAt: now };
  } else {
    await db
      .insert(registrations)
      .values({
        chatId: id,
        fio,
        company,
        size,
        username,
        tgName,
        status: 'active',
        createdAt: now,
        updatedAt: now,
        deletedAt: null,
      })
      .run();

    record = (await findRecord(id)) ?? {
      chatId: id,
      fio,
      company,
      size,
      username,
      tgName,
      status: 'active',
      createdAt: now,
      updatedAt: now,
      deletedAt: null,
    };
  }

  console.log(
    `Анкета ${old ? 'обновлена' : 'создана'}: ID=${id}, username=${
      username ? `@${username}` : '—'
    }, ФИО=${fio}, компания=${company}, размер=${size}`,
  );

  return record;
}

export async function deleteRecord(chatId) {
  const id = Number(chatId);
  const old = await findRecord(id);
  if (!old) return null;

  const now = nowMoscow();
  await db
    .update(registrations)
    .set({ status: 'deleted', deletedAt: now, updatedAt: now })
    .where(eq(registrations.id, old.id))
    .run();

  console.log(
    `Анкета удалена: ID=${id}, username=${old.username ? `@${old.username}` : '—'}, ФИО=${
      old.fio
    }, компания=${old.company}, размер=${old.size}`,
  );

  return { ...old, status: 'deleted', deletedAt: now, updatedAt: now };
}

export async function deleteAllRecords() {
  const active = await loadRegistrations();
  if (active.length === 0) return 0;

  const now = nowMoscow();
  await db
    .update(registrations)
    .set({ status: 'deleted', deletedAt: now, updatedAt: now })
    .where(eq(registrations.status, 'active'))
    .run();

  for (const record of active) {
    console.log(
      `Анкета удалена: ID=${record.chatId}, username=${
        record.username ? `@${record.username}` : '—'
      }, ФИО=${record.fio}, компания=${record.company}, размер=${record.size}`,
    );
  }
  console.log(`Массовое удаление анкет: ${active.length}`);

  return active.length;
}

export async function loadRegistrations() {
  return db
    .select()
    .from(registrations)
    .where(eq(registrations.status, 'active'))
    .orderBy(asc(registrations.createdAt), asc(registrations.id))
    .all();
}

export async function loadArchive() {
  return db
    .select()
    .from(registrations)
    .where(eq(registrations.status, 'deleted'))
    .orderBy(asc(registrations.createdAt), asc(registrations.id))
    .all();
}

export async function statsData() {
  const active = await loadRegistrations();
  const archive = await loadArchive();
  const sizes = Object.fromEntries(SIZES.map((size) => [size, 0]));

  for (const record of active) {
    const size = String(record.size ?? '').toUpperCase();
    if (size in sizes) sizes[size]++;
  }

  return { total: active.length, deleted: archive.length, sizes };
}

// ---------------------------------------------------------------------------
// Сессии
// ---------------------------------------------------------------------------

export async function getSession(chatId) {
  const id = Number(chatId);
  const row = await db.select().from(sessions).where(eq(sessions.chatId, id)).get();
  if (!row) return null;
  if (row.updatedAt < Math.floor(Date.now() / 1000) - SESSION_TTL) return null;
  return row;
}

export async function setSession(chatId, session) {
  const id = Number(chatId);
  await db
    .insert(sessions)
    .values({
      chatId: id,
      step: session.step,
      data: session.data,
      updatedAt: session.updatedAt,
    })
    .onConflictDoUpdate({
      target: sessions.chatId,
      set: { step: session.step, data: session.data, updatedAt: session.updatedAt },
    })
    .run();
}

export async function clearSession(chatId) {
  const id = Number(chatId);
  await db.delete(sessions).where(eq(sessions.chatId, id)).run();
}

export async function clearAllSessions() {
  await db.run('DELETE FROM sessions');
}

// ---------------------------------------------------------------------------
// Черновики рассылки
// ---------------------------------------------------------------------------

export async function getBroadcast(adminId) {
  const id = Number(adminId);
  const row = await db.select().from(broadcastDrafts).where(eq(broadcastDrafts.adminId, id)).get();
  if (!row) return null;
  if (row.updatedAt < Math.floor(Date.now() / 1000) - SESSION_TTL) return null;
  return row;
}

export async function setBroadcast(adminId, state) {
  const id = Number(adminId);
  await db
    .insert(broadcastDrafts)
    .values({
      adminId: id,
      step: state.step,
      body: state.body ?? '',
      updatedAt: state.updatedAt,
    })
    .onConflictDoUpdate({
      target: broadcastDrafts.adminId,
      set: { step: state.step, body: state.body ?? '', updatedAt: state.updatedAt },
    })
    .run();
}

export async function clearBroadcast(adminId) {
  const id = Number(adminId);
  await db.delete(broadcastDrafts).where(eq(broadcastDrafts.adminId, id)).run();
}

// ---------------------------------------------------------------------------
// Рассылка
// ---------------------------------------------------------------------------

export async function broadcastRecipients(excludeChatId = '') {
  const exclude = String(excludeChatId);

  const candidates = new Map();
  for (const user of await loadUsers()) {
    candidates.set(String(user.chatId), user);
  }
  for (const record of [...(await loadRegistrations()), ...(await loadArchive())]) {
    const id = String(record.chatId ?? '');
    if (id && !candidates.has(id)) candidates.set(id, null);
  }

  const recipients = [];
  for (const [chatId, user] of candidates) {
    if (!chatId || chatId.startsWith('-')) continue;
    if (exclude && chatId === exclude) continue;
    if (user?.blocked) continue;
    recipients.push({ chatId, user });
  }

  return recipients;
}

export async function markUsersDelivery(delivery) {
  for (const [chatId, blocked] of Object.entries(delivery)) {
    const id = Number(chatId);
    const now = nowMoscow();
    const old = await db.select().from(users).where(eq(users.chatId, id)).get();

    if (old) {
      await db
        .update(users)
        .set({ blocked, blockedAt: blocked ? now : null, lastSeen: now })
        .where(eq(users.chatId, id))
        .run();
    } else {
      await db
        .insert(users)
        .values({
          chatId: id,
          telegramId: id,
          username: '',
          name: '',
          firstSeen: now,
          lastSeen: now,
          blocked,
          blockedAt: blocked ? now : null,
        })
        .run();
    }
  }
}

// ---------------------------------------------------------------------------
// Билеты на матч
// ---------------------------------------------------------------------------

export async function addTicket(fileId, fileName, fileSize) {
  await db.insert(tickets).values({
    fileId,
    fileName: fileName ?? 'ticket.pdf',
    fileSize: fileSize ?? null,
    used: false,
    usedByChatId: null,
    usedAt: null,
  }).run();
}

export async function loadTickets(onlyUnused = true) {
  const query = db.select().from(tickets).orderBy(asc(tickets.id));
  if (onlyUnused) {
    query.where(eq(tickets.used, false));
  }
  return query.all();
}

export async function countTickets(onlyUnused = true) {
  if (onlyUnused) {
    return db.$count(tickets, eq(tickets.used, false));
  }
  return db.$count(tickets);
}

export async function issueTicket(chatId) {
  const id = Number(chatId);

  // Пользователь уже получал билет — вернём существующий
  const existing = await db.select().from(tickets)
    .where(and(eq(tickets.used, true), eq(tickets.usedByChatId, id)))
    .limit(1)
    .get();
  if (existing) return existing;

  // Выдаём первый свободный билет и помечаем использованным
  const ticket = await db.select().from(tickets)
    .where(eq(tickets.used, false))
    .orderBy(asc(tickets.id))
    .limit(1)
    .get();

  if (!ticket) return null;

  const now = nowMoscow();
  await db.update(tickets)
    .set({ used: true, usedByChatId: id, usedAt: now })
    .where(eq(tickets.id, ticket.id))
    .run();

  return { ...ticket, used: true, usedByChatId: id, usedAt: now };
}

export async function ticketStats() {
  const total = await db.$count(tickets);
  const used = await db.$count(tickets, eq(tickets.used, true));
  return { total, used: used ?? 0, unused: total - (used ?? 0) };
}

export async function clearTickets() {
  await db.run('DELETE FROM tickets');
}

export async function resetTickets() {
  await db
    .update(tickets)
    .set({ used: false, usedByChatId: null, usedAt: null })
    .run();
}
