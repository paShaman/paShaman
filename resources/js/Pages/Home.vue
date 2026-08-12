<script setup>
import { onMounted, onBeforeUnmount } from 'vue';
import { Head } from '@inertiajs/vue3';
import { lenisScrollTo } from '@/composables/useLenis';
import AppBanner from '@/Components/shared/AppBanner.vue';
import AboutCounter from '@/Components/about/AboutCounter.vue';
import ProjectsGrid from '@/Components/projects/ProjectsGrid.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    counters: {
        type: Object,
        default: () => ({}),
    },
    showHidden: {
        type: Boolean,
        default: false,
    },
    initialTag: {
        type: String,
        default: '',
    },
});

let scrollTimer = null;

onMounted(() => {
    if (!props.initialTag) return;

    // Ждём navigate-синхронизацию Lenis в app.js, затем скроллим к блоку проектов
    scrollTimer = setTimeout(() => {
        lenisScrollTo('#projects', { offset: -96 });
    }, 100);
});

onBeforeUnmount(() => {
    if (scrollTimer) clearTimeout(scrollTimer);
});
</script>

<template>
    <Head title="paShaman — веб-разработка, дизайн и продвижение" />

    <div>
        <div class="container mx-auto">
            <!-- Banner -->
            <AppBanner class="mb-5 sm:mb-8" />
        </div>

        <!-- About counter -->
        <AboutCounter :counters="counters" />

        <div class="container mx-auto">
            <!-- Projects -->
            <ProjectsGrid :initial-tag="initialTag" />
        </div>
    </div>
</template>