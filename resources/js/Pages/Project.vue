<script setup>
import { Head } from '@inertiajs/vue3';
import ProjectHeader from '@/Components/project/ProjectHeader.vue';
import ProjectInfo from '@/Components/project/ProjectInfo.vue';
import ProjectNav from '@/Components/project/ProjectNav.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head :title="'paShaman - ' + project.name" />

    <div class="container mx-auto px-4 sm:px-6 mt-10 sm:mt-16 pb-12 sm:pb-20">
        <ProjectHeader :project="project" />
        <ProjectInfo :project="project" />
        <ProjectNav :prev="project.prev" :next="project.next" />
    </div>
</template>