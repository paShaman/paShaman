import { db } from 'sdk';
import {
  and,
  asc,
  desc,
  eq,
  gt,
  gte,
  inArray,
  isNotNull,
  isNull,
  lte,
  ne,
} from 'sdk/db';
import {
  assignments,
  auditLog,
  broadcasts,
  campaigns,
  chatMessages,
  claims,
  deliveryInfo,
  exclusions,
  memberships,
  rateLimits,
  sessions,
  users,
  wishlistItems,
} from '../schema.js';
import { SESSION_TTL } from './config.js';
import { nowSec } from './utils.js';
import { partitionItems } from './domain/wishlist.js';

function num(value) {
  const number = Number(value);
  return Number.isFinite(number) ? number : null;
}

// ---------------------------------------------------------------------------
// Пользователи
// ---------------------------------------------------------------------------

export async function getUser(tgId) {
  const id = num(tgId);
  if (!id) return null;
  return (await db.select().from(users).where(eq(users.tgId, id)).get()) ?? null;
}

export async function rememberUser(from) {
  const id = num(from?.id);
  if (!id || id <= 0) return null;
  const username = String(from.username ?? '').trim();
  const firstName = String(from.first_name ?? '').trim();
  const lastName = String(from.last_name ?? '').trim();
  const old = await getUser(id);
  if (
    old &&
    old.username === username &&
    old.firstName === firstName &&
    old.lastName === lastName &&
    !old.blocked
  ) {
    return old;
  }
  const now = new Date();
  await db
    .insert(users)
    .values({
      tgId: id,
      username,
      firstName,
      lastName,
      lang: 'ru',
      banned: old?.banned ?? false,
      blocked: false,
      createdAt: old?.createdAt ?? now,
      updatedAt: now,
    })
    .onConflictDoUpdate({
      target: users.tgId,
      set: { username, firstName, lastName, blocked: false, updatedAt: now },
    })
    .run();
  return getUser(id);
}

export async function setUserBanned(tgId, banned) {
  const id = num(tgId);
  if (!id) return false;
  await db
    .update(users)
    .set({ banned: Boolean(banned), updatedAt: new Date() })
    .where(eq(users.tgId, id))
    .run();
  return true;
}

export async function setUserBlocked(tgId, blocked) {
  const id = num(tgId);
  if (!id) return false;
  await db
    .update(users)
    .set({ blocked: Boolean(blocked), updatedAt: new Date() })
    .where(eq(users.tgId, id))
    .run();
  return true;
}

export async function updateUserProfile(tgId, patch) {
  const id = num(tgId);
  if (!id) return null;
  await db
    .update(users)
    .set({ ...patch, updatedAt: new Date() })
    .where(eq(users.tgId, id))
    .run();
  return getUser(id);
}

export async function countUsers() {
  return db.$count(users);
}

// ---------------------------------------------------------------------------
// Кампании
// ---------------------------------------------------------------------------

export async function getCampaign(campaignId) {
  const id = num(campaignId);
  if (!id) return null;
  return (await db.select().from(campaigns).where(eq(campaigns.id, id)).get()) ?? null;
}

export async function getCampaignByCode(code) {
  return (await db.select().from(campaigns).where(eq(campaigns.code, code)).get()) ?? null;
}

export async function createCampaign({
  code,
  title,
  description,
  rules,
  budgetMin,
  budgetMax,
  currency,
  deliveryMode,
  logoFileId = '',
  revealSanta,
  organizerId,
  registrationEndsAt,
  giftDate,
}) {
  const now = new Date();
  const result = await db
    .insert(campaigns)
    .values({
      code,
      title,
      description,
      rules,
      rulesVersion: 1,
      budgetMin,
      budgetMax,
      currency,
      status: 'recruiting',
      deliveryMode,
      logoFileId,
      logoVersion: logoFileId ? 1 : 0,
      revealSanta,
      organizerId,
      registrationEndsAt,
      giftDate,
      version: 1,
      createdAt: now,
      updatedAt: now,
    })
    .run();

  const id = Number(result.lastInsertRowid);
  await db
    .insert(memberships)
    .values({
      campaignId: id,
      userId: organizerId,
      role: 'organizer',
      status: 'joined',
      createdAt: now,
      updatedAt: now,
    })
    .run();
  return getCampaign(id);
}

