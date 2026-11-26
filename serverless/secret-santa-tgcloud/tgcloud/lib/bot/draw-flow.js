import { buildMatching, swapReceiver } from '../domain/draw.js';
import { MatchingError } from '../domain/matching-errors.js';
import {
  bumpCampaignVersion,
  confirmDrafts,
  getCampaign,
  listConfirmedMembers,
  listDrafts,
  listExclusions,
  logAudit,
  replaceDrafts,
  updateCampaign,
} from '../store.js';
import { drawKeyboard } from '../keyboards.js';
import { assignmentIntro, drawText } from '../texts.js';
import { render, sendHtml, sendMiniApp } from '../telegram.js';
import { APP_URL } from '../config.js';
import { appUrlFromConfig, campaignNames, loadOrganizerCampaign } from './common.js';

function matchingErrorText(error) {
  if (error instanceof MatchingError && error.code === 'NO_PERFECT_MATCHING') {
    return 'Не удалось собрать пары: кому-то не хватает вариантов из-за исключений. Уберите часть исключений или добавьте участников.';
  }
  if (error instanceof MatchingError && error.code === 'TOO_FEW_PARTICIPANTS') {
    return 'Для жеребьёвки нужно минимум три подтверждённых участника.';
  }
  return 'Не удалось собрать пары. Проверьте участников и исключения и попробуйте снова.';
}

async function buildDrafts(campaignId) {
  const confirmed = await listConfirmedMembers(campaignId);
  const participants = confirmed.map((member) => member.userId);
  const rows = await listExclusions(campaignId);
  const exclusions = rows.map((row) => [row.userA, row.userB]);
  const pairs = buildMatching({ participants, exclusions });
  await replaceDrafts(campaignId, pairs);
  await bumpCampaignVersion(campaignId);
  return pairs;
}

export async function openDraw(chatId, userId, campaignId, page = 1) {
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  if (loaded.campaign.status !== 'locked') {
    await sendHtml(
      chatId,
      loaded.campaign.status === 'drawn'
        ? 'Пары уже подтверждены. Пересобрать их после подтверждения нельзя.'
        : 'Сначала закройте набор участников.',
    );
    return;
  }

  let drafts = await listDrafts(campaignId);
  if (drafts.length === 0) {
    try {
      await buildDrafts(campaignId);
      drafts = await listDrafts(campaignId);
      await logAudit(campaignId, userId, 'draw.build', { pairs: drafts.length });
    } catch (error) {
      if (error instanceof MatchingError) {
        await sendHtml(chatId, matchingErrorText(error));
        return;
      }
      throw error;
    }
  }

  const { names } = await campaignNames(campaignId);
  const fresh = await getCampaign(campaignId);
  const result = drawText(drafts, names, loaded.campaign.organizerId, Number(page) || 1);
  await render(
    chatId,
    null,
    result.text,
    drawKeyboard(
      campaignId,
      drafts,
      names,
      loaded.campaign.organizerId,
      result.page,
      result.totalPages,
      fresh?.version ?? loaded.campaign.version,
    ),
  );
}

export async function rebuildAll(chatId, userId, campaignId) {
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  if (loaded.campaign.status !== 'locked') {
    await sendHtml(chatId, 'Пересобрать пары можно только до подтверждения жеребьёвки.');
    return;
  }
  try {
    const pairs = await buildDrafts(campaignId);
    await logAudit(campaignId, userId, 'draw.rebuild', { pairs: pairs.length });
    await openDraw(chatId, userId, campaignId, 1);
  } catch (error) {
    if (error instanceof MatchingError) {
      await sendHtml(chatId, matchingErrorText(error));
      return;
    }
    throw error;
  }
}

export async function swapPair(chatId, userId, campaignId, giverId) {
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  if (loaded.campaign.status !== 'locked') {
    await sendHtml(chatId, 'Менять пары можно только до подтверждения жеребьёвки.');
    return;
  }
  const drafts = await listDrafts(campaignId);
  const pairs = drafts.map((row) => ({ giverId: row.giverId, receiverId: row.receiverId }));
  const rows = await listExclusions(campaignId);
  const exclusions = rows.map((row) => [row.userA, row.userB]);
  const swapped = swapReceiver({ pairs, giverId, exclusions });
  if (!swapped) {
    await sendHtml(chatId, 'Не получилось поменять только эту пару: мешают исключения. Можно пересобрать всё.');
    return;
  }
  await replaceDrafts(campaignId, swapped);
  await bumpCampaignVersion(campaignId);
  await logAudit(campaignId, userId, 'draw.swap', { giverId: Number(giverId) });
  await openDraw(chatId, userId, campaignId, 1);
}

export async function confirmDraw(chatId, userId, campaignId, token) {
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  if (loaded.campaign.status !== 'locked') {
    await sendHtml(chatId, 'Пары уже подтверждены или набор ещё не закрыт.');
    return;
  }
  if (Number(token) !== Number(loaded.campaign.version)) {
    await sendHtml(chatId, 'Пары изменились после открытия превью. Откройте превью заново.');
    return;
  }
  const drafts = await listDrafts(campaignId);
  if (drafts.length < 3) {
    await sendHtml(chatId, 'Нужно минимум три подтверждённых участника.');
    return;
  }
  await confirmDrafts(campaignId);
  await updateCampaign(campaignId, { status: 'drawn', drawnAt: new Date() });
  await logAudit(campaignId, userId, 'draw.confirm', { pairs: drafts.length });

  const confirmed = await listConfirmedMembers(campaignId);
  const appUrl = appUrlFromConfig(APP_URL);
  for (const member of confirmed) {
    await sendMiniApp(member.userId, assignmentIntro(), appUrl);
  }
  await sendHtml(chatId, '🎲 <b>Пары подтверждены</b>\n\nУчастники получили уведомление. Теперь можно начать вручение.');
}
