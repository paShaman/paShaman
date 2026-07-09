<script setup>
import { Clock, Link, Tag } from 'lucide-vue-next';

defineProps({
    project: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div>
        <p class="text-left text-3xl sm:text-4xl font-bold text-primary-dark dark:text-primary-light mt-14 sm:mt-20 mb-7">
            <span v-if="project.name">{{ project.name }}</span>
            <div v-else class="h-4 bg-gray-200 rounded-full dark:bg-gray-700 w-48 mb-4"></div>
        </p>

        <div class="flex gap-4 sm:gap-10 flex-wrap" v-if="project.date">
            <div class="flex items-center">
                <Clock class="w-4 h-4 text-ternary-dark dark:text-ternary-light" />
                <span class="font-medium ml-2 text-primary-dark dark:text-primary-light">{{ project.date }}</span>
            </div>
            <div class="flex items-center" v-if="project.site">
                <Link class="w-4 h-4 text-ternary-dark dark:text-ternary-light" />
                <a
                    :href="project.site"
                    target="_blank"
                    class="font-medium ml-2 text-primary-dark dark:text-primary-light hover:underline cursor-pointer"
                >{{ project.site }}</a>
            </div>
            <div class="flex items-center" v-if="project.current_version">
                <Tag class="w-4 h-4 text-ternary-dark dark:text-ternary-light shrink-0" />
                <span class="font-medium ml-2 text-primary-dark dark:text-primary-light">Версия {{ project.current_version }}</span>
            </div>
        </div>
    </div>
</template>