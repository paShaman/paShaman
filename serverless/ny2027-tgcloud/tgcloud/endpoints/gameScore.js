import { db, EndpointError } from 'sdk';
import { asc, desc, eq, gt } from 'sdk/db';
import { gameScores } from '../schema.js';
import { nowMoscow } from '../lib/utils.js';

const TOP_LIMIT = 10;
const MAX_SCORE = 1000000;

function displayName(user) {
  const full = `${String(user.first_name ?? '')} ${String(user.last_name ?? '')}`.trim();
  return full || String(user.username ?? '').trim() || 'Игрок';
}

async function topRows(userId) {
  const rows = await db
    .select({
      userId: gameScores.userId,
      name: gameScores.name,
      username: gameScores.username,
      best: gameScores.best,
      plays: gameScores.plays,
    })
    .from(gameScores)
    .orderBy(desc(gameScores.best), asc(gameScores.updatedAt))
    .limit(TOP_LIMIT)
    .all();

  let lastBest = null;
  let lastRank = 0;

  return rows.map((row, index) => {
    if (row.best !== lastBest) {
      lastRank = index + 1;
      lastBest = row.best;
    }

    return {
      rank: lastRank,
      name: row.name || (row.username ? `@${row.username}` : 'Игрок'),
      best: row.best,
      plays: row.plays,
      isMe: row.userId === userId,
    };
  });
}

export default async function (input, ctx) {
  const user = ctx.initData?.user;
  const userId = Number(user?.id ?? 0);

  if (!Number.isFinite(userId) || userId <= 0) {
    throw new EndpointError('Не удалось определить пользователя', { code: 'NO_USER' });
  }

  if (input && input.score !== undefined) {
    const raw = Number(input.score);

    if (!Number.isFinite(raw) || raw < 0) {
      throw new EndpointError('Некорректный счёт', { code: 'BAD_SCORE' });
    }

    const score = Math.floor(raw);

    if (score > MAX_SCORE) {
      throw new EndpointError('Слишком большой счёт', { code: 'SCORE_TOO_BIG' });
    }

    const name = displayName(user);
    const username = String(user.username ?? '').trim();
    const now = nowMoscow();
    const existing = await db
      .select()
      .from(gameScores)
      .where(eq(gameScores.userId, userId))
      .get();

    if (existing) {
      await db
        .update(gameScores)
        .set({
          name,
          username,
          best: Math.max(existing.best, score),
          plays: existing.plays + 1,
          updatedAt: now,
        })
        .where(eq(gameScores.userId, userId))
        .run();
    } else {
      await db
        .insert(gameScores)
        .values({
          userId,
          name,
          username,
          best: score,
          plays: 1,
          updatedAt: now,
        })
        .run();
    }
  }

  const top = await topRows(userId);
  const me = await db
    .select()
    .from(gameScores)
    .where(eq(gameScores.userId, userId))
    .get();
  const total = await db.$count(gameScores);

  let rank = null;
  if (me) {
    rank = (await db.$count(gameScores, gt(gameScores.best, me.best))) + 1;
  }

  return {
    top,
    me: me ? { rank, best: me.best, plays: me.plays } : null,
    total,
  };
}
