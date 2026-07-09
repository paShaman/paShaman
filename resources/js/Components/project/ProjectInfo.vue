<script setup>
import { Link } from '@inertiajs/vue3';
import Fancybox from '@/Components/Fancybox.vue';

defineProps({
    project: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="block md:flex gap-0 sm:gap-10 mt-10 sm:mt-14">
        <!-- Left: project image -->
        <div class="w-full md:w-2/3 text-left">
            <Fancybox v-if="project.image">
                <a :href="project.host + (project.image_full || project.image)" data-fancybox>
                    <img
                        :src="project.host + project.image"
                        class="rounded-2xl sm:rounded-3xl cursor-pointer shadow-card hover:shadow-card-hover transition-shadow duration-300 w-full"
                        alt=""
                        loading="lazy"
                    />
                </a>
            </Fancybox>

            <div v-else class="flex items-center justify-center w-full h-48 bg-border-default rounded-2xl animate-pulse">
                <svg class="w-10 h-10 text-text-muted" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 18">
                    <path d="M18 0H2a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2Zm-5.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm4.376 10.481A1 1 0 0 1 16 15H4a1 1 0 0 1-.895-1.447l3.5-7A1 1 0 0 1 7.468 6a.965.965 0 0 1 .9.5l2.775 4.757 1.546-1.887a1 1 0 0 1 1.618.1l2.541 4a1 1 0 0 1 .028 1.011Z"/>
                </svg>
            </div>
        </div>

        <!-- Right: project details -->
        <div class="w-full md:w-1/3 text-left mt-10 md:mt-0">
            <!-- Skeleton loader -->
            <div v-if="!project.image" class="w-full animate-pulse space-y-4">
                <div class="h-3 bg-border-default rounded-full w-48"></div>
                <div class="h-3 bg-border-default rounded-full max-w-[480px]"></div>
                <div class="h-3 bg-border-default rounded-full"></div>
                <div class="h-3 bg-border-default rounded-full max-w-[440px]"></div>
                <div class="h-3 bg-border-default rounded-full max-w-[460px]"></div>
                <div class="h-3 bg-border-default rounded-full max-w-[360px]"></div>
            </div>

            <template v-if="project.image">
                <!-- Authors -->
                <div class="mb-8 bg-warm-surface rounded-2xl p-5 shadow-card">
                    <ul class="space-y-4">
                        <li v-for="author in project.authors" :key="author.id" class="flex items-center gap-4">
                            <img :src="project.host + author.image" class="w-12 h-12 rounded-full object-cover shrink-0" alt="" />
                            <div>
                                <span v-if="!author.site" class="text-sm font-medium text-text-primary">{{ author.name }}</span>
                                <a v-if="author.site" :href="author.site" class="text-sm font-medium text-text-primary hover:text-accent-terracotta transition-colors cursor-pointer">{{ author.name }}</a>
                                <div class="text-xs text-text-muted mt-0.5">
                                    {{ author.pivot?.role }}
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Tags -->
                <div class="mb-8" v-if="project.tags && project.tags.length">
                    <p class="font-display text-xl text-text-primary mb-3">Теги</p>
                    <div class="flex gap-1.5 flex-wrap">
                        <Link
                            v-for="tag in project.tags"
                            :key="tag"
                            :href="`/?tag=${tag}`"
                            class="inline-block rounded-full px-3 py-1 text-sm font-medium bg-accent-terracotta-light/30 text-text-secondary hover:bg-accent-terracotta-light/50 transition-colors cursor-pointer"
                        >
                            {{ tag }}
                        </Link>
                    </div>
                </div>

                <!-- Description -->
                <div class="mb-8" v-if="project.info">
                    <p class="font-display text-xl text-text-primary mb-3">Описание</p>
                    <p class="text-sm text-text-secondary leading-relaxed">
                        {{ project.info }}
                    </p>
                </div>

                <!-- Versions -->
                <div class="mb-8" v-if="project.versions && project.versions.length > 1">
                    <p class="font-display text-xl text-text-primary mb-3">Версии</p>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            v-for="version in project.versions"
                            :key="version.link"
                            :href="version.link"
                            class="font-medium flex items-center px-4 py-2 rounded-full text-sm transition-all duration-300 cursor-pointer"
                            :class="version.current
                                ? 'bg-accent-sage text-white shadow-sm hover:shadow-md hover:-translate-y-0.5'
                                : 'bg-warm-surface text-text-secondary border border-border-default hover:border-accent-terracotta hover:text-accent-terracotta'"
                        >
                            {{ version.version }}
                        </Link>
                    </div>
                </div>

                <!-- Years -->
                <div class="mb-8" v-if="project.years && project.years.length > 1">
                    <p class="font-display text-xl text-text-primary mb-3">Года</p>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            v-for="year in project.years"
                            :key="year.link"
                            :href="year.link"
                            class="font-medium flex items-center px-4 py-2 rounded-full text-sm transition-all duration-300 cursor-pointer"
                            :class="year.current
                                ? 'bg-accent-sage text-white shadow-sm hover:shadow-md hover:-translate-y-0.5'
                                : 'bg-warm-surface text-text-secondary border border-border-default hover:border-accent-terracotta hover:text-accent-terracotta'"
                        >
                            {{ year.year }}
                        </Link>
                    </div>
                </div>

                <!-- Works -->
                <div class="mb-8 bg-accent-amber-light/30 rounded-2xl p-5" v-if="project.works && project.works.length > 1">
                    <p class="font-display text-xl text-text-primary mb-4">Работа</p>
                    <div class="space-y-3 text-sm text-text-secondary">
                        <div v-for="work in project.works" :key="work.link" class="flex gap-2 items-start">
                            <Link :href="work.link" class="font-medium text-text-primary hover:text-accent-terracotta transition-colors cursor-pointer w-[6rem] shrink-0">
                                {{ work.name }}
                            </Link>
                            <div class="flex-1 text-text-muted">
                                {{ work.years.join(', ') }}
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>