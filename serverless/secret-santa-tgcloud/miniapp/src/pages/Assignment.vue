<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { call } from '../api/client.js';
import { toast } from '../composables/useSession.js';
import { haptic, openLink } from '../telegram/webapp.js';
import AppIcon from '../components/ui/AppIcon.vue';

const route = useRoute();
const campaignId = Number(route.params.id);
const data = ref(null);
const loading = ref(true);
const error = ref('');
const revealed = ref(false);
const photos = reactive({});
const busyItem = ref(null);

function formatPrice(item) {
  if (item.priceMin == null && item.priceMax == null) return '';
  const formatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: item.currency || 'RUB',
    maximumFractionDigits: 0,
  });
  if (item.priceMin != null && item.priceMax != null && item.priceMin !== item.priceMax) {
    return `${formatter.format(item.priceMin)} — ${formatter.format(item.priceMax)}`;
  }
  return formatter.format(item.priceMin ?? item.priceMax);
}

async function load() {
  loading.value = true;
  error.value = '';
  try {
    data.value = await call('getAssignment', { campaignId });
    data.value.wishlist?.forEach((item) => {
      if (item.hasPhoto) loadPhoto(item);
    });
    setTimeout(() => {
      revealed.value = true;
      haptic('soft');
    }, 140);
  } catch (e) {
    error.value = e.message || 'Не удалось открыть получателя.';
  } finally {
    loading.value = false;
  }
}

async function loadPhoto(item) {
  if (photos[item.id]) return;
  try {
    const result = await call('getItemPhoto', { campaignId, itemId: item.id });
    photos[item.id] = result.dataUrl;
  } catch {
    // фото временно недоступно
  }
}

async function toggleClaim(item) {
  if (busyItem.value) return;
  busyItem.value = item.id;
  const previous = item.claimed;
  item.claimed = !previous;
  haptic('light');
  try {
    await call('setItemClaimed', {
      campaignId,
      itemId: item.id,
      claimed: item.claimed,
    });
  } catch (e) {
    item.claimed = previous;
    toast(e.message);
  } finally {
    busyItem.value = null;
  }
}

onMounted(load);
</script>

