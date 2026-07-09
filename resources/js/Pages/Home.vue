<script setup>
import { Head } from '@inertiajs/vue3';
import AppBanner from '@/Components/shared/AppBanner.vue';
import AboutCounter from '@/Components/about/AboutCounter.vue';
import ProjectsGrid from '@/Components/projects/ProjectsGrid.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    projects: {
        type: Array,
        default: () => [],
    },
    tags: {
        type: Array,
        default: () => [],
    },
    counters: {
        type: Object,
        default: () => ({}),
    },
    showHidden: {
        type: Boolean,
        default: false,
    },
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
            <ProjectsGrid :projects="projects" :tags="tags" />
        </div>
    </div>
</template>