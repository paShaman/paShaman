<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import tree from '../assets/tree-192.png';
import { call, ApiError } from '../api/client.js';
import { state, toast } from '../composables/useSession.js';
import { useCampaign } from '../composables/useCampaign.js';
import { openLink } from '../telegram/webapp.js';
import AppSheet from '../components/ui/AppSheet.vue';
import AppIcon from '../components/ui/AppIcon.vue';
import { pluralRu } from '../utils/plural.js';

const route = useRoute();
const { campaign, loading: campaignLoading } = useCampaign(route.params.id);
const items = ref([]);
const loading = ref(true);
const error = ref('');
const busy = ref(false);
const editorOpen = ref(false);
const editingId = ref(null);
const MAX_URL_LENGTH = 2048;
const form = reactive({ title: '', url: '', price: '', comment: '' });
const fileInput = ref(null);
const photoTarget = ref(null);
const photoBusy = ref(false);
const photos = reactive({});

const canEdit = computed(() => Boolean(campaign.value?.can?.editWishlist));
const limit = computed(() => state.boot?.limits?.wishlistItems ?? 50);
const photoMax = computed(() => state.boot?.photoMaxBytes ?? 320 * 1024);
const budget = computed(() => campaign.value?.budget ?? null);
const budgetLabel = computed(() => {
  const value = budget.value;
  if (!value) return '';
  const formatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: value.currency || 'RUB',
    maximumFractionDigits: 0,
  });
  const min = formatter.format(value.min);
  return value.min === value.max ? min : `${min} — ${formatter.format(value.max)}`;
});
const pricePlaceholder = computed(() => (budget.value ? String(budget.value.min) : 'например 2000'));

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const result = await call('getMyWishlist', { campaignId: Number(route.params.id) });
    items.value = result.items ?? [];
    result.items?.forEach((item) => {
      if (item.hasPhoto) loadPhoto(item);
    });
  } catch (e) {
    error.value = e.message || 'Не удалось загрузить вишлист.';
  } finally {
    loading.value = false;
  }
}

function openNew() {
  editingId.value = null;
  Object.assign(form, { title: '', url: '', price: '', comment: '' });
  editorOpen.value = true;
}

function openEdit(item) {
  editingId.value = item.id;
  Object.assign(form, {
    title: item.title,
    url: item.url ?? '',
    price: item.priceMin ?? item.priceMax ?? '',
    comment: item.comment ?? '',
  });
  editorOpen.value = true;
}

async function save() {
  if (!form.title.trim()) {
    toast('Название не может быть пустым.');
    return;
  }
  const url = form.url.replace(/[\u200B-\u200D\uFEFF]/g, '').trim();
  if (url && !(/^https?:\/\/\S+$/i.test(url) && url.length <= MAX_URL_LENGTH)) {
    toast(`Ссылка должна начинаться с http:// или https:// и быть короче ${MAX_URL_LENGTH} символов.`);
    return;
  }
  const rawPrice = String(form.price).trim();
  const price = rawPrice === '' ? null : Number(rawPrice);
  if (price !== null && (!Number.isInteger(price) || price < 0 || price > 1000000)) {
    toast('Цена — целое число от 0 до 1 000 000 ₽.');
    return;
  }
  if (price !== null && budget.value && (price < budget.value.min || price > budget.value.max)) {
    toast(`Цена должна быть в бюджете кампании: ${budgetLabel.value}.`);
    return;
  }
  busy.value = true;
  try {
    await call('saveWishlistItem', {
      campaignId: Number(route.params.id),
      itemId: editingId.value || undefined,
      title: form.title,
      url,
      priceMin: price,
      priceMax: price,
      comment: form.comment,
    });
    editorOpen.value = false;
    toast(editingId.value ? 'Желание обновлено.' : 'Желание добавлено.');
    await load();
  } catch (e) {
    toast(e instanceof ApiError ? e.message : 'Не удалось сохранить.');
  } finally {
    busy.value = false;
  }
}

async function removeItem(item) {
  if (!window.confirm(`Удалить «${item.title}»?`)) return;
  busy.value = true;
  try {
    await call('deleteWishlistItem', { campaignId: Number(route.params.id), itemId: item.id });
    delete photos[item.id];
    await load();
    toast('Удалено.');
  } catch (e) {
    toast(e.message);
  } finally {
    busy.value = false;
  }
}

async function move(item, delta) {
  if (busy.value) return;
  const index = items.value.findIndex((row) => row.id === item.id);
  const target = index + delta;
  if (index < 0 || target < 0 || target >= items.value.length) return;
  const next = [...items.value];
  [next[index], next[target]] = [next[target], next[index]];
  items.value = next;
  busy.value = true;
  try {
    await call('reorderWishlist', {
      campaignId: Number(route.params.id),
      itemIds: next.map((row) => row.id),
    });
  } catch (e) {
    toast(e.message);
    await load();
  } finally {
    busy.value = false;
  }
}

