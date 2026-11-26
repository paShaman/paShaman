<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { call } from '../api/client.js';
import { useCampaign } from '../composables/useCampaign.js';
import { toast } from '../composables/useSession.js';
import { state } from '../composables/useSession.js';
import CampaignStatus from '../components/campaign/CampaignStatus.vue';
import AppSheet from '../components/ui/AppSheet.vue';
import { shareLink } from '../telegram/webapp.js';

const route = useRoute();
const router = useRouter();
const { campaign, loading, error, busy, act } = useCampaign(route.params.id);
const rulesOpen = ref(false);
const leaving = ref(false);
const deliveryOpen = ref(false);
const avatars = reactive({});
const AVATAR_LIMIT = 50;
const logo = ref('');
const deliveryForm = reactive({
  method: 'hybrid',
  recipientName: '',
  address: '',
  phone: '',
  comment: '',
});

const me = computed(() => campaign.value?.me);
const deliveryRequired = computed(() => campaign.value?.deliveryMode === 'shipping');
const progress = computed(() => campaign.value?.progress ?? { total: 0, confirmed: 0 });
const percent = computed(() => {
  if (!progress.value.total) return 0;
  return Math.round((progress.value.confirmed / progress.value.total) * 100);
});
const hint = computed(() => {
  const code = campaign.value?.confirmationHint;
  return (
    {
      RULES_NOT_ACCEPTED: 'Примите правила, чтобы подтвердить участие.',
      WISHLIST_EMPTY: 'Добавьте хотя бы одно желание в вишлист.',
      DELIVERY_REQUIRED: 'Заполните адрес доставки и укажите почту или пункт выдачи.',
      CAMPAIGN_LOCKED: 'Набор уже закрыт.',
      NOT_MEMBER: 'Вы не участвуете в кампании.',
    }[code] ?? ''
  );
});

function formatDate(value) {
  if (!value) return '—';
  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  }).format(new Date(value));
}

function formatBudget(budget) {
  if (!budget) return '—';
  const formatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: budget.currency || 'RUB',
    maximumFractionDigits: 0,
  });
  const min = formatter.format(budget.min);
  return budget.max === budget.min ? min : `${min} — ${formatter.format(budget.max)}`;
}

function invite() {
  if (!campaign.value?.inviteUrl) {
    toast(`Код кампании: ${campaign.value?.code ?? '—'}`);
    return;
  }
  shareLink(campaign.value.inviteUrl, `Присоединяйтесь к «${campaign.value.title}» — Тайный Санта!`);
}

function avatarFor(member) {
  if (member.isMe && state.boot?.user?.photoUrl) return state.boot.user.photoUrl;
  return member.id != null ? avatars[member.id] || '' : '';
}

async function loadAvatars() {
  const members = campaign.value?.members ?? [];
  await Promise.all(
    members.slice(0, AVATAR_LIMIT).map(async (member) => {
      if (member.id == null || avatars[member.id] !== undefined) return;
      if (member.isMe && state.boot?.user?.photoUrl) return;
      avatars[member.id] = '';
      try {
        const result = await call('getMemberAvatar', {
          campaignId: Number(route.params.id),
          memberId: member.id,
        });
        avatars[member.id] = result?.photo ?? '';
      } catch {
        // без фото показываем инициал
      }
    }),
  );
}

async function loadLogo() {
  if (!campaign.value?.hasLogo) {
    logo.value = '';
    return;
  }
  try {
    const result = await call('getCampaignLogo', { campaignId: Number(route.params.id) });
    logo.value = result?.photo ?? '';
  } catch {
    logo.value = '';
  }
}

watch(campaign, (value) => {
  if (value) {
    loadAvatars();
    loadLogo();
  }
});

async function acceptRules() {
  await act('acceptRules', { rulesVersion: campaign.value.rulesVersion }, 'Правила приняты.');
}

async function confirmParticipation() {
  await act('confirmParticipation', {}, 'Участие подтверждено.');
}

async function leave() {
  if (!window.confirm('Выйти из кампании? Вернуться можно только пока идёт набор.')) return;
  leaving.value = true;
  try {
    await act('leaveCampaign', {}, 'Вы вышли из кампании.');
    router.replace('/campaigns');
  } catch {
    // ошибка показана тостом
  } finally {
    leaving.value = false;
  }
}

function openDelivery() {
  const saved = campaign.value?.myDelivery;
  Object.assign(deliveryForm, {
    method: saved?.method ?? campaign.value?.deliveryMode ?? 'hybrid',
    recipientName: saved?.recipientName ?? '',
    address: saved?.address ?? '',
    phone: saved?.phone ?? '',
    comment: saved?.comment ?? '',
  });
  deliveryOpen.value = true;
}

