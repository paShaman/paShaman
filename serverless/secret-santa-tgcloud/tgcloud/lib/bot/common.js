import { ADMIN_IDS } from '../config.js';
import {
  getCampaign,
  getCampaignByCode,
  getMembership,
  listMembersDetailed,
} from '../store.js';
import { displayName } from '../utils.js';
import { sendHtml } from '../telegram.js';

export function isSuperAdmin(userId) {
  return ADMIN_IDS.map(String).includes(String(userId));
}

export async function loadCampaignFor(userId, campaignId) {
  const campaign = await getCampaign(campaignId);
  if (!campaign) return { error: 'Кампания не найдена.' };
  const member = await getMembership(campaign.id, userId);
  if (!member || ['removed', 'declined'].includes(member.status)) {
    return { error: 'Вы не участвуете в этой кампании.' };
  }
  return { campaign, member };
}

export async function loadCampaignByCode(code) {
  return getCampaignByCode(code);
}

export async function loadOrganizerCampaign(userId, campaignId) {
  const loaded = await loadCampaignFor(userId, campaignId);
  if (loaded.error) return loaded;
  if (loaded.campaign.organizerId !== Number(userId) && !isSuperAdmin(userId)) {
    return { error: 'Это действие доступно организатору кампании.' };
  }
  return loaded;
}

export function namesMap(members) {
  const map = new Map();
  for (const member of members) {
    map.set(member.userId, displayName(member.user));
  }
  return map;
}

export async function campaignNames(campaignId) {
  const members = await listMembersDetailed(campaignId);
  return { members, names: namesMap(members) };
}

export async function notifyUser(userId, html, keyboard) {
  try {
    await sendHtml(userId, html, keyboard);
    return true;
  } catch (error) {
    console.warn(`notify ${userId} failed: ${error instanceof Error ? error.message : error}`);
    return false;
  }
}

export function appUrlFromConfig(url) {
  return /^https:\/\//i.test(String(url ?? '')) ? url : '';
}
