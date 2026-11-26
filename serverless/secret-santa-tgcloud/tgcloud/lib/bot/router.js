import { welcomeKeyboard } from '../keyboards.js';
import { helpText, privacyText, welcomeText } from '../texts.js';
import { answerCallback, sendHtml, setBotCommands } from '../telegram.js';
import { displayName, fromBase36 } from '../utils.js';
import { rememberUser } from '../store.js';
import {
  askLogo,
  handleCampaignLogoPhoto,
  handleCampaignLogoText,
  handleCancelConfirm,
  handleCompleteConfirm,
  handleFinal,
  handleLock,
  handleStartGifting,
  handleStats,
  openCampaign,
  openMyCampaigns,
  openRules,
  startFromPayload,
} from './dashboard.js';
import { beginWizard, handleWizardCallback, handleWizardPhoto, handleWizardText } from './wizard.js';
import { clear, getState } from './session.js';
import {
  addPair,
  askRemove,
  openExclusions,
  openMembers,
  pickFirst,
  pickSecond,
  removePair,
  removeParticipant,
  viewMember,
} from './members.js';
import { confirmDraw, openDraw, rebuildAll, swapPair } from './draw-flow.js';
import {
  cancelBroadcast,
  handleBroadcastText,
  openBroadcast,
  previewPreset,
  sendBroadcast,
  startCustom,
} from './broadcast.js';
import {
  banUser,
  blockReportedChat,
  hideReported,
  openReport,
  openReports,
} from './moderation.js';
import { banById, openAdmin, openGlobalStats, unbanById } from './superadmin.js';
import { isSuperAdmin } from './common.js';

function chatOf(message) {
  return message?.chat?.id;
}

function userIdOf(message) {
  return message?.from?.id;
}

function isPrivate(message) {
  return message?.chat?.type === 'private';
}

export async function handleMessage(message, ctx) {
  if (!message?.from || !isPrivate(message)) return;
  const chatId = chatOf(message);
  const userId = userIdOf(message);
  const user = await rememberUser(message.from);
  if (!chatId || !userId) return;
  if (user?.banned) {
    await sendHtml(chatId, 'Доступ к боту ограничен администратором.');
    return;
  }

  const text = String(message.text ?? '').trim();
  if (!text) {
    if (message.photo) {
      const photoSession = await getState(userId);
      if (photoSession?.step === 'wizard:logo') {
        await handleWizardPhoto(chatId, userId, photoSession, message);
        return;
      }
      if (String(photoSession?.step ?? '').startsWith('logo:')) {
        await handleCampaignLogoPhoto(chatId, userId, photoSession, message);
        return;
      }
      await sendHtml(
        chatId,
        'Фото загружается в приложении при редактировании желания. Откройте вишлист в Mini App.',
      );
    }
    return;
  }

  const [rawCommand, ...args] = text.split(/\s+/);
  const command = rawCommand.toLowerCase().split('@')[0];

  try {
    if (command === '/start') {
      const payload = text.slice(rawCommand.length).trim();
      await clear(userId);
      if (payload) {
        await startFromPayload(chatId, userId, payload);
      } else {
        await sendHtml(chatId, welcomeText(message.from.first_name), welcomeKeyboard());
      }
      return;
    }
    if (command === '/new') {
      await beginWizard(chatId, userId);
      return;
    }
    if (command === '/my') {
      await openMyCampaigns(chatId, userId);
      return;
    }
    if (command === '/help') {
      await sendHtml(chatId, helpText(isSuperAdmin(userId)), welcomeKeyboard());
      return;
    }
    if (command === '/privacy') {
      await sendHtml(chatId, privacyText(), welcomeKeyboard());
      return;
    }
    if (command === '/id') {
      await sendHtml(chatId, `Ваш Telegram ID: <code>${userId}</code>`);
      return;
    }
    if (command === '/cancel') {
      await clear(userId);
      await sendHtml(chatId, 'Действие отменено.', welcomeKeyboard());
      return;
    }
    if (command === '/setcommands') {
      if (!isSuperAdmin(userId)) {
        await sendHtml(chatId, 'Команда доступна супер-админу.');
        return;
      }
      const ok = await setBotCommands();
      await sendHtml(chatId, ok ? 'Меню команд обновлено.' : 'Не удалось обновить меню, смотрите логи.');
      return;
    }
    if (command === '/admin') {
      await openAdmin(chatId, userId);
      return;
    }
    if (command === '/stats') {
      await openGlobalStats(chatId, userId);
      return;
    }
    if (command === '/ban') {
      await banById(chatId, userId, args[0]);
      return;
    }
    if (command === '/unban') {
      await unbanById(chatId, userId, args[0]);
      return;
    }

    const session = await getState(userId);
    if (session?.step?.startsWith('wizard:')) {
      await handleWizardText(chatId, userId, session, text);
      return;
    }
    if (session?.step === 'broadcast:text') {
      await handleBroadcastText(chatId, userId, session, text);
      return;
    }
    if (session?.step === 'broadcast:preview') {
      await sendHtml(chatId, 'Осталось нажать «Отправить» или «Отмена» под превью.');
      return;
    }
    if (String(session?.step ?? '').startsWith('logo:')) {
      await handleCampaignLogoText(chatId, userId, session, text);
      return;
    }

    await sendHtml(chatId, 'Не понял команду. Откройте /help или выберите действие в меню.', welcomeKeyboard());
  } catch (error) {
    console.error('handleMessage failed', error);
    await sendHtml(chatId, 'Что-то пошло не так. Попробуйте ещё раз или откройте /help.').catch(() => {});
  }
}

