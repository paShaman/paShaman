import { onMounted, onUnmounted, ref } from 'vue';
import { call } from '../api/client.js';

export function useUnread(campaignId) {
  const unread = ref({ santa: 0, recipient: 0 });
  const roles = ref([]);
  let timer = null;

  async function load() {
    if (typeof document !== 'undefined' && document.visibilityState !== 'visible') return;
    try {
      const result = await call('getUnread', { campaignId: Number(campaignId) });
      roles.value = result.roles ?? [];
      unread.value = {
        santa: Number(result.unread?.santa) || 0,
        recipient: Number(result.unread?.recipient) || 0,
      };
    } catch {
      // счётчик не критичен для страницы
    }
  }

  onMounted(() => {
    load();
    timer = setInterval(load, 20000);
  });

  onUnmounted(() => {
    if (timer) clearInterval(timer);
  });

  return { unread, roles, refresh: load };
}
