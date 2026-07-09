<script setup>
import { Head } from '@inertiajs/vue3';
import ProjectHeader from '@/Components/project/ProjectHeader.vue';
import ProjectInfo from '@/Components/project/ProjectInfo.vue';
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

    <div class="container mx-auto mt-10 sm:mt-20">
        <ProjectHeader :project="project" />
        <ProjectInfo :project="project" />
    </div>
</template>