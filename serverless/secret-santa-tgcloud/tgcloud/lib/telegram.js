import { api, BotApiError, InputFile } from 'sdk';
import { splitMessage, stripHtml, bytesToBase64, detectImageMime } from './utils.js';

export async function sendHtml(chatId, html, keyboard) {
  const chunks = splitMessage(html);
  let ok = true;
  for (let index = 0; index < chunks.length; index += 1) {
    const last = index === chunks.length - 1;
    const params = {
      chat_id: chatId,
      text: chunks[index],
      parse_mode: 'HTML',
      disable_web_page_preview: true,
    };
    if (keyboard && last) params.reply_markup = keyboard;
    try {
      await api.sendMessage(params);
      continue;
    } catch (error) {
      if (!(error instanceof BotApiError)) throw error;
      console.warn(`sendMessage HTML failed: ${error.code} ${error.description}`);
    }
    try {
      await api.sendMessage({
        chat_id: chatId,
        text: stripHtml(chunks[index]),
        disable_web_page_preview: true,
        ...(keyboard && last ? { reply_markup: keyboard } : {}),
      });
    } catch (error) {
      console.warn(
        `sendMessage failed: ${
          error instanceof BotApiError ? `${error.code} ${error.description}` : error
        }`,
      );
      ok = false;
    }
  }
  return ok;
}

export async function editText(chatId, messageId, text, keyboard) {
  if (!messageId) return false;
  if (text.length > 3996) return false;
  try {
    await api.editMessageText({
      chat_id: chatId,
      message_id: messageId,
      text,
      parse_mode: 'HTML',
      disable_web_page_preview: true,
      reply_markup: keyboard ?? { inline_keyboard: [] },
    });
    return true;
  } catch (error) {
    if (error instanceof BotApiError) {
      console.warn(`editMessageText failed: ${error.code} ${error.description}`);
      return false;
    }
    throw error;
  }
}

export async function render(chatId, messageId, text, keyboard) {
  if (messageId && (await editText(chatId, messageId, text, keyboard))) return;
  await sendHtml(chatId, text, keyboard);
}

export async function answerCallback(callbackId, text, showAlert = false) {
  if (!callbackId) return;
  try {
    await api.answerCallbackQuery({
      callback_query_id: callbackId,
      ...(text ? { text, show_alert: showAlert } : {}),
    });
  } catch (error) {
    if (error instanceof BotApiError) {
      console.warn(`answerCallbackQuery failed: ${error.code} ${error.description}`);
      return;
    }
    throw error;
  }
}

export async function sendPhotoBytes(chatId, bytes, caption, keyboard) {
  const photo = new InputFile(bytes, 'secret-santa.jpg', { type: 'image/jpeg' });
  await api.sendPhoto({
    chat_id: chatId,
    photo,
    caption,
    parse_mode: 'HTML',
    ...(keyboard ? { reply_markup: keyboard } : {}),
  });
}

export async function uploadPhotoToTelegram(chatId, bytes, mime) {
  const extension = mime === 'image/png' ? 'png' : 'jpg';
  const photo = new InputFile(bytes, `wishlist.${extension}`, { type: mime });
  const message = await api.sendPhoto({
    chat_id: chatId,
    photo,
    disable_notification: true,
  });
  const sizes = Array.isArray(message.photo) ? message.photo : [];
  const fileId = sizes.length > 0 ? sizes[sizes.length - 1].file_id : null;
  if (message.message_id) {
    try {
      await api.deleteMessage({ chat_id: chatId, message_id: message.message_id });
    } catch (error) {
      if (error instanceof BotApiError) {
        console.warn(`deleteMessage failed: ${error.code} ${error.description}`);
      }
    }
  }
  if (!fileId) throw new Error('NO_FILE_ID');
  return fileId;
}

export async function photoDataUrl(fileId) {
  const bytes = await api.getFileContent(fileId);
  const mime = detectImageMime(bytes) ?? 'image/jpeg';
  return `data:${mime};base64,${bytesToBase64(bytes)}`;
}

export async function profilePhotoDataUrl(userId) {
  const result = await api.getUserProfilePhotos({ user_id: Number(userId), limit: 1 });
  const sizes = result?.photos?.[0];
  if (!Array.isArray(sizes) || sizes.length === 0) return '';
  for (const size of [sizes[0], sizes[sizes.length - 1]]) {
    if (!size?.file_id) continue;
    try {
      return await photoDataUrl(size.file_id);
    } catch {
      // пробуем другой размер
    }
  }
  return '';
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

export async function sendBroadcastMessage(chatId, html, attempt = 0) {
  try {
    await api.sendMessage({
      chat_id: chatId,
      text: html,
      parse_mode: 'HTML',
      disable_web_page_preview: true,
    });
    return 'ok';
  } catch (error) {
    if (!(error instanceof BotApiError)) return 'error';
    const description = String(error.description ?? '');
    if (error.code === 429) {
      if (attempt >= 1) {
        console.warn(`broadcast ${chatId}: 429 after retry, giving up`);
        return 'limited';
      }
      const retryAfter = Number(error.parameters?.retry_after);
      const waitMs = Math.min(Number.isFinite(retryAfter) && retryAfter > 0 ? retryAfter : 1, 5) * 1000;
      console.warn(`broadcast ${chatId}: 429, retrying in ${waitMs} ms`);
      await sleep(waitMs);
      return sendBroadcastMessage(chatId, html, attempt + 1);
    }
    if (
      error.code === 403 ||
      (error.code === 400 &&
        (/chat not found/i.test(description) || /deactivated/i.test(description)))
    ) {
      return 'blocked';
    }
    console.warn(`broadcast ${chatId}: ${error.code} ${description}`);
    return 'error';
  }
}

export async function sendMiniApp(chatId, text, url) {
  if (!url) {
    await sendHtml(chatId, text);
    return;
  }
  const keyboard = {
    inline_keyboard: [[{ text: 'Открыть приложение', web_app: { url } }]],
  };
  try {
    await api.sendMessage({
      chat_id: chatId,
      text,
      parse_mode: 'HTML',
      disable_web_page_preview: true,
      reply_markup: keyboard,
    });
  } catch (error) {
    if (!(error instanceof BotApiError)) throw error;
    console.warn(`web_app send failed: ${error.code} ${error.description}`);
    await sendHtml(chatId, text, {
      inline_keyboard: [[{ text: 'Открыть приложение', url }]],
    });
  }
}

export async function setBotCommands() {
  try {
    await api.setMyCommands({
      commands: [
        { command: 'start', description: 'Главное меню' },
        { command: 'new', description: 'Создать кампанию' },
        { command: 'my', description: 'Мои кампании' },
        { command: 'help', description: 'Помощь' },
        { command: 'cancel', description: 'Отменить действие' },
        { command: 'privacy', description: 'Приватность' },
      ],
    });
    return true;
  } catch (error) {
    console.warn(
      `setMyCommands failed: ${
        error instanceof BotApiError ? `${error.code} ${error.description}` : error
      }`,
    );
    return false;
  }
}