async function saveDelivery() {
  if (!deliveryForm.recipientName.trim()) {
    toast('Укажите, кому адресован подарок.');
    return;
  }
  try {
    await act(
      'updateDeliveryInfo',
      {
        method: deliveryForm.method,
        recipientName: deliveryForm.recipientName,
        address: deliveryForm.address,
        phone: deliveryForm.phone,
        comment: deliveryForm.comment,
      },
      'Данные доставки сохранены.',
    );
    deliveryOpen.value = false;
  } catch {
    // ошибка показана тостом
  }
}

const canOpenAssignment = computed(() => ['drawn', 'gifting', 'completed'].includes(campaign.value?.status));
const canOpenChat = computed(() => ['drawn', 'gifting'].includes(campaign.value?.status));
</script>

<template>
  <div class="page">
    <div v-if="loading" class="stack">
      <div class="skeleton" style="height: 24px; width: 45%" />
      <div class="skeleton" style="height: 44px; width: 80%" />
      <div class="skeleton" style="height: 140px" />
    </div>

    <div v-else-if="error" class="card stack">
      <h1 class="h2">Не удалось открыть кампанию</h1>
      <p class="lede">{{ error }}</p>
      <RouterLink to="/campaigns" class="btn btn--primary">Мои кампании</RouterLink>
    </div>

    <template v-else-if="campaign">
      <div class="stack">
        <div class="row row--between">
          <p class="eyebrow">Кампания</p>
          <div class="row">
            <span v-if="campaign.isOrganizer" class="pill">организатор</span>
            <CampaignStatus :status="campaign.status" />
          </div>
        </div>
        <img v-if="logo" :src="logo" alt="" class="campaign-logo" />
        <h1 class="display">{{ campaign.title }}</h1>
        <p v-if="campaign.description" class="lede">{{ campaign.description }}</p>
      </div>

      <div class="card stack">
        <div class="row row--between">
          <span class="muted small">Бюджет</span>
          <b>{{ formatBudget(campaign.budget) }}</b>
        </div>
        <div class="row row--between">
          <span class="muted small">Набор до</span>
          <b>{{ formatDate(campaign.registrationEndsAt) }}</b>
        </div>
        <div class="row row--between">
          <span class="muted small">Вручение</span>
          <b>{{ formatDate(campaign.giftDate) }}</b>
        </div>
        <div class="divider" />
        <div class="row row--between">
          <span class="muted small">Подтверждено</span>
          <b>{{ progress.confirmed }} из {{ progress.total }}</b>
        </div>
        <div class="bar" role="progressbar" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100">
          <span :style="{ width: `${percent}%` }" />
        </div>
      </div>

      <div v-if="campaign.status === 'recruiting'" class="card stack">
        <h2 class="h2">Пригласить друзей</h2>
        <p class="small muted">
          Отправьте ссылку или код — новый участник вступит в боте и появится в списке.
        </p>
        <p class="small">Код кампании: <b>{{ campaign.code }}</b></p>
        <button class="btn btn--secondary btn--block" type="button" @click="invite">
          Поделиться приглашением
        </button>
      </div>

      <div v-if="me && !me.confirmed" class="card stack">
        <h2 class="h2">Ваше участие</h2>
        <p class="small muted">
          Статус: {{ me.status === 'rules_accepted' ? 'правила приняты' : 'нужно подтвердить' }}.
          {{ hint }}
        </p>
        <button
          v-if="deliveryRequired"
          class="btn btn--secondary btn--block"
          type="button"
          :disabled="busy"
          @click="openDelivery"
        >
          {{ me.deliveryFilled ? 'Изменить адрес доставки' : 'Заполнить адрес доставки' }}
        </button>
        <button
          v-if="campaign.can.acceptRules"
          class="btn btn--primary btn--block"
          type="button"
          :disabled="busy"
          @click="acceptRules"
        >
          Принять правила
        </button>
        <button
          v-else
          class="btn btn--primary btn--block"
          type="button"
          :disabled="busy || !campaign.can.confirmParticipation"
          @click="confirmParticipation"
        >
          Участвую
        </button>
        <RouterLink :to="`/c/${campaign.id}/wishlist`" class="btn btn--secondary btn--block">
          {{ me.wishlistCount ? 'Мой вишлист' : 'Добавить желания' }}
          <span v-if="me.wishlistCount" class="pill">{{ me.wishlistCount }}</span>
        </RouterLink>
        <button
          v-if="campaign.can.leave"
          class="btn btn--ghost btn--block"
          type="button"
          :disabled="leaving"
          @click="leave"
        >
          Выйти из кампании
        </button>
      </div>

      <div v-else-if="me" class="card stack">
        <div class="row row--between">
          <h2 class="h2">Вы участвуете</h2>
          <span class="pill pill--ok">подтверждено</span>
        </div>
        <p class="small muted">Вишлист: {{ me.wishlistCount }} желаний.</p>
        <RouterLink :to="`/c/${campaign.id}/wishlist`" class="btn btn--secondary btn--block">
          Мой вишлист
        </RouterLink>
        <button
          v-if="!['completed', 'cancelled'].includes(campaign.status)"
          class="btn btn--secondary btn--block"
          type="button"
          @click="openDelivery"
        >
          {{ me.deliveryFilled ? 'Изменить данные доставки' : 'Указать данные доставки' }}
        </button>
        <RouterLink
          v-if="canOpenAssignment"
          :to="`/c/${campaign.id}/assignment`"
          class="btn btn--primary btn--block"
        >
          Мой получатель
        </RouterLink>
        <div v-if="canOpenChat" class="row">
          <RouterLink :to="`/c/${campaign.id}/chat`" class="btn btn--secondary" style="flex: 1">
            Чат
          </RouterLink>
          <RouterLink :to="`/c/${campaign.id}/gift`" class="btn btn--secondary" style="flex: 1">
            Подарок
          </RouterLink>
        </div>
      </div>

      <div class="card card--flat stack">
        <button class="row row--between rules-toggle" type="button" @click="rulesOpen = !rulesOpen">
          <span class="h2">Правила кампании</span>
          <span aria-hidden="true">{{ rulesOpen ? '−' : '+' }}</span>
        </button>
        <p v-if="rulesOpen" class="small muted" style="white-space: pre-wrap">{{ campaign.rules }}</p>
      </div>

      <div class="stack">
        <div class="section-title">
          <h2>Участники</h2>
          <span class="pill">{{ progress.total }}</span>
        </div>
        <div class="list">
          <div v-for="(member, index) in campaign.members" :key="`${member.name}-${index}`" class="tile">
            <span class="avatar" aria-hidden="true">
              <img v-if="avatarFor(member)" :src="avatarFor(member)" :alt="member.name" />
              <template v-else>{{ member.name.slice(0, 1) }}</template>
            </span>
            <span class="tile__body">
              <span class="tile__title">{{ member.name }}<template v-if="member.isMe"> (вы)</template></span>
              <span class="tile__meta">{{ member.role === 'organizer' ? 'Организатор' : 'Участник' }}</span>
            </span>
            <span class="pill" :class="member.confirmed ? 'pill--ok' : ''">
              {{ member.confirmed ? 'готов' : 'ждём' }}
            </span>
          </div>
        </div>
      </div>

      <p v-if="state.boot?.user?.isSuperAdmin" class="hint">
        Вы супер-админ: управление кампанией доступно в боте.
      </p>

      <AppSheet :open="deliveryOpen" title="Доставка подарка" @close="deliveryOpen = false">
        <form class="stack" @submit.prevent="saveDelivery">
          <div v-if="campaign.deliveryMode === 'shipping'" class="row row--between">
            <span class="muted small">Передача</span>
            <b>Доставка</b>
          </div>
          <div v-else class="field">
            <label for="delivery-method">Как передаём подарок</label>
            <select id="delivery-method" v-model="deliveryForm.method" class="input">
              <option value="meetup">Встреча</option>
              <option value="shipping">Доставка</option>
              <option value="hybrid">Встреча или доставка</option>
            </select>
          </div>
          <div class="field">
            <label for="delivery-name">Кому адресован подарок *</label>
            <input
              id="delivery-name"
              v-model="deliveryForm.recipientName"
              class="input"
              maxlength="120"
              required
            />
          </div>
          <div class="field">
            <label for="delivery-address">Адрес или пункт самовывоза</label>
            <textarea
              id="delivery-address"
              v-model="deliveryForm.address"
              class="input"
              maxlength="500"
              placeholder="Город, улица, дом, квартира, индекс — или адрес отделения почты / ПВЗ"
            />
            <p class="hint">
              Укажите отделение почты или пункт выдачи. Для доставки адрес обязателен, его видит
              только ваш Санта.
            </p>
          </div>
          <div class="field">
            <label for="delivery-phone">Телефон</label>
            <input
              id="delivery-phone"
              v-model="deliveryForm.phone"
              class="input"
              maxlength="40"
              inputmode="tel"
            />
          </div>
          <div class="field">
            <label for="delivery-comment">Комментарий</label>
            <textarea id="delivery-comment" v-model="deliveryForm.comment" class="input" maxlength="300" />
          </div>
          <button class="btn btn--primary" type="submit" :disabled="busy">
            {{ busy ? 'Сохраняем…' : 'Сохранить' }}
          </button>
        </form>
      </AppSheet>
    </template>
  </div>
</template>

<style scoped>
.campaign-logo {
  width: 64px;
  height: 64px;
  border-radius: 18px;
  object-fit: cover;
}
.bar {
  height: 8px;
  background: var(--paper-sunken);
  border-radius: 999px;
  overflow: hidden;
}
.bar span {
  display: block;
  height: 100%;
  background: linear-gradient(90deg, var(--pine-500), var(--pine-700));
  border-radius: inherit;
  transition: width 300ms cubic-bezier(0.22, 1, 0.36, 1);
}
.rules-toggle {
  width: 100%;
  border: 0;
  background: transparent;
  color: inherit;
  padding: 0;
  text-align: left;
}
</style>
