<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import hero from '../assets/hero-1280.jpg';
import heroSmall from '../assets/hero-640.jpg';
import tree from '../assets/tree-192.png';
import { call, ApiError } from '../api/client.js';
import { state, refreshBoot, toast } from '../composables/useSession.js';

const router = useRouter();
const code = ref('');
const busy = ref(false);
const error = ref('');

const campaigns = computed(() => state.boot?.campaigns ?? []);
const latest = computed(() => campaigns.value[0] ?? null);

async function join() {
  const value = code.value.trim().toUpperCase();
  if (value.length !== 6) {
    error.value = 'Код состоит из 6 символов.';
    return;
  }
  busy.value = true;
  error.value = '';
  try {
    const result = await call('joinCampaign', { code: value });
    await refreshBoot();
    toast('Вы вступили в кампанию.');
    router.push(`/c/${result.campaignId}`);
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Не удалось вступить в кампанию.';
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <div class="page">
    <div class="hero">
      <div class="hero__media">
        <img
          :src="hero"
          :srcset="`${heroSmall} 640w, ${hero} 1280w`"
          sizes="(max-width: 560px) 100vw, 560px"
          width="1280"
          height="832"
          alt=""
        />
      </div>
      <div class="hero__body">
        <h1 class="display">Подарки, сюрпризы и немного зимнего волшебства</h1>
        <p class="small" style="margin-top: 8px">
          Соберите друзей, заполните вишлист и обменяйтесь подарками анонимно.
        </p>
      </div>
    </div>

    <template v-if="latest">
      <div class="card stack">
        <div class="row row--between">
          <p class="eyebrow">Текущая кампания</p>
          <img :src="tree" alt="" width="42" height="42" style="border-radius: 14px" />
        </div>
        <h2 class="h2">{{ latest.title }}</h2>
        <p class="lede">
          {{ latest.role === 'organizer' ? 'Вы организатор' : 'Вы участник' }} ·
          {{ latest.confirmed ? 'участие подтверждено' : 'нужно подтвердить участие' }}
        </p>
        <button class="btn btn--primary btn--block" type="button" @click="router.push(`/c/${latest.id}`)">
          Продолжить
        </button>
        <button class="btn btn--secondary btn--block" type="button" @click="router.push('/campaigns')">
          Все кампании
        </button>
      </div>
    </template>

    <div class="card stack">
      <div>
        <p class="eyebrow">Присоединиться</p>
        <h2 class="h2">Есть код от организатора?</h2>
      </div>
      <form class="stack" @submit.prevent="join">
        <div class="field">
          <label for="join-code">Код кампании</label>
          <input
            id="join-code"
            v-model="code"
            class="input input--code"
            maxlength="6"
            autocomplete="one-time-code"
            placeholder="XMAS26"
            aria-describedby="join-hint"
          />
          <p id="join-hint" class="hint">6 символов, например XMAS26.</p>
        </div>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
        <button class="btn btn--primary btn--block" type="submit" :disabled="busy">
          {{ busy ? 'Вступаем…' : 'Вступить в кампанию' }}
        </button>
      </form>
    </div>

    <div class="empty">
      <img :src="tree" alt="" width="96" height="96" />
      <p class="small">
        Создать новую кампанию можно в боте: отправьте ему команду <b>/new</b>. После публикации
        он пришлёт код и кнопку приглашения.
      </p>
    </div>
  </div>
</template>
