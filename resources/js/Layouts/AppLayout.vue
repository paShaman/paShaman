<script setup>
import { ref, onMounted, watch } from 'vue';
import AppHeader from '@/Components/shared/AppHeader.vue';
import AppFooter from '@/Components/shared/AppFooter.vue';
import BackToTop from '@/Components/shared/BackToTop.vue';

const appTheme = ref(localStorage.getItem('theme') || 'light');

function updateTheme(theme) {
    appTheme.value = theme;
}

onMounted(() => {
    appTheme.value = localStorage.getItem('theme') || 'light';
});
</script>

<template>
    <div :class="appTheme === 'dark' ? 'dark' : ''" class="pt-0.5 min-h-screen bg-secondary-light dark:bg-primary-dark">
        <AppHeader :theme="appTheme" @theme-changed="updateTheme" />

        <main>
            <slot />
        </main>

        <BackToTop />

        <AppFooter />
    </div>
</template>