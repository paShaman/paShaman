import { ref, computed, onMounted, onUnmounted } from 'vue';
import { call, ApiError } from '../api/client.js';
import { toast } from '../composables/useSession.js';

export function useCampaign(campaignId) {
  const campaign = ref(null);
  const loading = ref(true);
  const error = ref('');
  const busy = ref(false);

  async function load() {
    loading.value = true;
    error.value = '';
    try {
      campaign.value = await call('getCampaign', { campaignId: Number(campaignId) });
    } catch (e) {
      error.value = e.message || 'Не удалось загрузить кампанию.';
    } finally {
      loading.value = false;
    }
  }

  async function act(name, input = {}, successText = '') {
    busy.value = true;
    try {
      const result = await call(name, { campaignId: Number(campaignId), ...input });
      if (successText) toast(successText);
      await load();
      return result;
    } catch (e) {
      if (e instanceof ApiError) toast(e.message);
      else toast('Не удалось выполнить действие.');
      throw e;
    } finally {
      busy.value = false;
    }
  }

  onMounted(load);

  return {
    campaign,
    loading,
    error,
    busy,
    load,
    act,
    canWishlist: computed(() => Boolean(campaign.value?.can?.editWishlist)),
  };
}
