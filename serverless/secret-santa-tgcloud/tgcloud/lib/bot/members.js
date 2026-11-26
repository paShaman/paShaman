import { displayName, esc, pluralRu } from '../utils.js';
import {
  addExclusion,
  getMembership,
  listExclusions,
  listMembersDetailed,
  logAudit,
  removeExclusion,
  removeMember,
  updateMembership,
} from '../store.js';
import {
  exclusionsKeyboard,
  exclusionPickOneKeyboard,
  exclusionPickTwoKeyboard,
  memberProfileKeyboard,
  membersKeyboard,
  removeMemberConfirmKeyboard,
  rulesKeyboard,
} from '../keyboards.js';
import { exclusionsText, membersText, memberRemovedText } from '../texts.js';
import { render, sendHtml } from '../telegram.js';
import {
  campaignNames,
  isSuperAdmin,
  loadCampaignFor,
  loadOrganizerCampaign,
  namesMap,
} from './common.js';

const PAGE_SIZE = 10;
const PICK_SIZE = 8;

export async function openMembers(chatId, userId, campaignId, page = 1, messageId = null) {
  const loaded = await loadCampaignFor(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  const members = await listMembersDetailed(campaignId);
  const result = membersText(loaded.campaign, members, Number(page) || 1, PAGE_SIZE);
  const isOrganizer = loaded.campaign.organizerId === Number(userId) || isSuperAdmin(userId);
  await render(
    chatId,
    messageId,
    result.text,
    membersKeyboard(campaignId, result.page, result.totalPages, result.slice, isOrganizer, PAGE_SIZE),
  );
}

export async function viewMember(chatId, userId, campaignId, memberId, messageId = null) {
  const loaded = await loadCampaignFor(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  const member = await getMembership(campaignId, memberId);
  if (!member) {
    await sendHtml(chatId, 'Участник не найден.');
    return;
  }
  const members = await listMembersDetailed(campaignId);
  const person = members.find((item) => Number(item.userId) === Number(memberId));
  const lines = [
    `👤 <b>${esc(displayName(person?.user))}</b>`,
    `Статус: ${member.status}`,
    `Роль: ${member.role === 'organizer' ? 'организатор' : 'участник'}`,
    member.deliveryMethod ? `Формат: ${member.deliveryMethod}` : '',
    '',
    'Это профиль участника. Переписка и вишлист не раскрываются организатору.',
  ].filter(Boolean);
  await sendHtml(chatId, lines.join('\n'), memberProfileKeyboard(campaignId));
}

export async function askRemove(chatId, userId, campaignId, memberId, messageId = null) {
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  if (['locked', 'drawn', 'gifting', 'completed', 'cancelled'].includes(loaded.campaign.status)) {
    await sendHtml(chatId, 'После закрытия набора удалять участников нельзя.');
    return;
  }
  const member = await getMembership(campaignId, memberId);
  if (!member || member.role === 'organizer') {
    await sendHtml(chatId, 'Этого участника нельзя удалить.');
    return;
  }
  const members = await listMembersDetailed(campaignId);
  const person = members.find((item) => Number(item.userId) === Number(memberId));
  await render(
    chatId,
    messageId,
    `Удалить ${esc(displayName(person?.user))} из кампании?\n\nУчастник получит уведомление и больше не сможет участвовать в жеребьёвке.`,
    removeMemberConfirmKeyboard(campaignId, memberId, loaded.campaign.version),
  );
}

export async function removeParticipant(chatId, userId, campaignId, memberId, token) {
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  if (['locked', 'drawn', 'gifting', 'completed', 'cancelled'].includes(loaded.campaign.status)) {
    await sendHtml(chatId, 'После закрытия набора удалять участников нельзя.');
    return;
  }
  if (Number(token) !== Number(loaded.campaign.version)) {
    await sendHtml(chatId, 'Данные кампании изменились. Откройте участников заново.');
    return;
  }
  const member = await getMembership(campaignId, memberId);
  if (!member || member.role === 'organizer') {
    await sendHtml(chatId, 'Этого участника нельзя удалить.');
    return;
  }
  const members = await listMembersDetailed(campaignId);
  const person = members.find((item) => Number(item.userId) === Number(memberId));
  await removeMember(campaignId, memberId);
  await logAudit(campaignId, userId, 'member.remove', { userId: Number(memberId) });
  await sendHtml(memberId, `🚫 Вас удалили из кампании «${esc(loaded.campaign.title)}».`);
  await sendHtml(chatId, memberRemovedText(loaded.campaign, person?.user));
  await openMembers(chatId, userId, campaignId, 1);
}

export async function openExclusions(chatId, userId, campaignId, messageId = null) {
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  if (!['recruiting', 'locked'].includes(loaded.campaign.status)) {
    await sendHtml(chatId, 'Исключения доступны только до жеребьёвки.');
    return;
  }
  const rows = await listExclusions(campaignId);
  const { names } = await campaignNames(campaignId);
  await render(chatId, messageId, exclusionsText(loaded.campaign, rows, names), exclusionsKeyboard(campaignId, rows));
}

export async function pickFirst(chatId, userId, campaignId, page = 1) {
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  if (loaded.campaign.status !== 'recruiting' && loaded.campaign.status !== 'locked') {
    await sendHtml(chatId, 'Исключения можно менять только до жеребьёвки.');
    return;
  }
  const members = (await listMembersDetailed(campaignId)).filter(
    (member) => !['removed', 'declined'].includes(member.status),
  );
  const totalPages = Math.max(1, Math.ceil(members.length / PICK_SIZE));
  const safePage = Math.min(Math.max(1, Number(page) || 1), totalPages);
  const slice = members.slice((safePage - 1) * PICK_SIZE, safePage * PICK_SIZE);
  await sendHtml(
    chatId,
    'Кого выбрать первым в паре исключения?',
    exclusionPickOneKeyboard(campaignId, slice, safePage, totalPages),
  );
}

export async function pickSecond(chatId, userId, campaignId, firstId, page = 1) {
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  if (!['recruiting', 'locked'].includes(loaded.campaign.status)) {
    await sendHtml(chatId, 'Исключения можно менять только до жеребьёвки.');
    return;
  }
  const members = (await listMembersDetailed(campaignId)).filter(
    (member) =>
      !['removed', 'declined'].includes(member.status) &&
      Number(member.userId) !== Number(firstId),
  );
  const totalPages = Math.max(1, Math.ceil(members.length / PICK_SIZE));
  const safePage = Math.min(Math.max(1, Number(page) || 1), totalPages);
  const slice = members.slice((safePage - 1) * PICK_SIZE, safePage * PICK_SIZE);
  await sendHtml(
    chatId,
    'С кем нельзя дарить друг другу?',
    exclusionPickTwoKeyboard(campaignId, slice, firstId, safePage, totalPages),
  );
}

export async function addPair(chatId, userId, campaignId, firstId, secondId) {
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  if (['drawn', 'gifting', 'completed', 'cancelled'].includes(loaded.campaign.status)) {
    await sendHtml(chatId, 'После жеребьёвки исключения менять нельзя.');
    return;
  }
  if (Number(firstId) === Number(secondId)) {
    await sendHtml(chatId, 'Нельзя исключить участника из пары с самим собой.');
    return;
  }
  const memberA = await getMembership(campaignId, firstId);
  const memberB = await getMembership(campaignId, secondId);
  if (
    !memberA ||
    !memberB ||
    ['removed', 'declined'].includes(memberA.status) ||
    ['removed', 'declined'].includes(memberB.status)
  ) {
    await sendHtml(chatId, 'Оба участника должны быть в кампании.');
    return;
  }
  await addExclusion(campaignId, firstId, secondId);
  await logAudit(campaignId, userId, 'exclusion.add', {
    userA: Math.min(Number(firstId), Number(secondId)),
    userB: Math.max(Number(firstId), Number(secondId)),
  });
  await openExclusions(chatId, userId, campaignId);
}

export async function removePair(chatId, userId, campaignId, exclusionId) {
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  if (!['recruiting', 'locked'].includes(loaded.campaign.status)) {
    await sendHtml(chatId, 'После жеребьёвки исключения менять нельзя.');
    return;
  }
  await removeExclusion(campaignId, exclusionId);
  await logAudit(campaignId, userId, 'exclusion.remove', { exclusionId: Number(exclusionId) });
  await openExclusions(chatId, userId, campaignId);
}

export function memberStatusLabel(status) {
  return (
    {
      confirmed: 'подтверждён',
      rules_accepted: 'принял правила',
      joined: 'вступил',
      removed: 'удалён',
      declined: 'отказался',
    }[status] ?? status
  );
}

export function participantsWord(count) {
  return `${count} ${pluralRu(count, 'участник', 'участника', 'участников')}`;
}
