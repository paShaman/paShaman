import { handleCallback } from '../lib/bot/router.js';

export default async function (callbackQuery, ctx) {
  await handleCallback(callbackQuery, ctx);
}