export async function setCampaignLogo(campaignId, fileId) {
  const campaign = await getCampaign(campaignId);
  if (!campaign) return null;
  await db
    .update(campaigns)
    .set({
      logoFileId: fileId ?? '',
      logoVersion: Number(campaign.logoVersion) + 1,
      updatedAt: new Date(),
    })
    .where(eq(campaigns.id, campaign.id))
    .run();
  return getCampaign(campaign.id);
}

export async function updateCampaign(campaignId, patch) {
  const campaign = await getCampaign(campaignId);
  if (!campaign) return null;
  await db
    .update(campaigns)
    .set({
      ...patch,
      version: Number(campaign.version) + 1,
      updatedAt: new Date(),
    })
    .where(eq(campaigns.id, campaign.id))
    .run();
  return getCampaign(campaign.id);
}

export async function bumpCampaignVersion(campaignId) {
  const campaign = await getCampaign(campaignId);
  if (!campaign) return null;
  await db
    .update(campaigns)
    .set({ version: Number(campaign.version) + 1, updatedAt: new Date() })
    .where(eq(campaigns.id, campaign.id))
    .run();
  return getCampaign(campaign.id);
}

export async function listUserCampaigns(userId) {
  const rows = await db
    .select()
    .from(memberships)
    .where(and(eq(memberships.userId, num(userId)), inArray(memberships.status, ['joined', 'rules_accepted', 'confirmed'])))
    .orderBy(desc(memberships.updatedAt))
    .all();
  if (rows.length === 0) return [];
  const list = await db
    .select()
    .from(campaigns)
    .where(inArray(campaigns.id, rows.map((row) => row.campaignId)))
    .all();
  const byId = new Map(list.map((campaign) => [campaign.id, campaign]));
  return rows
    .map((membership) => ({ membership, campaign: byId.get(membership.campaignId) }))
    .filter((item) => item.campaign);
}

export async function listOrganizedCampaigns(userId) {
  return db
    .select()
    .from(campaigns)
    .where(eq(campaigns.organizerId, num(userId)))
    .orderBy(desc(campaigns.createdAt))
    .all();
}

export async function listAllCampaigns(limit = 50) {
  return db
    .select()
    .from(campaigns)
    .orderBy(desc(campaigns.createdAt))
    .limit(limit)
    .all();
}

export async function countCampaigns(status) {
  return status
    ? db.$count(campaigns, eq(campaigns.status, status))
    : db.$count(campaigns);
}

// ---------------------------------------------------------------------------
// Участники
// ---------------------------------------------------------------------------

export async function getMembership(campaignId, userId) {
  const cid = num(campaignId);
  const uid = num(userId);
  if (!cid || !uid) return null;
  return (
    (await db
      .select()
      .from(memberships)
      .where(and(eq(memberships.campaignId, cid), eq(memberships.userId, uid)))
      .get()) ?? null
  );
}

export async function getMembershipById(membershipId) {
  const id = num(membershipId);
  if (!id) return null;
  return (await db.select().from(memberships).where(eq(memberships.id, id)).get()) ?? null;
}

export async function listMembers(campaignId) {
  return db
    .select()
    .from(memberships)
    .where(eq(memberships.campaignId, num(campaignId)))
    .orderBy(asc(memberships.id))
    .all();
}

export async function listMembersDetailed(campaignId) {
  const rows = await listMembers(campaignId);
  if (rows.length === 0) return [];
  const people = await db
    .select()
    .from(users)
    .where(inArray(users.tgId, rows.map((row) => row.userId)))
    .all();
  const byId = new Map(people.map((person) => [person.tgId, person]));
  return rows.map((member) => ({ ...member, user: byId.get(member.userId) ?? null }));
}

