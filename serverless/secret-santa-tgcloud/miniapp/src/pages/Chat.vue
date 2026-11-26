<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { call } from '../api/client.js';
import { toast } from '../composables/useSession.js';
import AppIcon from '../components/ui/AppIcon.vue';
import { useUnread } from '../composables/useUnread.js';

const route = useRoute();
const campaignId = Number(route.params.id);
const { unread, refresh: refreshUnread } = useUnread(campaignId);
const messages = ref([]);
const roles = ref([]);
const role = ref(route.query.role === 'santa' ? 'santa' : 'recipient');
const chatBlocked = ref(false);
const campaignStatus = ref('');
const loading = ref(true);
const error = ref('');
const text = ref('');
const sending = ref(false);
const listRef = ref(null);
let timer = null;
let switched = false;

const title = computed(() => (role.value === 'santa' ? 'Чат с получателем' : 'Санта на связи'));
const placeholder = computed(() =>
  role.value === 'santa' ? 'Написать получателю…' : 'Написать Санте…',
);
const roleLabel = (value) => (value === 'recipient' ? 'Мой Санта' : 'Мой получатель');

function scrollBottom() {
  nextTick(() => {
    if (listRef.value) listRef.value.scrollTop = listRef.value.scrollHeight;
  });
}

function mergeMessages(incoming) {
  const map = new Map(messages.value.map((message) => [message.id, message]));
  for (const message of incoming) map.set(message.id, message);
  messages.value = [...map.values()].sort((a, b) => a.id - b.id);
}

async function load(silent = false) {
  if (!silent) loading.value = true;
  try {
    const lastSeen = messages.value.length ? messages.value[messages.value.length - 1].id : null;
    const result = await call('getThread', {
      campaignId,
      role: role.value,
      markRead: typeof document !== 'undefined' && document.visibilityState === 'visible',
      ...(lastSeen ? { upToId: lastSeen } : {}),
    });
    roles.value = result.roles ?? [];
    if (!roles.value.includes(role.value) && roles.value.length && !switched) {
      switched = true;
      role.value = roles.value[0];
      return load(silent);
    }
    mergeMessages(result.messages ?? []);
    chatBlocked.value = Boolean(result.chatBlocked);
    campaignStatus.value = result.campaignStatus;
    error.value = '';
    if (!silent) {
      scrollBottom();
      refreshUnread();
    }
  } catch (e) {
    if (!silent) error.value = e.message || 'Не удалось открыть чат.';
  } finally {
    if (!silent) loading.value = false;
  }
}

function switchRole(next) {
  if (next === role.value) return;
  role.value = next;
  messages.value = [];
  text.value = '';
  error.value = '';
  load();
}

async function send() {
  const body = text.value.trim();
  if (!body || sending.value) return;
  sending.value = true;
  try {
    const id =
      typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    const result = await call('sendChatMessage', {
      campaignId,
      role: role.value,
      text: body,
      clientMsgId: id,
    });
    text.value = '';
    mergeMessages([result.message]);
    scrollBottom();
  } catch (e) {
    toast(e.message);
  } finally {
    sending.value = false;
  }
}

async function report(message) {
  if (message.reported) {
    toast('Жалоба уже отправлена.');
    return;
  }
  if (!window.confirm('Пожаловаться на сообщение организатору?')) return;
  try {
    await call('reportMessage', {
      campaignId,
      role: role.value,
      messageId: message.id,
      reason: 'Жалоба из чата',
    });
    message.reported = true;
    toast('Жалоба отправлена. Организатор разберётся.');
  } catch (e) {
    toast(e.message);
  }
}