function pickPhoto(item) {
  photoTarget.value = item.id;
  fileInput.value?.click();
}

async function onFile(event) {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file || !photoTarget.value) return;
  photoBusy.value = true;
  try {
    const base64 = await compressImage(file);
    await call('uploadItemPhoto', {
      campaignId: Number(route.params.id),
      itemId: photoTarget.value,
      dataBase64: base64,
      mime: 'image/jpeg',
    });
    toast('Фото загружено.');
    await load();
  } catch (e) {
    toast(e instanceof ApiError ? e.message : 'Не удалось загрузить фото.');
  } finally {
    photoBusy.value = false;
  }
}

async function loadPhoto(item) {
  if (photos[item.id]) return;
  try {
    const result = await call('getItemPhoto', {
      campaignId: Number(route.params.id),
      itemId: item.id,
    });
    photos[item.id] = result.dataUrl;
  } catch {
    // фото может быть временно недоступно
  }
}

function readAsDataUrl(blob) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result));
    reader.onerror = reject;
    reader.readAsDataURL(blob);
  });
}

async function compressImage(file) {
  let source = null;
  if (typeof createImageBitmap === 'function') {
    try {
      source = await createImageBitmap(file);
    } catch {
      source = null;
    }
  }
  if (!source) source = await loadImage(file);
  const maxDim = 1280;
  const scale = Math.min(1, maxDim / Math.max(source.width, source.height));
  let width = Math.round(source.width * scale);
  let height = Math.round(source.height * scale);
  for (let attempt = 0; attempt < 4; attempt += 1) {
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(source, 0, 0, width, height);
    const quality = 0.8 - attempt * 0.12;
    const blob = await new Promise((resolve) =>
      canvas.toBlob(resolve, 'image/jpeg', Math.max(0.4, quality)),
    );
    if (blob && blob.size <= photoMax.value) {
      const dataUrl = await readAsDataUrl(blob);
      return dataUrl.split(',')[1];
    }
    width = Math.round(width * 0.82);
    height = Math.round(height * 0.82);
  }
  throw new ApiError('Фото слишком большое. Попробуйте другое.', 'PHOTO_TOO_BIG');
}

function loadImage(file) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const image = new Image();
    image.onload = () => {
      URL.revokeObjectURL(url);
      resolve(image);
    };
    image.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new ApiError('Не удалось прочитать изображение.', 'UNSUPPORTED_TYPE'));
    };
    image.src = url;
  });
}

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

onMounted(load);
</script>