export async function joinCampaign(campaignId, userId) {
  const cid = num(campaignId);
  const uid = num(userId);
  const now = new Date();
  const existing = await getMembership(cid, uid);
  if (existing) {
    if (['removed', 'declined'].includes(existing.status)) {
      await db
        .update(memberships)
        .set({ status: 'joined', updatedAt: now })
        .where(eq(memberships.id, existing.id))
        .run();
      return getMembership(cid, uid);
    }
    return existing;
  }
  await db
    .insert(memberships)
    .values({
      campaignId: cid,
      userId: uid,
      role: 'participant',
      status: 'joined',
      createdAt: now,
      updatedAt: now,
    })
    .run();
  return getMembership(cid, uid);
}

export async function updateMembership(membershipId, patch) {
  const id = num(membershipId);
  if (!id) return null;
  await db
    .update(memberships)
    .set({ ...patch, updatedAt: new Date() })
    .where(eq(memberships.id, id))
    .run();
  return (await db.select().from(memberships).where(eq(memberships.id, id)).get()) ?? null;
}

export async function removeMember(campaignId, userId) {
  const member = await getMembership(campaignId, userId);
  if (!member) return null;
  return updateMembership(member.id, { status: 'removed' });
}

export async function countMembers(campaignId, status) {
  const where = status
    ? and(eq(memberships.campaignId, num(campaignId)), eq(memberships.status, status))
    : eq(memberships.campaignId, num(campaignId));
  return db.$count(memberships, where);
}

export async function listConfirmedMembers(campaignId) {
  return db
    .select()
    .from(memberships)
    .where(
      and(eq(memberships.campaignId, num(campaignId)), eq(memberships.status, 'confirmed')),
    )
    .all();
}

// ---------------------------------------------------------------------------
// Исключения
// ---------------------------------------------------------------------------

export async function listExclusions(campaignId) {
  return db
    .select()
    .from(exclusions)
    .where(eq(exclusions.campaignId, num(campaignId)))
    .orderBy(asc(exclusions.id))
    .all();
}

export async function addExclusion(campaignId, a, b) {
  const userA = Math.min(num(a), num(b));
  const userB = Math.max(num(a), num(b));
  if (!userA || !userB || userA === userB) return null;
  const existing = await db
    .select()
    .from(exclusions)
    .where(
      and(
        eq(exclusions.campaignId, num(campaignId)),
        eq(exclusions.userA, userA),
        eq(exclusions.userB, userB),
      ),
    )
    .get();
  if (existing) return existing;
  const result = await db
    .insert(exclusions)
    .values({
      campaignId: num(campaignId),
      userA,
      userB,
      createdAt: new Date(),
    })
    .run();
  return (
    (await db
      .select()
      .from(exclusions)
      .where(eq(exclusions.id, Number(result.lastInsertRowid)))
      .get()) ?? null
  );
}

export async function removeExclusion(campaignId, exclusionId) {
  await db
    .delete(exclusions)
    .where(
      and(eq(exclusions.campaignId, num(campaignId)), eq(exclusions.id, num(exclusionId))),
    )
    .run();
}

// ---------------------------------------------------------------------------
// Вишлист
// ---------------------------------------------------------------------------

export async function listWishlist(campaignId, userId) {
  return partitionItems(
    await db
      .select()
      .from(wishlistItems)
      .where(
        and(eq(wishlistItems.campaignId, num(campaignId)), eq(wishlistItems.userId, num(userId))),
      )
      .all(),
  );
}

export async function countWishlist(campaignId, userId) {
  return db.$count(
    wishlistItems,
    and(eq(wishlistItems.campaignId, num(campaignId)), eq(wishlistItems.userId, num(userId))),
  );
}

export async function getWishlistItem(itemId) {
  return (
    (await db.select().from(wishlistItems).where(eq(wishlistItems.id, num(itemId))).get()) ??
    null
  );
}

