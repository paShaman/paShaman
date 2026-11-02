import { handleCallback } from '../lib/bot.js';

export default async function (callbackQuery, ctx) {
  await handleCallback(callbackQuery, ctx);
}
