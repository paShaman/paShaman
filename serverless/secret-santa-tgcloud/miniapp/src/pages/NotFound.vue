<script setup>
import tree from '../assets/tree-192.png';
</script>

<template>
  <div class="page">
    <div class="empty" style="margin-top: 40px">
      <img :src="tree" alt="" width="96" height="96" />
      <h1 class="h2" style="margin-bottom: 8px">Здесь ничего нет</h1>
      <p class="small">Ссылка устарела или кампания была удалена.</p>
      <RouterLink to="/campaigns" class="btn btn--primary" style="margin-top: 16px">
        Мои кампании
      </RouterLink>
    </div>
  </div>
</template>