export async function createWishlistItem(campaignId, userId, value, sortOrder) {
  const now = new Date();
  const result = await db
    .insert(wishlistItems)
    .values({
      campaignId: num(campaignId),
      userId: num(userId),
      title: value.title,
      url: value.url,
      priceMin: value.priceMin,
      priceMax: value.priceMax,
      currency: value.currency,
      comment: value.comment,
      photoFileId: null,
      photoVersion: 0,
      sortOrder,
      createdAt: now,
      updatedAt: now,
    })
    .run();
  return getWishlistItem(result.lastInsertRowid);
}

export async function updateWishlistItem(itemId, value) {
  await db
    .update(wishlistItems)
    .set({ ...value, updatedAt: new Date() })
    .where(eq(wishlistItems.id, num(itemId)))
    .run();
  return getWishlistItem(itemId);
}

export async function deleteWishlistItem(itemId) {
  await db.delete(wishlistItems).where(eq(wishlistItems.id, num(itemId))).run();
  await db.delete(claims).where(eq(claims.itemId, num(itemId))).run();
}

export async function setWishlistPhoto(itemId, fileId) {
  const item = await getWishlistItem(itemId);
  if (!item) return null;
  return updateWishlistItem(itemId, {
    photoFileId: fileId,
    photoVersion: Number(item.photoVersion) + 1,
  });
}

export async function clearWishlistPhoto(itemId) {
  const item = await getWishlistItem(itemId);
  if (!item) return null;
  return updateWishlistItem(itemId, {
    photoFileId: null,
    photoVersion: Number(item.photoVersion) + 1,
  });
}

export async function setWishlistOrder(items) {
  for (const item of items) {
    await db
      .update(wishlistItems)
      .set({ sortOrder: item.sortOrder, updatedAt: new Date() })
      .where(eq(wishlistItems.id, item.id))
      .run();
  }
}

export async function listWishlistForCampaign(campaignId) {
  return db
    .select()
    .from(wishlistItems)
    .where(eq(wishlistItems.campaignId, num(campaignId)))
    .all();
}

// ---------------------------------------------------------------------------
// Claims
// ---------------------------------------------------------------------------

export async function getClaim(itemId) {
  return (
    (await db.select().from(claims).where(eq(claims.itemId, num(itemId))).get()) ?? null
  );
}

export async function listClaims(assignmentId) {
  return db
    .select()
    .from(claims)
    .where(eq(claims.assignmentId, num(assignmentId)))
    .all();
}

export async function setClaim(itemId, campaignId, santaId, assignmentId, claimed) {
  if (!claimed) {
    await db.delete(claims).where(eq(claims.itemId, num(itemId))).run();
    return false;
  }
  await db
    .insert(claims)
    .values({
      itemId: num(itemId),
      campaignId: num(campaignId),
      santaId: num(santaId),
      assignmentId: num(assignmentId),
      claimedAt: new Date(),
    })
    .onConflictDoUpdate({
      target: claims.itemId,
      set: { claimedAt: new Date(), assignmentId: num(assignmentId) },
    })
    .run();
  return true;
}

// ---------------------------------------------------------------------------
// Пары
// ---------------------------------------------------------------------------

export async function listDrafts(campaignId) {
  return db
    .select()
    .from(assignments)
    .where(
      and(eq(assignments.campaignId, num(campaignId)), eq(assignments.status, 'draft')),
    )
    .orderBy(asc(assignments.giverId))
    .all();
}

export async function replaceDrafts(campaignId, pairs) {
  await db
    .delete(assignments)
    .where(
      and(eq(assignments.campaignId, num(campaignId)), eq(assignments.status, 'draft')),
    )
    .run();
  if (pairs.length === 0) return [];
  const now = new Date();
  await db
    .insert(assignments)
    .values(
      pairs.map((pair) => ({
        campaignId: num(campaignId),
        giverId: num(pair.giverId),
        receiverId: num(pair.receiverId),
        status: 'draft',
        giftStatus: 'pending',
        version: 1,
        createdAt: now,
        updatedAt: now,
      })),
    )
    .run();
  return listDrafts(campaignId);
}