<template>
  <div class="page">
    <div v-if="loading" class="stack">
      <div class="skeleton" style="height: 200px; border-radius: 26px" />
      <div class="skeleton" style="height: 22px; width: 55%" />
      <div class="skeleton" style="height: 120px" />
    </div>

    <div v-else-if="error" class="card stack">
      <h1 class="h2">Пока не готово</h1>
      <p class="lede">{{ error }}</p>
      <RouterLink :to="`/c/${campaignId}`" class="btn btn--secondary">Вернуться в кампанию</RouterLink>
    </div>

    <template v-else-if="data">
      <div class="reveal" :class="{ 'reveal--open': revealed }">
        <p class="eyebrow" style="color: rgba(255, 248, 240, 0.78)">Ваш получатель</p>
        <h1 class="display" style="color: #fff8f0">{{ data.receiver.name }}</h1>
        <p class="small" style="color: rgba(255, 248, 240, 0.86); margin-top: 6px">
          Подарите что-то от души. Бюджет и правила — в кампании.
        </p>
      </div>

      <div class="card stack">
        <div class="row row--between">
          <h2 class="h2">Вишлист</h2>
          <span class="pill">{{ data.wishlist.length }}</span>
        </div>

        <div v-if="data.wishlist.length" class="list">
          <div v-for="item in data.wishlist" :key="item.id" class="gift">
            <div class="gift__photo">
              <img v-if="photos[item.id]" :src="photos[item.id]" :alt="item.title" />
              <AppIcon v-else name="image" :size="22" :stroke-width="1.6" />
            </div>
            <div class="gift__body">
              <p class="gift__title">{{ item.title }}</p>
              <p v-if="formatPrice(item)" class="small muted">{{ formatPrice(item) }}</p>
              <p v-if="item.comment" class="small muted">{{ item.comment }}</p>
              <a
                v-if="item.url"
                :href="item.url"
                target="_blank"
                rel="noopener noreferrer"
                class="small"
                @click.prevent="openLink(item.url)"
              >
                Открыть ссылку
              </a>
            </div>
            <button
              class="claim"
              :class="{ 'claim--on': item.claimed }"
              type="button"
              :aria-pressed="item.claimed"
              :disabled="busyItem === item.id"
              :title="item.claimed ? 'Снять отметку' : 'Отметить как купленное'"
              @click="toggleClaim(item)"
            >
              <AppIcon v-if="item.claimed" name="check" :size="20" :stroke-width="2.6" />
            </button>
          </div>
        </div>
        <p v-else class="small muted">Получатель пока не добавил желания. Можно написать в чат и спросить.</p>
      </div>

      <div v-if="data.delivery?.method === 'shipping'" class="card stack">
        <h2 class="h2">Куда отправлять</h2>
        <div v-if="data.delivery.recipientName" class="row row--between">
          <span class="muted small">Кому</span>
          <b>{{ data.delivery.recipientName }}</b>
        </div>
        <div v-if="data.delivery.address" class="stack">
          <span class="muted small">Адрес</span>
          <p style="white-space: pre-wrap">{{ data.delivery.address }}</p>
        </div>
        <div v-if="data.delivery.phone" class="row row--between">
          <span class="muted small">Телефон</span>
          <b>{{ data.delivery.phone }}</b>
        </div>
        <p v-if="data.delivery.comment" class="small muted">{{ data.delivery.comment }}</p>
      </div>
      <div v-else-if="data.delivery" class="card stack">
        <h2 class="h2">Как передать подарок</h2>
        <p class="small muted">
          Получатель выбрал встречу. Договоритесь о времени и месте в чате.
        </p>
        <p v-if="data.delivery.comment" class="small muted">{{ data.delivery.comment }}</p>
      </div>
      <div v-else class="card card--flat stack">
        <p class="small muted">
          Получатель ещё не заполнил адрес. Если нужна доставка, напишите ему в чат.
        </p>
      </div>

      <div class="row">
        <RouterLink
          v-if="data.chatAvailable"
          :to="`/c/${campaignId}/chat?role=santa`"
          class="btn btn--secondary"
          style="flex: 1"
        >
          Чат
        </RouterLink>
        <RouterLink :to="`/c/${campaignId}/gift`" class="btn btn--secondary" style="flex: 1">
          Статус подарка
        </RouterLink>
      </div>
    </template>
  </div>
</template>

<style scoped>
.reveal {
  position: relative;
  overflow: hidden;
  border-radius: 26px;
  padding: 24px;
  min-height: 180px;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  background:
    radial-gradient(circle at 78% 18%, rgba(242, 216, 168, 0.55), transparent 42%),
    linear-gradient(145deg, #536f6b, #2e4a44 62%, #72333e);
  transform: translateY(8px) scale(0.985);
  opacity: 0;
  transition:
    transform 420ms cubic-bezier(0.22, 1, 0.36, 1),
    opacity 320ms ease;
}
.reveal--open {
  transform: none;
  opacity: 1;
}
.reveal::after {
  content: '';
  position: absolute;
  inset: 12px;
  border: 1px solid rgba(255, 248, 240, 0.22);
  border-radius: 18px;
  pointer-events: none;
}
.gift {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px;
  border-radius: 16px;
  background: var(--paper-sunken);
}
.gift__photo {
  width: 52px;
  height: 52px;
  border-radius: 12px;
  background: var(--paper);
  display: grid;
  place-items: center;
  overflow: hidden;
  flex: 0 0 auto;
}
.gift__photo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.gift__body {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.gift__title {
  margin: 0;
  font-weight: 700;
  font-size: 15px;
}
.claim {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  border: 1px solid var(--line);
  background: var(--paper);
  color: var(--pine-900);
  display: grid;
  place-items: center;
  flex: 0 0 auto;
}
.claim--on {
  background: var(--pine-700);
  border-color: var(--pine-700);
  color: #fff8f0;
}
@media (prefers-reduced-motion: reduce) {
  .reveal {
    transition: none;
    transform: none;
    opacity: 1;
  }
}
</style>
