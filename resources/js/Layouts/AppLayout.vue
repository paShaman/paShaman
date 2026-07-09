<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppHeader from '@/Components/shared/AppHeader.vue';
import AppFooter from '@/Components/shared/AppFooter.vue';
import BackToTop from '@/Components/shared/BackToTop.vue';

const page = usePage();
const isHome = computed(() => page.component === 'Home');
</script>

<template>
    <div class="min-h-screen bg-warm-bg">
        <AppHeader :is-home="isHome" />

        <main>
            <Transition name="page-fade" mode="out-in">
<div :key="page.url">
                    <slot />
                </div>
            </Transition>
        </main>

        <BackToTop />

        <AppFooter />
    </div>
</template>

<style scoped>
.page-fade-enter-active,
.page-fade-leave-active {
    transition: opacity 0.25s ease;
}
.page-fade-enter-from,
.page-fade-leave-to {
    opacity: 0;
}
</style>