export async function updateDraftPair(campaignId, giverId, receiverId) {
  await db
    .update(assignments)
    .set({ receiverId: num(receiverId), version: 1, updatedAt: new Date() })
    .where(
      and(eq(assignments.campaignId, num(campaignId)), eq(assignments.giverId, num(giverId))),
    )
    .run();
}

export async function confirmDrafts(campaignId) {
  await db
    .update(assignments)
    .set({ status: 'confirmed', updatedAt: new Date() })
    .where(
      and(eq(assignments.campaignId, num(campaignId)), eq(assignments.status, 'draft')),
    )
    .run();
}

export async function getAssignmentForGiver(campaignId, userId) {
  return (
    (await db
      .select()
      .from(assignments)
      .where(
        and(
          eq(assignments.campaignId, num(campaignId)),
          eq(assignments.giverId, num(userId)),
          eq(assignments.status, 'confirmed'),
        ),
      )
      .get()) ?? null
  );
}

export async function getAssignmentForReceiver(campaignId, userId) {
  return (
    (await db
      .select()
      .from(assignments)
      .where(
        and(
          eq(assignments.campaignId, num(campaignId)),
          eq(assignments.receiverId, num(userId)),
          eq(assignments.status, 'confirmed'),
        ),
      )
      .get()) ?? null
  );
}

export async function getAssignmentById(assignmentId) {
  return (
    (await db.select().from(assignments).where(eq(assignments.id, num(assignmentId))).get()) ??
    null
  );
}

export async function listConfirmedAssignments(campaignId) {
  return db
    .select()
    .from(assignments)
    .where(
      and(eq(assignments.campaignId, num(campaignId)), eq(assignments.status, 'confirmed')),
    )
    .all();
}

export async function setAssignmentGift(assignmentId, patch) {
  await db
    .update(assignments)
    .set({ ...patch, updatedAt: new Date() })
    .where(eq(assignments.id, num(assignmentId)))
    .run();
  return getAssignmentById(assignmentId);
}

export async function blockAssignmentChat(assignmentId, blocked) {
  await db
    .update(assignments)
    .set({
      chatBlocked: Boolean(blocked),
      chatBlockedAt: blocked ? new Date() : null,
      updatedAt: new Date(),
    })
    .where(eq(assignments.id, num(assignmentId)))
    .run();
}

export async function revealAssignments(campaignId) {
  await db
    .update(assignments)
    .set({ revealedAt: new Date(), updatedAt: new Date() })
    .where(
      and(eq(assignments.campaignId, num(campaignId)), eq(assignments.status, 'confirmed')),
    )
    .run();
}

// ---------------------------------------------------------------------------
// Доставка
// ---------------------------------------------------------------------------

export async function getDelivery(campaignId, userId) {
  return (
    (await db
      .select()
      .from(deliveryInfo)
      .where(
        and(eq(deliveryInfo.campaignId, num(campaignId)), eq(deliveryInfo.userId, num(userId))),
      )
      .get()) ?? null
  );
}

export async function saveDelivery(campaignId, userId, value) {
  const existing = await getDelivery(campaignId, userId);
  const now = new Date();
  if (existing) {
    await db
      .update(deliveryInfo)
      .set({ ...value, updatedAt: now })
      .where(eq(deliveryInfo.id, existing.id))
      .run();
  } else {
    await db
      .insert(deliveryInfo)
      .values({
        campaignId: num(campaignId),
        userId: num(userId),
        ...value,
        updatedAt: now,
      })
      .run();
  }
  return getDelivery(campaignId, userId);
}

export async function purgeDelivery(campaignId) {
  await db.delete(deliveryInfo).where(eq(deliveryInfo.campaignId, num(campaignId))).run();
}

