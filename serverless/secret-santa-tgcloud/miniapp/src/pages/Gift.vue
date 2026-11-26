<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { call } from '../api/client.js';
import { toast } from '../composables/useSession.js';
import AppIcon from '../components/ui/AppIcon.vue';

const route = useRoute();
const campaignId = Number(route.params.id);
const data = ref(null);
const loading = ref(true);
const error = ref('');
const busy = ref(false);

const steps = computed(() => {
  const status = data.value?.giftStatus ?? 'pending';
  return [
    { key: 'pending', label: 'Выбираем подарок', done: true },
    { key: 'sent', label: 'Подарок отправлен', done: ['sent', 'received'].includes(status) },
    { key: 'received', label: 'Подарок получен', done: status === 'received' },
  ];
});

const title = computed(() => {
  const status = data.value?.giftStatus ?? 'pending';
  if (status === 'received') return 'Подарок получен';
  if (status === 'sent') return 'Подарок в пути';
  return data.value?.role === 'recipient' ? 'Ждём подарок' : 'Выбираем подарок';
});

async function load() {
  loading.value = true;
  error.value = '';
  try {
    data.value = await call('getGiftStatus', { campaignId });
  } catch (e) {
    error.value = e.message || 'Не удалось открыть статус подарка.';
  } finally {
    loading.value = false;
  }
}

async function mark(status) {
  busy.value = true;
  try {
    await call('markGiftStatus', { campaignId, status });
    toast(status === 'sent' ? 'Отправка отмечена.' : 'Получение отмечено.');
    await load();
  } catch (e) {
    toast(e.message);
  } finally {
    busy.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div class="page">
    <div>
      <p class="eyebrow">Подарок</p>
      <h1 class="display" style="font-size: clamp(24px, 6vw, 30px)">
        {{ title }}
      </h1>
    </div>

    <div v-if="loading" class="stack">
      <div class="skeleton" style="height: 54px" />
      <div class="skeleton" style="height: 54px" />
      <div class="skeleton" style="height: 54px" />
    </div>

    <div v-else-if="error" class="card stack">
      <p class="lede">{{ error }}</p>
      <button class="btn btn--primary" type="button" @click="load">Повторить</button>
    </div>

    <template v-else-if="data">
      <div class="card stack">
        <div class="row row--between">
          <span class="muted small">Кампания</span>
          <b>{{ data.campaign.title }}</b>
        </div>
        <div v-if="data.partnerName" class="row row--between">
          <span class="muted small">
            {{ data.role === 'santa' ? 'Получатель' : 'Санта' }}
          </span>
          <b>{{ data.partnerName }}</b>
        </div>
        <div class="divider" />
        <ol class="steps">
          <li v-for="step in steps" :key="step.key" class="step" :class="{ 'step--done': step.done }">
            <span class="step__dot" aria-hidden="true">
              <AppIcon v-if="step.done" name="check" :size="13" :stroke-width="2.6" />
            </span>
            <span>{{ step.label }}</span>
          </li>
        </ol>
      </div>

      <div v-if="data.role === 'santa'" class="card stack">
        <h2 class="h2">Отметить отправку</h2>
        <p class="small muted">
          Нажмите, когда передадите подарок или отправите его доставкой. Получатель увидит
          уведомление, но не ваше имя.
        </p>
        <button
          class="btn btn--primary btn--block"
          type="button"
          :disabled="busy || data.giftStatus !== 'pending'"
          @click="mark('sent')"
        >
          {{ data.giftStatus === 'pending' ? 'Я отправил(а) подарок' : 'Отправка отмечена' }}
        </button>
      </div>

      <div v-else class="card stack">
        <h2 class="h2">Подарок пришёл?</h2>
        <p class="small muted">
          Отметьте получение, чтобы Санта знал, что всё дошло. Это можно сделать, даже если
          Санта забыл отметить отправку.
        </p>
        <button
          class="btn btn--primary btn--block"
          type="button"
          :disabled="busy || data.giftStatus === 'received'"
          @click="mark('received')"
        >
          {{ data.giftStatus === 'received' ? 'Получение отмечено' : 'Я получил(а) подарок' }}
        </button>
      </div>

      <div class="row">
        <RouterLink
          v-if="!data.chatBlocked && data.campaignStatus !== 'completed'"
          :to="`/c/${campaignId}/chat`"
          class="btn btn--secondary"
          style="flex: 1"
        >
          Чат
        </RouterLink>
        <RouterLink :to="`/c/${campaignId}`" class="btn btn--secondary" style="flex: 1">
          В кампанию
        </RouterLink>
      </div>

      <p v-if="data.campaignStatus === 'completed'" class="hint">
        Кампания завершена. Если Санта разрешил раскрытие, его имя появилось в карточке.
      </p>
    </template>
  </div>
</template>

<style scoped>
.steps {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.step {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--ink-soft);
  font-size: 14px;
}
.step--done {
  color: var(--ink);
  font-weight: 700;
}
.step__dot {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: 1px solid var(--line);
  display: grid;
  place-items: center;
  font-size: 12px;
  background: var(--paper-sunken);
  flex: 0 0 auto;
}
.step--done .step__dot {
  background: var(--pine-700);
  border-color: var(--pine-700);
  color: #fff8f0;
}
</style>
