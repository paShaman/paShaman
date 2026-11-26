<script setup>
import { computed, onMounted, onUnmounted, watch } from 'vue';
import { RouterView, useRoute, useRouter } from 'vue-router';
import mark from './assets/tree-192.png';
import { call, ApiError } from './api/client.js';
import { initTelegram, bindBackButton, openLink } from './telegram/webapp.js';
import { loadBoot, refreshBoot, state, toast } from './composables/useSession.js';

const route = useRoute();
const router = useRouter();
const isRoot = computed(() => ['/', '/campaigns'].includes(route.path));
const backCleanup = { fn: null };

async function handleStartParam() {
  const param = state.boot?.startParam;
  if (!param || !param.startsWith('join_')) return;
  const key = `ss-joined-${param}`;
  try {
    if (sessionStorage.getItem(key)) return;
  } catch {
    // приватный режим
  }
  const code = param.slice(5);
  try {
    const result = await call('joinCampaign', { code });
    try {
      sessionStorage.setItem(key, '1');
    } catch {
      // приватный режим
    }
    await refreshBoot();
    toast('Вы вступили в кампанию.');
    router.replace(`/c/${result.campaignId}`);
  } catch (error) {
    if (error instanceof ApiError && error.code !== 'ALREADY_MEMBER') {
      toast(error.message);
    }
  }
}

function updateBackButton() {
  if (backCleanup.fn) backCleanup.fn();
  backCleanup.fn = bindBackButton(!isRoot.value, () => router.back());
}

onMounted(async () => {
  initTelegram();
  updateBackButton();
  try {
    await loadBoot();
    await handleStartParam();
  } catch {
    // состояние ошибки показывает экран ниже
  }
});

watch(() => route.path, updateBackButton);
onUnmounted(() => {
  if (backCleanup.fn) backCleanup.fn();
});

async function retry() {
  try {
    await loadBoot(true);
    await handleStartParam();
  } catch {
    // ничего: ошибка останется на экране
  }
}
</script>

<template>
  <div class="app-shell">
    <header class="topbar">
      <button
        v-if="!isRoot"
        class="icon-btn"
        type="button"
        aria-label="Назад"
        @click="router.back()"
      >
        ←
      </button>
      <RouterLink to="/campaigns" class="brand" aria-label="Тайный Санта">
        <img :src="mark" alt="" class="brand__mark" width="36" height="36" />
        <span class="brand__name">Тайный Санта</span>
      </RouterLink>
      <div class="topbar__spacer" />
      <RouterLink to="/profile" class="icon-btn" aria-label="Профиль">Я</RouterLink>
    </header>

    <main v-if="state.loading" class="page" aria-busy="true">
      <div class="skeleton" style="height: 270px; border-radius: 28px" />
      <div class="skeleton" style="height: 24px; width: 60%" />
      <div class="skeleton" style="height: 14px; width: 90%" />
      <div class="skeleton" style="height: 120px" />
    </main>

    <main v-else-if="state.error" class="page">
      <div class="card stack">
        <h1 class="h2">Не удалось открыть приложение</h1>
        <p class="lede">{{ state.error }}</p>
        <button class="btn btn--primary" type="button" @click="retry">Повторить</button>
        <p class="hint">
          Если вы открыли страницу в браузере, а не в Telegram, войдите через кнопку в боте.
        </p>
      </div>
    </main>

    <main v-else>
      <RouterView />
    </main>

    <footer class="app-footer">
      <a
        class="app-footer__link"
        href="https://paShaman.dev"
        target="_blank"
        rel="noopener noreferrer"
        @click.prevent="openLink('https://paShaman.dev')"
      >
        Разработчик paShaman.dev
      </a>
    </footer>

    <div v-if="state.toast" class="toast" role="status" aria-live="polite">
      {{ state.toast }}
    </div>
  </div>
</template>
