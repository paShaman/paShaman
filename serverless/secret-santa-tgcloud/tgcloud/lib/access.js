import { EndpointError } from 'sdk';
import {
  getAssignmentForGiver,
  getAssignmentForReceiver,
  getCampaign,
  getMembership,
  getUser,
  rememberUser,
} from './store.js';
import { isSuperAdmin } from './bot/common.js';

export function fail(code, message, params = {}) {
  throw new EndpointError(message, { code, ...params });
}

export function requiredId(value) {
  const id = Number(value);
  if (!Number.isSafeInteger(id) || id <= 0) fail('VALIDATION', 'Некорректный идентификатор.');
  return id;
}

export async function requireUser(ctx) {
  const tgUser = ctx?.initData?.user;
  if (!tgUser?.id) fail('NO_USER', 'Не удалось определить пользователя Telegram.');
  const user = (await getUser(tgUser.id)) ?? (await rememberUser(tgUser));
  if (!user) fail('NO_USER', 'Пользователь не найден.');
  if (user.banned) fail('BANNED', 'Доступ к боту ограничен администратором.');
  return { tgId: Number(tgUser.id), user, tgUser };
}

export async function requireCampaign(ctx, campaignId) {
  const auth = await requireUser(ctx);
  const campaign = await getCampaign(requiredId(campaignId));
  if (!campaign) fail('NOT_FOUND', 'Кампания не найдена.');
  return { ...auth, campaign };
}

export async function requireMember(ctx, campaignId) {
  const auth = await requireCampaign(ctx, campaignId);
  const member = await getMembership(auth.campaign.id, auth.tgId);
  if (!member || ['removed', 'declined'].includes(member.status)) {
    fail('NOT_MEMBER', 'Вы не участвуете в этой кампании.');
  }
  if (['completed', 'cancelled'].includes(auth.campaign.status) && member.status === 'removed') {
    fail('NOT_MEMBER', 'Вы не участвуете в этой кампании.');
  }
  return { ...auth, member };
}

export async function requireConfirmedMember(ctx, campaignId) {
  const auth = await requireMember(ctx, campaignId);
  if (auth.member.status !== 'confirmed') {
    fail('RULES_NOT_ACCEPTED', 'Сначала подтвердите участие.');
  }
  return auth;
}

export async function requireOrganizer(ctx, campaignId) {
  const auth = await requireCampaign(ctx, campaignId);
  if (auth.campaign.organizerId !== auth.tgId && !isSuperAdmin(auth.tgId)) {
    fail('NOT_ORGANIZER', 'Действие доступно организатору кампании.');
  }
  const member = await getMembership(auth.campaign.id, auth.tgId);
  return { ...auth, member };
}

export async function requireSanta(ctx, campaignId) {
  const auth = await requireMember(ctx, campaignId);
  if (auth.campaign.status === 'cancelled') fail('CAMPAIGN_CANCELLED', 'Кампания отменена.');
  if (!['drawn', 'gifting', 'completed'].includes(auth.campaign.status)) {
    fail('NOT_DRAWN', 'Пары ещё не собраны.');
  }
  const assignment = await getAssignmentForGiver(auth.campaign.id, auth.tgId);
  if (!assignment) fail('NOT_SANTA', 'У вас нет пары в этой кампании.');
  const receiver = await getUser(assignment.receiverId);
  return { ...auth, assignment, receiver };
}

export async function requireRecipient(ctx, campaignId) {
  const auth = await requireMember(ctx, campaignId);
  if (auth.campaign.status === 'cancelled') fail('CAMPAIGN_CANCELLED', 'Кампания отменена.');
  if (!['drawn', 'gifting', 'completed'].includes(auth.campaign.status)) {
    fail('NOT_DRAWN', 'Пары ещё не собраны.');
  }
  const assignment = await getAssignmentForReceiver(auth.campaign.id, auth.tgId);
  if (!assignment) fail('NOT_RECIPIENT', 'У вас нет пары в этой кампании.');
  const santa = await getUser(assignment.giverId);
  return { ...auth, assignment, santa };
}

export async function requirePair(ctx, campaignId, role) {
  const auth = await requireMember(ctx, campaignId);
  if (auth.campaign.status === 'cancelled') fail('CAMPAIGN_CANCELLED', 'Кампания отменена.');
  const wanted = role === 'santa' || role === 'recipient' ? role : null;
  if (wanted !== 'recipient') {
    const asSanta = await getAssignmentForGiver(auth.campaign.id, auth.tgId);
    if (asSanta) {
      return { ...auth, assignment: asSanta, role: 'santa', otherUserId: asSanta.receiverId };
    }
  }
  if (wanted !== 'santa') {
    const asRecipient = await getAssignmentForReceiver(auth.campaign.id, auth.tgId);
    if (asRecipient) {
      return {
        ...auth,
        assignment: asRecipient,
        role: 'recipient',
        otherUserId: asRecipient.giverId,
      };
    }
  }
  if (wanted === 'santa') fail('NOT_SANTA', 'Вам не назначен получатель.');
  if (wanted === 'recipient') fail('NOT_RECIPIENT', 'Вам не назначен Санта.');
  fail('NO_ASSIGNMENT', 'Для этой кампании у вас нет пары.');
}

export async function pairRoles(campaignId, userId) {
  const [santa, recipient] = await Promise.all([
    getAssignmentForGiver(campaignId, userId),
    getAssignmentForReceiver(campaignId, userId),
  ]);
  return { santa, recipient };
}