function decodeId(part) {
  return fromBase36(part);
}

export async function handleCallback(query, ctx) {
  const chatId = query?.message?.chat?.id;
  const callbackId = query?.id;
  const userId = query?.from?.id;
  await answerCallback(callbackId);
  if (!chatId || !userId) return;
  const user = await rememberUser(query.from);
  if (user?.banned) {
    await sendHtml(chatId, 'Доступ к боту ограничен администратором.');
    return;
  }

  const data = String(query.data ?? '');
  if (!data.startsWith('ss:')) return;
  const parts = data.split(':');
  const action = parts[1];
  const messageId = query?.message?.message_id ?? null;

  try {
    if (action === 'my') {
      await openMyCampaigns(chatId, userId, messageId);
      return;
    }
    if (action === 'nw') {
      await beginWizard(chatId, userId);
      return;
    }
    if (action === 'hl') {
      await sendHtml(chatId, helpText(isSuperAdmin(userId)), welcomeKeyboard());
      return;
    }
    if (action === 'wd' || action === 'wz' || action === 'wc') {
      await handleWizardCallback(chatId, userId, action, parts[2], messageId);
      return;
    }
    if (action === 'as') {
      await openGlobalStats(chatId, userId);
      return;
    }

    const campaignId = decodeId(parts[2]);
    if (!campaignId) {
      await sendHtml(chatId, 'Кампания не найдена.');
      return;
    }

    if (action === 'co') {
      await openCampaign(chatId, userId, campaignId, messageId);
      return;
    }
    if (action === 'lg') {
      await askLogo(chatId, userId, campaignId);
      return;
    }
    if (action === 'cr') {
      await openRules(chatId, userId, campaignId, messageId);
      return;
    }
    if (action === 'mp') {
      await openMembers(chatId, userId, campaignId, Number(parts[3]) || 1, messageId);
      return;
    }
    if (action === 'mi') {
      const memberId = decodeId(parts[3]);
      if (memberId) await viewMember(chatId, userId, campaignId, memberId, messageId);
      return;
    }
    if (action === 'mx') {
      const memberId = decodeId(parts[3]);
      if (memberId) await askRemove(chatId, userId, campaignId, memberId, messageId);
      return;
    }
    if (action === 'mz') {
      const memberId = decodeId(parts[3]);
      const token = decodeId(parts[4]);
      if (memberId && token) await removeParticipant(chatId, userId, campaignId, memberId, token);
      return;
    }
    if (action === 'xl') {
      await openExclusions(chatId, userId, campaignId, messageId);
      return;
    }
    if (action === 'xa') {
      await pickFirst(chatId, userId, campaignId, Number(parts[3]) || 1);
      return;
    }
    if (action === 'xb') {
      const first = decodeId(parts[3]);
      if (first) await pickSecond(chatId, userId, campaignId, first, Number(parts[4]) || 1);
      return;
    }
    if (action === 'xc') {
      const first = decodeId(parts[3]);
      const second = decodeId(parts[4]);
      if (first && second) await addPair(chatId, userId, campaignId, first, second);
      return;
    }
    if (action === 'xr') {
      const exclusionId = decodeId(parts[3]);
      if (exclusionId) await removePair(chatId, userId, campaignId, exclusionId);
      return;
    }
    if (action === 'dl') {
      await handleLock(chatId, userId, campaignId, messageId);
      return;
    }
    if (action === 'dv') {
      await openDraw(chatId, userId, campaignId, Number(parts[3]) || 1);
      return;
    }
    if (action === 'dr') {
      const giverId = decodeId(parts[3]);
      if (giverId) await swapPair(chatId, userId, campaignId, giverId);
      return;
    }
    if (action === 'dg') {
      await rebuildAll(chatId, userId, campaignId);
      return;
    }
    if (action === 'dk') {
      const token = decodeId(parts[3]);
      if (token) await confirmDraw(chatId, userId, campaignId, token);
      return;
    }
    if (action === 'gi') {
      await handleStartGifting(chatId, userId, campaignId, messageId);
      return;
    }
    if (action === 'st') {
      await handleStats(chatId, userId, campaignId, messageId);
      return;
    }
    if (action === 'cm') {
      await handleCompleteConfirm(chatId, userId, campaignId, messageId);
      return;
    }
    if (action === 'cn') {
      await handleCancelConfirm(chatId, userId, campaignId, messageId);
      return;
    }
    if (action === 'mc' || action === 'nc') {
      const token = decodeId(parts[3]);
      if (token) await handleFinal(chatId, userId, campaignId, action === 'mc' ? 'm' : 'n', token);
      return;
    }
    if (action === 'bl') {
      await openBroadcast(chatId, userId, campaignId);
      return;
    }
    if (action === 'bp') {
      await previewPreset(chatId, userId, campaignId, parts[3]);
      return;
    }
    if (action === 'bc') {
      await startCustom(chatId, userId, campaignId);
      return;
    }
    if (action === 'bs') {
      await sendBroadcast(chatId, userId, campaignId);
      return;
    }
    if (action === 'bx') {
      await cancelBroadcast(chatId, userId, campaignId);
      return;
    }
    if (action === 'rv') {
      const reprotId = decodeId(parts[2]);
      if (reprotId) await openReport(chatId, userId, reprotId, messageId);
      return;
    }
    if (action === 'rh') {
      const reprotId = decodeId(parts[2]);
      if (reprotId) await hideReported(chatId, userId, reprotId);
      return;
    }
    if (action === 'rb') {
      const assignmentId = decodeId(parts[2]);
      if (assignmentId) await blockReportedChat(chatId, userId, assignmentId);
      return;
    }
    if (action === 'ru') {
      const targetId = decodeId(parts[2]);
      if (targetId) await banUser(chatId, userId, targetId);
      return;
    }
    if (action === 'rl') {
      await openReports(chatId, userId, Number(parts[2]) || 1, messageId);
      return;
    }

    await sendHtml(chatId, 'Кнопка устарела. Откройте кампанию заново.', welcomeKeyboard());
  } catch (error) {
    console.error('handleCallback failed', error);
    await sendHtml(chatId, 'Не удалось выполнить действие. Откройте кампанию заново.').catch(() => {});
  }
}
