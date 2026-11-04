import { db } from 'sdk';
import { EndpointError } from 'sdk';
import { users, records, sessions } from '../schema.js';
import { ADMIN_ID } from '../lib/config.js';

export default async function (input, ctx) {
  const user = ctx.initData?.user;
  if (!user || String(user.id) !== String(ADMIN_ID)) {
    throw new EndpointError('Forbidden', { code: 'FORBIDDEN' });
  }

  const { users: usersData, records: recordsData, sessions: sessionsData } = input ?? {};

  let importedUsers = 0;
  let importedRecords = 0;
  let importedSessions = 0;

  if (Array.isArray(usersData)) {
    for (const u of usersData) {
      await db
        .insert(users)
        .values({
          id: Number(u.id),
          name: String(u.name ?? ''),
          username: String(u.username ?? ''),
          role: String(u.role ?? 'user'),
          annual_days: u.annual_days ? Number(u.annual_days) : null,
          active: u.active !== false,
          created_at: String(u.created_at ?? new Date().toISOString()),
        })
        .onConflictDoUpdate({
          target: users.id,
          set: {
            name: String(u.name ?? ''),
            username: String(u.username ?? ''),
            role: String(u.role ?? 'user'),
            annual_days: u.annual_days ? Number(u.annual_days) : null,
            active: u.active !== false,
          },
        })
        .run();
      importedUsers++;
    }
  }

  if (Array.isArray(recordsData)) {
    for (const r of recordsData) {
      await db
        .insert(records)
        .values({
          id: r.id ? Number(r.id) : undefined,
          user_id: Number(r.user_id),
          type: String(r.type),
          date_from: String(r.date_from),
          date_to: String(r.date_to),
          days: Number(r.days ?? 0),
          comment: String(r.comment ?? ''),
          finished_at: r.finished_at ? String(r.finished_at) : null,
          created_at: String(r.created_at ?? new Date().toISOString()),
          updated_at: String(r.updated_at ?? new Date().toISOString()),
        })
        .run();
      importedRecords++;
    }
  }

  if (Array.isArray(sessionsData)) {
    for (const s of sessionsData) {
      await db
        .insert(sessions)
        .values({
          user_id: Number(s.user_id),
          step: String(s.step),
          data: s.data ?? {},
          ts: Number(s.ts ?? Math.floor(Date.now() / 1000)),
        })
        .onConflictDoUpdate({
          target: sessions.user_id,
          set: {
            step: String(s.step),
            data: s.data ?? {},
            ts: Number(s.ts ?? Math.floor(Date.now() / 1000)),
          },
        })
        .run();
      importedSessions++;
    }
  }

  return { users: importedUsers, records: importedRecords, sessions: importedSessions };
}
