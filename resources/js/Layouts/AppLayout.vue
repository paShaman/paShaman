<script setup>
import AppHeader from '@/Components/shared/AppHeader.vue';
import AppFooter from '@/Components/shared/AppFooter.vue';
import BackToTop from '@/Components/shared/BackToTop.vue';
</script>

<template>
    <div class="pt-0.5 min-h-screen bg-secondary-light">
        <AppHeader />

        <main>
            <slot />
        </main>

        <BackToTop />

        <AppFooter />
    </div>
</template>
