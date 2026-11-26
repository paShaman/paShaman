import { handleMessage } from '../lib/bot/router.js';

export default async function (message, ctx) {
  await handleMessage(message, ctx);
}
