import { api, BotApiError } from 'sdk';
import { GAME_HTML } from './game-widget.js';

const GAME_CAPTION =
  '🚗 <b>Новогодний заезд</b>\n' +
  'Тап по полю — прыжок через колонки АЗС. Чем дальше, тем быстрее.';

export async function sendGame(chatId) {
  try {
    await api.sendWidget({
      chat_id: chatId,
      html: GAME_HTML,
      width: 600,
      caption: GAME_CAPTION,
      parse_mode: 'HTML',
    });
    return true;
  } catch (error) {
    console.warn(
      `sendWidget failed: ${
        error instanceof BotApiError ? `${error.code} ${error.description}` : error
      }`,
    );
    return false;
  }
}
