import { ref } from 'vue';
import { call } from '../api/client.js';
import { state } from './useSession.js';
import { telegramUser } from '../telegram/webapp.js';

const avatar = ref('');
let requested = false;

async function load() {
  const direct = state.boot?.user?.photoUrl || telegramUser()?.photo_url || '';
  if (direct) {
    avatar.value = direct;
    return;
  }
  if (requested) return;
  requested = true;
  try {
    const result = await call('getMyAvatar', {});
    avatar.value = result?.photo || '';
  } catch {
    avatar.value = '';
    requested = false;
  }
}

export function useMyAvatar() {
  return { avatar, load };
}