export async function purgeClaims(campaignId) {
  await db.delete(claims).where(eq(claims.campaignId, num(campaignId))).run();
}

// ---------------------------------------------------------------------------
// Чат
// ---------------------------------------------------------------------------

export async function addChatMessage({
  campaignId,
  assignmentId,
  senderRole,
  senderUserId,
  clientMsgId,
  text,
}) {
  const existing = await db
    .select()
    .from(chatMessages)
    .where(
      and(
        eq(chatMessages.assignmentId, num(assignmentId)),
        eq(chatMessages.clientMsgId, String(clientMsgId)),
      ),
    )
    .get();
  if (existing) return existing;
  const result = await db
    .insert(chatMessages)
    .values({
      campaignId: num(campaignId),
      assignmentId: num(assignmentId),
      senderRole,
      senderUserId: num(senderUserId),
      clientMsgId: String(clientMsgId),
      text,
      createdAt: new Date(),
    })
    .run();
  return (
    (await db
      .select()
      .from(chatMessages)
      .where(eq(chatMessages.id, Number(result.lastInsertRowid)))
      .get()) ?? null
  );
}

export async function listChatMessages(assignmentId, afterId = 0) {
  const where = afterId
    ? and(eq(chatMessages.assignmentId, num(assignmentId)), gt(chatMessages.id, num(afterId)))
    : eq(chatMessages.assignmentId, num(assignmentId));
  return db
    .select()
    .from(chatMessages)
    .where(where)
    .orderBy(asc(chatMessages.id))
    .limit(200)
    .all();
}

export async function countRecentMessages(assignmentId, senderUserId, sinceSec) {
  return db.$count(
    chatMessages,
    and(
      eq(chatMessages.assignmentId, num(assignmentId)),
      eq(chatMessages.senderUserId, num(senderUserId)),
      gte(chatMessages.createdAt, new Date(Number(sinceSec) * 1000)),
    ),
  );
}

export async function markMessagesRead(assignmentId, userId, upToId) {
  const where = [
    eq(chatMessages.assignmentId, num(assignmentId)),
    ne(chatMessages.senderUserId, num(userId)),
    isNull(chatMessages.readAt),
  ];
  if (upToId) where.push(lte(chatMessages.id, num(upToId)));
  await db.update(chatMessages).set({ readAt: new Date() }).where(and(...where)).run();
}

export async function getChatMessage(messageId) {
  return (
    (await db.select().from(chatMessages).where(eq(chatMessages.id, num(messageId))).get()) ??
    null
  );
}

export async function reportChatMessage(messageId, userId, reason) {
  await db
    .update(chatMessages)
    .set({
      reportedAt: new Date(),
      reportedBy: num(userId),
      reportReason: String(reason ?? '').slice(0, 500),
    })
    .where(eq(chatMessages.id, num(messageId)))
    .run();
  return getChatMessage(messageId);
}

export async function hideChatMessage(messageId, actorId) {
  await db
    .update(chatMessages)
    .set({ hiddenAt: new Date(), hiddenBy: num(actorId) })
    .where(eq(chatMessages.id, num(messageId)))
    .run();
}

export async function listReports(campaignId = null, limit = 50) {
  const where = campaignId
    ? and(eq(chatMessages.campaignId, num(campaignId)), isNotNull(chatMessages.reportedAt))
    : isNotNull(chatMessages.reportedAt);
  return db
    .select()
    .from(chatMessages)
    .where(where)
    .orderBy(desc(chatMessages.reportedAt))
    .limit(limit)
    .all();
}

// ---------------------------------------------------------------------------
// Сессии
// ---------------------------------------------------------------------------

export async function getSession(userId) {
  const row = await db
    .select()
    .from(sessions)
    .where(eq(sessions.userId, num(userId)))
    .get();
  if (!row) return null;
  if (Number(row.updatedAt) < nowSec() - SESSION_TTL) {
    await clearSession(userId);
    return null;
  }
  return row;
}

