<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    project: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Link
        v-if="project.link !== '#'"
        :href="project.link"
        class="rounded-xl shadow-lg hover:shadow-xl mb-10 sm:mb-0 bg-secondary-light block"
        aria-label="Single Project"
    >
        <div>
            <img
                :src="project.image"
                class="rounded-t-xl border-none aspect-square object-cover"
                alt=""
            />
        </div>
        <div class="px-4 py-6">
            <div class="flex items-center justify-between mb-2 gap-3">
                <div class="text-xl text-ternary-dark font-semibold flex-1 text-left text-nowrap overflow-ellipsis overflow-hidden">
                    {{ project.name }}
                </div>
                <div class="font-semibold text-gray-500 shrink-0">
                    {{ project.date }}
                </div>
            </div>
            <div class="font-medium text-gray-400 text-nowrap overflow-ellipsis overflow-hidden w-full">
                {{ project.tags.join(' ') }}
            </div>
        </div>
    </Link>

    <!-- Скрытый проект (режим full=false) -->
    <span
        v-else
        class="rounded-xl shadow-lg hover:shadow-xl mb-10 sm:mb-0 bg-secondary-light block"
        aria-label="Hidden Project"
    >
        <div>
            <img
                :src="project.image"
                class="rounded-t-xl border-none aspect-square object-cover"
                alt=""
            />
        </div>
        <div class="px-4 py-6">
            <div class="flex items-center justify-between mb-2 gap-3">
                <div class="text-xl text-ternary-dark font-semibold flex-1 text-left text-nowrap overflow-ellipsis overflow-hidden">
                    {{ project.name }}
                </div>
                <div class="font-semibold text-gray-500 shrink-0">
                    {{ project.date }}
                </div>
            </div>
            <div class="font-medium text-gray-400 text-nowrap overflow-ellipsis overflow-hidden w-full">
                {{ project.tags.join(' ') }}
            </div>
        </div>
    </span>
</template>