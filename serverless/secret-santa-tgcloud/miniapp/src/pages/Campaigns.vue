<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import tree from '../assets/tree-192.png';
import { call, ApiError } from '../api/client.js';
import { state, refreshBoot, toast } from '../composables/useSession.js';
import CampaignStatus from '../components/campaign/CampaignStatus.vue';

const router = useRouter();
const loading = ref(false);
const code = ref('');
const error = ref('');
const logos = reactive({});

const campaigns = computed(() => state.boot?.campaigns ?? []);

async function loadLogos() {
  const list = state.boot?.campaigns ?? [];
  await Promise.all(
    list
      .filter((campaign) => campaign.hasLogo)
      .map(async (campaign) => {
        try {
          const result = await call('getCampaignLogo', { campaignId: campaign.id });
          logos[campaign.id] = result?.photo ?? '';
        } catch {
          // без логотипа оставляем стандартную иконку
        }
      }),
  );
}

onMounted(loadLogos);

function formatDate(value) {
  if (!value) return '—';
  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(value));
}

async function join() {
  const value = code.value.trim().toUpperCase();
  if (value.length !== 6) {
    error.value = 'Код состоит из 6 символов.';
    return;
  }
  loading.value = true;
  error.value = '';
  try {
    const result = await call('joinCampaign', { code: value });
    await refreshBoot();
    toast('Вы вступили в кампанию.');
    router.push(`/c/${result.campaignId}`);
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Не удалось вступить.';
  } finally {
    loading.value = false;
  }
}

async function refresh() {
  loading.value = true;
  try {
    await refreshBoot();
    await loadLogos();
    toast('Список обновлён.');
  } catch {
    toast('Не удалось обновить.');
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="page">
    <div class="row row--between">
      <div>
        <p class="eyebrow">Мои кампании</p>
        <h1 class="display" style="font-size: clamp(24px, 6vw, 30px)">Куда идём дарить?</h1>
      </div>
      <button class="btn btn--ghost btn--sm" type="button" :disabled="loading" @click="refresh">
        Обновить
      </button>
    </div>

    <div v-if="campaigns.length" class="list">
      <button
        v-for="campaign in campaigns"
        :key="campaign.id"
        class="tile"
        type="button"
        @click="router.push(`/c/${campaign.id}`)"
      >
        <span class="tile__mark" aria-hidden="true">
          <img v-if="logos[campaign.id]" :src="logos[campaign.id]" alt="" width="44" height="44" />
          <img v-else :src="tree" alt="" width="44" height="44" />
        </span>
        <span class="tile__body">
          <span class="tile__title">{{ campaign.title }}</span>
          <span class="tile__meta">
            {{ campaign.role === 'organizer' ? 'Организатор' : 'Участник' }} ·
            {{ formatDate(campaign.giftDate) }}
          </span>
        </span>
        <CampaignStatus :status="campaign.status" />
      </button>
    </div>

    <div v-else class="empty">
      <img :src="tree" alt="" width="96" height="96" />
      <p class="small">Пока нет кампаний. Вступите по коду или создайте кампанию в боте.</p>
    </div>

    <div class="card stack">
      <h2 class="h2">Вступить по коду</h2>
      <form class="stack" @submit.prevent="join">
        <div class="field">
          <label for="code">Код кампании</label>
          <input
            id="code"
            v-model="code"
            class="input input--code"
            maxlength="6"
            placeholder="XMAS26"
            autocomplete="one-time-code"
          />
        </div>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
        <button class="btn btn--primary btn--block" type="submit" :disabled="loading">
          {{ loading ? 'Вступаем…' : 'Вступить' }}
        </button>
      </form>
    </div>
  </div>
</template>