export async function setSession(userId, state) {
  const updatedAt = nowSec();
  const data = { ...(state.data ?? {}), step: state.step, updatedAt };
  await db
    .insert(sessions)
    .values({ userId: num(userId), step: state.step, data, updatedAt })
    .onConflictDoUpdate({
      target: sessions.userId,
      set: { step: state.step, data, updatedAt },
    })
    .run();
  return { step: state.step, data, updatedAt };
}

export async function clearSession(userId) {
  await db.delete(sessions).where(eq(sessions.userId, num(userId))).run();
}

// ---------------------------------------------------------------------------
// Rate limits
// ---------------------------------------------------------------------------

export async function hitRate(key, limit, windowSec) {
  const now = nowSec();
  const row = await db
    .select()
    .from(rateLimits)
    .where(eq(rateLimits.key, String(key)))
    .get();
  if (!row || now - Number(row.windowStart) >= Number(windowSec)) {
    await db
      .insert(rateLimits)
      .values({ key: String(key), windowStart: now, count: 1 })
      .onConflictDoUpdate({
        target: rateLimits.key,
        set: { windowStart: now, count: 1 },
      })
      .run();
    return { ok: true, retryAfter: 0 };
  }
  if (Number(row.count) >= Number(limit)) {
    return {
      ok: false,
      retryAfter: Number(windowSec) - (now - Number(row.windowStart)),
    };
  }
  await db
    .update(rateLimits)
    .set({ count: Number(row.count) + 1 })
    .where(eq(rateLimits.key, String(key)))
    .run();
  return { ok: true, retryAfter: 0 };
}

// ---------------------------------------------------------------------------
// Рассылки и аудит
// ---------------------------------------------------------------------------

export async function logBroadcast(value) {
  const result = await db
    .insert(broadcasts)
    .values({ ...value, createdAt: new Date() })
    .run();
  return Number(result.lastInsertRowid);
}

export async function logAudit(campaignId, actorId, action, payload = {}) {
  await db
    .insert(auditLog)
    .values({
      campaignId: campaignId ? num(campaignId) : null,
      actorId: num(actorId),
      action,
      payload,
      createdAt: new Date(),
    })
    .run();
}

// ---------------------------------------------------------------------------
// Статистика
// ---------------------------------------------------------------------------

export async function campaignStats(campaignId) {
  const cid = num(campaignId);
  const total = await db.$count(memberships, eq(memberships.campaignId, cid));
  const confirmed = await db.$count(
    memberships,
    and(eq(memberships.campaignId, cid), eq(memberships.status, 'confirmed')),
  );
  const wishlist = await db.$count(wishlistItems, eq(wishlistItems.campaignId, cid));
  const deliveries = await db.$count(deliveryInfo, eq(deliveryInfo.campaignId, cid));
  const sent = await db.$count(
    assignments,
    and(
      eq(assignments.campaignId, cid),
      eq(assignments.status, 'confirmed'),
      eq(assignments.giftStatus, 'sent'),
    ),
  );
  const received = await db.$count(
    assignments,
    and(
      eq(assignments.campaignId, cid),
      eq(assignments.status, 'confirmed'),
      eq(assignments.giftStatus, 'received'),
    ),
  );
  const reports = await db.$count(
    chatMessages,
    and(eq(chatMessages.campaignId, cid), isNotNull(chatMessages.reportedAt)),
  );
  return { total, confirmed, wishlist, deliveries, sent, received, reports };
}

export async function globalStats() {
  const usersCount = await db.$count(users);
  const campaignsCount = await db.$count(campaigns);
  const reports = await db.$count(chatMessages, isNotNull(chatMessages.reportedAt));
  return { users: usersCount, campaigns: campaignsCount, reports };
}

export async function totalWishlistCount(campaignId) {
  return db.$count(wishlistItems, eq(wishlistItems.campaignId, num(campaignId)));
}