<template>
  <div class="page">
    <div class="row row--between">
      <div>
        <p class="eyebrow">Вишлист</p>
        <h1 class="display" style="font-size: clamp(24px, 6vw, 30px)">Что хочется получить?</h1>
      </div>
      <img :src="tree" alt="" width="52" height="52" style="border-radius: 16px" />
    </div>
    <p class="lede">
      Добавьте несколько подсказок — Санте будет проще. Вишлист видят только ваш Санта и вы.
    </p>

    <div v-if="loading" class="list">
      <div v-for="n in 3" :key="n" class="skeleton" style="height: 96px" />
    </div>

    <div v-else-if="error" class="card stack">
      <p class="lede">{{ error }}</p>
      <button class="btn btn--primary" type="button" @click="load">Повторить</button>
    </div>

    <template v-else>
      <div v-if="items.length" class="list">
        <article v-for="(item, index) in items" :key="item.id" class="wish">
          <div class="wish__photo">
            <img v-if="photos[item.id]" :src="photos[item.id]" :alt="`Фото: ${item.title}`" />
            <AppIcon v-else name="image" :size="22" :stroke-width="1.6" />
          </div>
          <div class="wish__body">
            <h2 class="wish__title">{{ item.title }}</h2>
            <p v-if="formatPrice(item)" class="small muted">{{ formatPrice(item) }}</p>
            <p v-if="item.comment" class="small muted wish__comment">{{ item.comment }}</p>
            <a
              v-if="item.url"
              :href="item.url"
              class="small link"
              target="_blank"
              rel="noopener noreferrer"
              @click.prevent="openLink(item.url)"
            >
              Открыть ссылку
            </a>
            <div v-if="canEdit" class="row wish__actions">
              <button
                class="wish-action"
                type="button"
                aria-label="Изменить"
                title="Изменить"
                @click="openEdit(item)"
              >
                <AppIcon name="pencil" :size="19" :stroke-width="1.8" />
              </button>
              <button
                class="wish-action"
                type="button"
                :aria-label="item.hasPhoto ? 'Заменить фото' : 'Добавить фото'"
                :title="item.hasPhoto ? 'Заменить фото' : 'Добавить фото'"
                :disabled="photoBusy"
                @click="pickPhoto(item)"
              >
                <AppIcon name="image" :size="19" :stroke-width="1.8" />
              </button>
              <button
                class="wish-action"
                type="button"
                aria-label="Выше"
                title="Выше"
                :disabled="index === 0"
                @click="move(item, -1)"
              >
                <AppIcon name="arrow-up" :size="19" :stroke-width="1.8" />
              </button>
              <button
                class="wish-action"
                type="button"
                aria-label="Ниже"
                title="Ниже"
                :disabled="index === items.length - 1"
                @click="move(item, 1)"
              >
                <AppIcon name="arrow-down" :size="19" :stroke-width="1.8" />
              </button>
              <button
                class="wish-action wish-action--danger"
                type="button"
                aria-label="Удалить"
                title="Удалить"
                @click="removeItem(item)"
              >
                <AppIcon name="trash" :size="19" :stroke-width="1.8" />
              </button>
            </div>
          </div>
        </article>
      </div>

      <div v-else class="empty">
        <img :src="tree" alt="" width="96" height="96" />
        <p class="small">Пока пусто. Добавьте первое желание — это займёт минуту.</p>
      </div>

      <button
        v-if="canEdit"
        class="btn btn--primary btn--block"
        type="button"
        :disabled="items.length >= limit"
        @click="openNew"
      >
        {{ items.length >= limit ? `Лимит ${limit} ${pluralRu(limit, 'желание', 'желания', 'желаний')}` : 'Добавить желание' }}
      </button>
      <p class="hint">
        Можно добавить до {{ limit }}
        {{ pluralRu(limit, 'желания', 'желаний', 'желаний') }}. Фото — до 320 КБ, лучше 1280 px.
      </p>
    </template>

    <input
      ref="fileInput"
      class="sr-only"
      type="file"
      accept="image/jpeg,image/png"
      @change="onFile"
    />

    <AppSheet :open="editorOpen" :title="editingId ? 'Изменить желание' : 'Новое желание'" @close="editorOpen = false">
      <form class="stack" @submit.prevent="save">
        <div class="field">
          <label for="title">Название *</label>
          <input id="title" v-model="form.title" class="input" maxlength="120" required />
        </div>
        <div class="field">
          <label for="url">Ссылка</label>
          <input
            id="url"
            v-model="form.url"
            class="input"
            type="text"
            inputmode="url"
            :maxlength="MAX_URL_LENGTH"
            placeholder="https://"
          />
        </div>
        <div class="field">
          <label for="price">Цена, ₽</label>
          <input
            id="price"
            v-model="form.price"
            class="input"
            inputmode="numeric"
            :placeholder="pricePlaceholder"
          />
          <p v-if="budgetLabel" class="hint">Бюджет кампании: {{ budgetLabel }}.</p>
        </div>
        <div class="field">
          <label for="comment">Комментарий</label>
          <textarea id="comment" v-model="form.comment" class="input" maxlength="500" />
        </div>
        <button class="btn btn--primary" type="submit" :disabled="busy">
          {{ busy ? 'Сохраняем…' : 'Сохранить' }}
        </button>
      </form>
    </AppSheet>
  </div>
</template>

<style scoped>
.wish {
  display: flex;
  gap: 14px;
  padding: 14px;
  border: 1px solid var(--line);
  border-radius: 20px;
  background: var(--paper-raised);
}
.wish__photo {
  width: 76px;
  height: 76px;
  border-radius: 16px;
  background: var(--paper-sunken);
  display: grid;
  place-items: center;
  font-size: 28px;
  overflow: hidden;
  flex: 0 0 auto;
}
.wish__photo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.wish__body {
  min-width: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.wish__title {
  margin: 0;
  font-family: var(--font-display);
  font-size: 17px;
  line-height: 1.25;
}
.wish__comment {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.wish__actions {
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 8px;
}
.wish-action {
  width: 38px;
  height: 38px;
  padding: 0;
  appearance: none;
  border-radius: 12px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--pine-700);
  display: grid;
  place-items: center;
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}
.wish-action:not(:disabled):hover {
  background: var(--paper-sunken);
}
.wish-action:disabled {
  opacity: 0.4;
  cursor: default;
}
.wish-action--danger {
  color: var(--berry-600);
}
.wish-action svg {
  width: 19px;
  height: 19px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.link {
  color: var(--pine-700);
  text-decoration: underline;
  text-underline-offset: 3px;
}
</style>
