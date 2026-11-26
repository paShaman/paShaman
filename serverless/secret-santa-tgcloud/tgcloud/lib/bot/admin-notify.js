import { ADMIN_IDS, APP_URL } from '../config.js';
import { appRow, btn, inline } from '../keyboards.js';
import { sendHtml } from '../telegram.js';
import { displayName, esc, formatDateRu, pluralRu, toBase36 } from '../utils.js';
import { appUrlFromConfig } from './common.js';

function adminKeyboard(campaignId) {
  const rows = [];
  const app = appRow(appUrlFromConfig(APP_URL));
  if (app.length) rows.push(app);
  if (campaignId) rows.push([btn('Открыть кампанию', `ss:co:${toBase36(campaignId)}`)]);
  return inline(rows);
}

async function sendToAdmins(html, keyboard) {
  for (const id of ADMIN_IDS.map(Number)) {
    if (!id) continue;
    try {
      await sendHtml(id, html, keyboard);
    } catch (error) {
      console.warn(`admin notify ${id} failed`, error instanceof Error ? error.message : error);
    }
  }
}

function newCampaignText(campaign, organizer) {
  const lines = [
    '🆕 <b>Новая кампания</b>',
    '',
    `<b>${esc(campaign.title)}</b>`,
    `Организатор: ${esc(displayName(organizer))}`,
  ];
  if (campaign.giftDate) lines.push(`Вручение: ${formatDateRu(campaign.giftDate)}`);
  return lines.join('\n');
}

export async function notifySuperAdminsNewCampaign(campaign, organizer) {
  if (!campaign) return;
  await sendToAdmins(newCampaignText(campaign, organizer), adminKeyboard(campaign.id));
}

export async function notifySuperAdminsCampaignFinished(campaign, summary) {
  if (!campaign) return;
  const tracked = summary?.tracked !== false;
  const confirmed = Number(summary?.confirmed) || 0;
  const received = Number(summary?.received) || 0;
  const receivedLine = !tracked
    ? 'Подарки передавали лично — статусы не отслеживались.'
    : confirmed > 0 && received >= confirmed
      ? `Подарки получили все ${confirmed} ${pluralRu(confirmed, 'участник', 'участника', 'участников')}.`
      : received === 0
        ? `Получение не отметил никто из ${confirmed} ${pluralRu(confirmed, 'участника', 'участников', 'участников')}.`
        : `Подарки получили ${received} ${pluralRu(received, 'человек', 'человека', 'человек')} из ${confirmed}.`;
  const text = [
    '🏁 <b>Кампания завершена</b>',
    '',
    `<b>${esc(campaign.title)}</b>`,
    receivedLine,
  ].join('\n');
  await sendToAdmins(text, adminKeyboard(campaign.id));
}
