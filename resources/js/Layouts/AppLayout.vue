<script setup>
import AppHeader from '@/Components/shared/AppHeader.vue';
import AppFooter from '@/Components/shared/AppFooter.vue';
import BackToTop from '@/Components/shared/BackToTop.vue';
</script>

<template>
    <div class="min-h-screen bg-warm-bg">
        <AppHeader />

        <main>
            <slot />
        </main>

        <BackToTop />

        <AppFooter />
    </div>
</template>