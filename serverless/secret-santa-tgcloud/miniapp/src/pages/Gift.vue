<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { call } from '../api/client.js';
import { toast } from '../composables/useSession.js';
import AppIcon from '../components/ui/AppIcon.vue';
import ChatButton from '../components/ui/ChatButton.vue';
import { useUnread } from '../composables/useUnread.js';

const route = useRoute();
const campaignId = Number(route.params.id);
const { unread } = useUnread(campaignId);
const data = ref(null);
const loading = ref(true);
const error = ref('');
const busy = ref(false);

const santaSide = computed(() => data.value?.santa ?? null);
const recipientSide = computed(() => data.value?.recipient ?? null);
const chatAllowed = computed(() =>
  ['drawn', 'gifting'].includes(data.value?.campaignStatus ?? ''),
);

const title = computed(() => {
  const recipient = recipientSide.value;
  const santa = santaSide.value;
  const shippedRecipient = recipient?.deliveryMethod === 'shipping' ? recipient : null;
  const shippedSanta = santa?.deliveryMethod === 'shipping' ? santa : null;
  const tracked = shippedRecipient ?? shippedSanta;
  if (!tracked) return 'Подарки при встрече';
  if (tracked === shippedRecipient) {
    if (tracked.giftStatus === 'received') return 'Подарок получен';
    if (tracked.giftStatus === 'sent') return 'Подарок в пути';
    return 'Ждём подарок';
  }
  if (tracked.giftStatus === 'received') return 'Подарок доставлен';
  if (tracked.giftStatus === 'sent') return 'Подарок в пути';
  return 'Выбираем подарок';
});

function isShipping(side) {
  return side?.deliveryMethod === 'shipping';
}

function stepsFor(side) {
  const status = side?.giftStatus ?? 'pending';
  return [
    {
      key: 'pending',
      label: side?.role === 'recipient' ? 'Ждём отправки' : 'Выбираем подарок',
      done: true,
    },
    { key: 'sent', label: 'Подарок отправлен', done: ['sent', 'received'].includes(status) },
    { key: 'received', label: 'Подарок получен', done: status === 'received' },
  ];
}

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

async function mark(status, role) {
  const question =
    status === 'sent'
      ? 'Подтвердить отправку подарка? Получатель получит уведомление и сообщение в чате.'
      : 'Подтвердить получение подарка? Отменить это действие нельзя.';
  if (!window.confirm(question)) return;
  busy.value = true;
  try {
    await call('markGiftStatus', { campaignId, status, role });
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
        <p v-if="data.campaignStatus === 'completed'" class="hint">
          Кампания завершена. Если Санта разрешил раскрытие, его имя появилось в карточке.
        </p>
      </div>

      <section v-if="recipientSide" class="card stack">
        <div class="row row--between">
          <h2 class="h2">Мой подарок</h2>
          <span class="eyebrow">Мне</span>
        </div>
        <div class="row row--between">
          <span class="muted small">Санта</span>
          <b>{{ recipientSide.partnerName || 'Аноним' }}</b>
        </div>
        <template v-if="isShipping(recipientSide)">
          <div class="divider" />
          <ol class="steps">
            <li
              v-for="step in stepsFor(recipientSide)"
              :key="step.key"
              class="step"
              :class="{ 'step--done': step.done }"
            >
              <span class="step__dot" aria-hidden="true">
                <AppIcon v-if="step.done" name="check" :size="13" :stroke-width="2.6" />
              </span>
              <span>{{ step.label }}</span>
            </li>
          </ol>
          <p class="small muted">
            Отметьте получение, чтобы Санта знал, что всё дошло. Это можно сделать, даже если
            Санта забыл отметить отправку.
          </p>
          <button
            class="btn btn--primary btn--block"
            type="button"
            :disabled="busy || recipientSide.giftStatus === 'received'"
            @click="mark('received', 'recipient')"
          >
            {{
              recipientSide.giftStatus === 'received'
                ? 'Получение отмечено'
                : 'Я получил(а) подарок'
            }}
          </button>
        </template>
        <p v-else class="small muted">
          Подарок передаётся лично — статусы не нужны. Договоритесь о встрече в чате.
        </p>
        <ChatButton
          v-if="chatAllowed && !recipientSide.chatBlocked"
          :to="`/c/${campaignId}/chat?role=recipient`"
          label="Чат с Сантой"
          :unread="unread.recipient"
          block
        />
      </section>

      <section v-if="santaSide" class="card stack">
        <div class="row row--between">
          <h2 class="h2">Подарок получателю</h2>
          <span class="eyebrow">От меня</span>
        </div>
        <div v-if="santaSide.partnerName" class="row row--between">
          <span class="muted small">Получатель</span>
          <b>{{ santaSide.partnerName }}</b>
        </div>
        <template v-if="isShipping(santaSide)">
          <div class="divider" />
          <ol class="steps">
            <li
              v-for="step in stepsFor(santaSide)"
              :key="step.key"
              class="step"
              :class="{ 'step--done': step.done }"
            >
              <span class="step__dot" aria-hidden="true">
                <AppIcon v-if="step.done" name="check" :size="13" :stroke-width="2.6" />
              </span>
              <span>{{ step.label }}</span>
            </li>
          </ol>
          <p class="small muted">
            Нажмите, когда отправите подарок доставкой. Получатель увидит уведомление, но не
            ваше имя.
          </p>
          <button
            class="btn btn--primary btn--block"
            type="button"
            :disabled="busy || santaSide.giftStatus !== 'pending'"
            @click="mark('sent', 'santa')"
          >
            {{
              santaSide.giftStatus === 'pending' ? 'Я отправил(а) подарок' : 'Отправка отмечена'
            }}
          </button>
        </template>
        <p v-else class="small muted">
          Подарок передаётся лично — статусы не нужны. Договоритесь о встрече в чате.
        </p>
        <ChatButton
          v-if="chatAllowed && !santaSide.chatBlocked"
          :to="`/c/${campaignId}/chat?role=santa`"
          label="Чат с получателем"
          :unread="unread.santa"
          block
        />
      </section>

      <RouterLink :to="`/c/${campaignId}`" class="btn btn--secondary btn--block">
        В кампанию
      </RouterLink>
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
