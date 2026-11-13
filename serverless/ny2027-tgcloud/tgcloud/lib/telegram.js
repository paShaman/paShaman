import { api, BotApiError, InputFile } from 'sdk';
import { WELCOME } from './config.js';
import { goatImageBytes } from './goat-image.js';
import { splitMessage, stripHtml, visibleLen } from './utils.js';

export async function sendHtml(chatId, html, keyboard) {
  const chunks = splitMessage(html);
  let allOk = true;

  for (let index = 0; index < chunks.length; index++) {
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

    const plain = {
      chat_id: chatId,
      text: stripHtml(chunks[index]),
      disable_web_page_preview: true,
    };
    if (keyboard && last) plain.reply_markup = keyboard;

    try {
      await api.sendMessage(plain);
    } catch (error) {
      console.warn(
        `sendMessage failed: ${error instanceof BotApiError ? `${error.code} ${error.description}` : error}`,
      );
      allOk = false;
    }
  }

  return allOk;
}

export async function editText(chatId, messageId, text, parseMode, keyboard) {
  if (!messageId) return false;

  const params = {
    chat_id: chatId,
    message_id: messageId,
    text,
    reply_markup: keyboard ?? { inline_keyboard: [] },
  };
  if (parseMode) params.parse_mode = parseMode;

  try {
    await api.editMessageText(params);
    return true;
  } catch (error) {
    if (!(error instanceof BotApiError)) throw error;
    console.warn(`editMessageText failed: ${error.code} ${error.description}`);
    return false;
  }
}

export async function answerCallback(callbackId) {
  if (!callbackId) return;
  try {
    await api.answerCallbackQuery({ callback_query_id: callbackId });
  } catch (error) {
    if (!(error instanceof BotApiError)) throw error;
    console.warn(`answerCallbackQuery failed: ${error.code} ${error.description}`);
  }
}

// Меню «/» в Telegram — те же команды, что в PHP-версии.
export async function setBotCommands() {
  try {
    await api.setMyCommands({
      commands: [
        { command: 'start', description: 'Приглашение и анкета' },
        { command: 'my', description: 'Моя анкета' },
        { command: 'game', description: 'Мини-игра «Новогодний заезд»' },
        { command: 'help', description: 'Помощь' },
        { command: 'cancel', description: 'Отменить заполнение' },
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

export async function sendWelcome(chatId, registered, keyboard) {
  if (visibleLen(WELCOME) <= 1024) {
    // Основной источник — картинка внутри проекта, без внешнего URL.
    try {
      const photo = new InputFile(goatImageBytes(), 'goat.jpg', { type: 'image/jpeg' });
      await api.sendPhoto({
        chat_id: chatId,
        photo,
        caption: WELCOME,
        parse_mode: 'HTML',
        reply_markup: keyboard,
      });
      return;
    } catch (error) {
      console.warn(
        `sendPhoto embedded failed: ${
          error instanceof BotApiError ? `${error.code} ${error.description}` : error
        }`,
      );
    }
  }

  // Если встроенную картинку отправить не удалось — только текст, без внешних URL.
  await sendHtml(chatId, WELCOME, keyboard);
}

export async function sendCsv(chatId, csv, caption) {
  try {
    const bytes = new TextEncoder().encode(csv);
    const file = new InputFile(bytes, 'ny2027_ankety.csv', { type: 'text/csv' });
    await api.sendDocument({ chat_id: chatId, document: file, caption });
    return true;
  } catch (error) {
    console.warn(
      `sendDocument failed: ${error instanceof BotApiError ? `${error.code} ${error.description}` : error}`,
    );
    return false;
  }
}

export async function broadcastSend(chatId, html) {
  try {
    await api.sendMessage({
      chat_id: chatId,
      text: html,
      parse_mode: 'HTML',
      disable_web_page_preview: true,
    });
    return 'ok';
  } catch (error) {
    if (error instanceof BotApiError) {
      const description = String(error.description ?? '');
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

    console.warn(`broadcast ${chatId}: ${error}`);
    return 'error';
  }
}
