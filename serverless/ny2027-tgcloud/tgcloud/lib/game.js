import { api, BotApiError } from 'sdk';
import { GAME_URL } from './config.js';

const GAME_CAPTION =
  '🚗 <b>Новогодний заезд</b>\n' +
  'Тап по полю — прыжок через колонки АЗС. Чем дальше, тем быстрее.';

function gameKeyboard(withWebApp) {
  const button = withWebApp
    ? { text: '🎮 Играть', web_app: { url: GAME_URL } }
    : { text: '🎮 Играть', url: GAME_URL };
  return { inline_keyboard: [[button]] };
}

async function send(chatId, withWebApp) {
  await api.sendMessage({
    chat_id: chatId,
    text: GAME_CAPTION,
    parse_mode: 'HTML',
    reply_markup: gameKeyboard(withWebApp),
  });
}

export async function sendGame(chatId) {
  try {
    await send(chatId, true);
    return true;
  } catch (error) {
    console.warn(
      `sendMessage web_app failed: ${
        error instanceof BotApiError ? `${error.code} ${error.description}` : error
      }`,
    );
  }

  // Если клиент/бот не принимает web_app — обычная ссылка на тот же Mini App.
  try {
    await send(chatId, false);
    return true;
  } catch (error) {
    console.warn(
      `sendMessage game link failed: ${
        error instanceof BotApiError ? `${error.code} ${error.description}` : error
      }`,
    );
    return false;
  }
}
