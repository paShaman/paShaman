import { api, BotApiError } from 'sdk';
import { splitMessage, stripHtml } from './utils.js';

export async function sendMessage(chatId, payload) {
  try {
    await api.sendMessage(payload);
    return true;
  } catch (error) {
    if (!(error instanceof BotApiError)) throw error;
    console.warn(`sendMessage ${chatId}: ${error.code} ${error.description}`);
    return false;
  }
}

export async function sendHtml(chatId, html, keyboard) {
  const chunks = splitMessage(html);
  let allOk = true;
  const last = chunks.length - 1;

  for (let i = 0; i < chunks.length; i++) {
    const payload = {
      chat_id: chatId,
      text: chunks[i],
      parse_mode: 'HTML',
      disable_web_page_preview: true,
    };
    if (keyboard && i === last) payload.reply_markup = keyboard;

    if (!(await sendMessage(chatId, payload))) {
      const plain = {
        chat_id: chatId,
        text: stripHtml(chunks[i]),
        disable_web_page_preview: true,
      };
      if (keyboard && i === last) plain.reply_markup = keyboard;
      if (!(await sendMessage(chatId, plain))) allOk = false;
    }
  }

  return allOk;
}

export async function sendRich(chatId, html) {
  if (html.length <= 32768) {
    try {
      await api.sendRichMessage({
        chat_id: chatId,
        rich_message: { html: html.replace(/\n/g, '<br>') },
      });
      return true;
    } catch (error) {
      if (!(error instanceof BotApiError)) throw error;
      console.warn(`sendRichMessage ${chatId}: ${error.code} ${error.description}`);
    }
  }
  return sendHtml(chatId, stripHtml(html));
}

export async function answerCallback(callbackId) {
  if (!callbackId) return;
  try {
    await api.answerCallbackQuery({ callback_query_id: callbackId });
  } catch (error) {
    if (!(error instanceof BotApiError)) throw error;
    console.warn(`answerCallbackQuery: ${error.code} ${error.description}`);
  }
}