function time(value) {
  if (!value) return '';
  return new Intl.DateTimeFormat('ru-RU', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}

onMounted(async () => {
  await load();
  timer = setInterval(() => {
    if (document.visibilityState === 'visible') load(true);
  }, 6000);
});

onUnmounted(() => {
  if (timer) clearInterval(timer);
});
</script>

<template>
  <div class="page chat-page">
    <div>
      <p class="eyebrow">Анонимный чат</p>
      <h1 class="display" style="font-size: clamp(23px, 6vw, 28px)">{{ title }}</h1>
      <p class="small muted" style="margin-top: 6px">
        Не раскрывайте себя до завершения кампании. Организатор видит только жалобы.
      </p>
    </div>

    <div v-if="roles.length > 1" class="role-tabs" role="tablist" aria-label="Кому пишем">
      <button
        v-for="item in roles"
        :key="item"
        class="role-tabs__item"
        :class="{ 'role-tabs__item--active': item === role }"
        type="button"
        role="tab"
        :aria-selected="item === role"
        @click="switchRole(item)"
      >
        {{ roleLabel(item) }}
        <span v-if="unread[item] > 0" class="role-tabs__badge" aria-hidden="true">
          {{ unread[item] > 99 ? '99+' : unread[item] }}
        </span>
      </button>
    </div>

    <div v-if="loading" class="stack">
      <div class="skeleton" style="height: 64px; width: 75%" />
      <div class="skeleton" style="height: 64px; width: 60%; margin-left: auto" />
      <div class="skeleton" style="height: 64px; width: 68%" />
    </div>

    <div v-else-if="error" class="card stack">
      <p class="lede">{{ error }}</p>
      <button class="btn btn--primary" type="button" @click="load()">Повторить</button>
    </div>

    <template v-else>
      <div ref="listRef" class="thread" role="log" aria-live="polite">
        <div v-if="messages.length === 0" class="empty">
          <p class="small">Сообщений пока нет. Напишите первым — это анонимно.</p>
        </div>
        <div
          v-for="message in messages"
          :key="message.id"
          class="bubble"
          :class="{ 'bubble--mine': message.mine }"
        >
          <p class="bubble__text">{{ message.text }}</p>
          <div class="bubble__meta">
            <span>{{ time(message.createdAt) }}</span>
            <button
              v-if="!message.mine"
              class="bubble__report"
              type="button"
              :aria-label="'Пожаловаться на сообщение'"
              @click="report(message)"
            >
              <AppIcon name="flag" :size="11" :stroke-width="2.4" />
            </button>
          </div>
        </div>
      </div>

      <p v-if="chatBlocked" class="error" role="status">
        Переписка остановлена организатором. История доступна только для чтения.
      </p>
      <p v-else-if="campaignStatus === 'completed'" class="hint">
        Кампания завершена, переписка доступна только для чтения.
      </p>

      <form class="composer" @submit.prevent="send">
        <label class="sr-only" for="message">Сообщение</label>
        <textarea
          id="message"
          v-model="text"
          class="input composer__input"
          rows="1"
          maxlength="1000"
          :disabled="chatBlocked || campaignStatus === 'completed'"
          :placeholder="placeholder"
          @keydown.enter.exact.prevent="send"
        />
        <button
          class="btn btn--primary composer__send"
          type="submit"
          :disabled="sending || chatBlocked || campaignStatus === 'completed' || !text.trim()"
        >
          <AppIcon name="arrow-up" :size="20" :stroke-width="2.2" />
        </button>
      </form>
    </template>
  </div>
</template>

<style scoped>
.role-tabs {
  display: flex;
  gap: 6px;
  padding: 4px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: var(--paper-raised);
}
.role-tabs__item {
  flex: 1;
  min-height: 38px;
  border: 0;
  border-radius: 10px;
  background: transparent;
  color: var(--ink-soft);
  font-size: 14px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}
.role-tabs__item--active {
  background: var(--pine-700);
  color: #fff8f0;
}
.role-tabs__badge {
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 999px;
  background: var(--berry-600);
  color: #fff8f0;
  font-size: 11px;
  font-weight: 800;
  line-height: 1;
  display: inline-grid;
  place-items: center;
}
.chat-page {
  min-height: calc(100dvh - 90px);
}
.thread {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-height: 240px;
  max-height: 52dvh;
  overflow: auto;
  padding: 4px 2px;
  scroll-behavior: smooth;
}
.bubble {
  max-width: 82%;
  align-self: flex-start;
  background: var(--paper-raised);
  border: 1px solid var(--line);
  border-radius: 18px 18px 18px 6px;
  padding: 10px 12px;
  box-shadow: 0 4px 16px -14px var(--shadow);
}
.bubble--mine {
  align-self: flex-end;
  background: var(--pine-700);
  border-color: var(--pine-700);
  color: #fff8f0;
  border-radius: 18px 18px 6px 18px;
}
.bubble__text {
  white-space: pre-wrap;
  word-break: break-word;
  font-size: 15px;
  line-height: 1.45;
}
.bubble__meta {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 4px;
  font-size: 11px;
  opacity: 0.72;
}
.bubble__report {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  border: 1px solid currentColor;
  background: transparent;
  color: inherit;
  display: grid;
  place-items: center;
  padding: 0;
}
.composer {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  padding: 8px;
  border: 1px solid var(--line);
  border-radius: 20px;
  background: var(--paper-raised);
  position: sticky;
  bottom: calc(8px + var(--safe-bottom));
}
.composer__input {
  min-height: 42px;
  max-height: 120px;
  border: 0;
  background: transparent;
  resize: none;
  padding: 9px 8px;
}
.composer__send {
  width: 44px;
  min-height: 44px;
  padding: 0;
  border-radius: 14px;
  flex: 0 0 auto;
}
</style>
