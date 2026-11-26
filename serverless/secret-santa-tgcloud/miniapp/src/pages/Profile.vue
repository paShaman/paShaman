<script setup>
import { computed, ref } from 'vue';
import { call } from '../api/client.js';
import { state, toast } from '../composables/useSession.js';

const saving = ref(false);
const name = ref(state.boot?.user?.name ?? '');
const error = ref('');

const user = computed(() => state.boot?.user);
const mockMode = computed(() => !window?.Telegram?.WebApp?.initData);

async function save() {
  if (!name.value.trim()) {
    error.value = 'Имя не может быть пустым.';
    return;
  }
  saving.value = true;
  error.value = '';
  try {
    await call('updateProfile', { firstName: name.value.trim(), lang: 'ru' });
    if (state.boot?.user) state.boot.user.name = name.value.trim();
    toast('Профиль сохранён.');
  } catch (e) {
    error.value = e.message;
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div class="page">
    <div>
      <p class="eyebrow">Профиль</p>
      <h1 class="display" style="font-size: clamp(24px, 6vw, 30px)">Как вас зовут?</h1>
    </div>

    <div class="card stack">
      <div class="row">
        <div class="avatar" aria-hidden="true">{{ (user?.name || 'С').slice(0, 1) }}</div>
        <div>
          <p style="font-weight: 700">{{ user?.name || 'Участник' }}</p>
          <p class="small muted">Имя видят участники кампании</p>
        </div>
      </div>
      <div class="divider" />
      <div class="field">
        <label for="name">Имя</label>
        <input id="name" v-model="name" class="input" maxlength="64" autocomplete="name" />
      </div>
      <p v-if="error" class="error" role="alert">{{ error }}</p>
      <button class="btn btn--primary" type="button" :disabled="saving" @click="save">
        {{ saving ? 'Сохраняем…' : 'Сохранить' }}
      </button>
    </div>

    <div class="card stack">
      <h2 class="h2">Приватность</h2>
      <p class="small muted">
        Вишлист и фото видны только вашему Санте. Адрес и телефон — только ему и только до
        завершения кампании. Отметки «куплено» видит только Санта.
      </p>
      <p class="small muted">
        Сообщения чата хранятся для разбора жалоб. Удалить данные можно через организатора или
        администратора бота.
      </p>
    </div>

    <p v-if="mockMode" class="hint">
      Демо-режим: приложение открыто вне Telegram. Данные хранятся только в памяти.
    </p>
  </div>
</template>
