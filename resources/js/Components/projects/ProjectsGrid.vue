<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Search } from '@lucide/vue';
import ProjectsTags from './ProjectsTags.vue';
import ProjectItem from './ProjectItem.vue';

const projects = ref([]);
const tags = ref([]);
const searchQuery = ref('');
const selectedTags = ref([]);
const showTags = ref(false);
const page = ref(1);
const totalPages = ref(1);
const hasMore = ref(false);
const total = ref(0);
const loading = ref(false);
const initialLoading = ref(true);
let observer = null;
const sentinelRef = ref(null);

// Дебаунс для поиска
let searchTimer = null;

async function fetchProjects(reset = false) {
    if (loading.value) return;
    if (!reset && !hasMore.value) return;

    loading.value = true;

    if (reset) {
        page.value = 1;
        projects.value = [];
    }

    const params = new URLSearchParams({
        page: page.value,
    });

    if (searchQuery.value) {
        params.set('search', searchQuery.value);
    }

    if (selectedTags.value.length > 0) {
        params.set('tags', selectedTags.value.map(t => t.name).join(','));
    }

    try {
        const response = await fetch(`/api/projects?${params.toString()}`);
        const data = await response.json();

        if (reset) {
            projects.value = data.projects;
        } else {
            projects.value = [...projects.value, ...data.projects];
        }

        totalPages.value = data.totalPages;
        hasMore.value = data.hasMore;
        total.value = data.total;
        page.value = data.page;
    } catch (e) {
        console.error('Failed to fetch projects:', e);
    } finally {
        loading.value = false;
        initialLoading.value = false;
    }
}

async function fetchTags() {
    try {
        const response = await fetch('/api/tags');
        const data = await response.json();
        tags.value = data.tags;
    } catch (e) {
        console.error('Failed to fetch tags:', e);
    }
}

// Intersection Observer для infinite scroll
function setupObserver() {
    if (observer) observer.disconnect();

    observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && hasMore.value && !loading.value) {
            page.value++;
            fetchProjects();
        }
    }, { rootMargin: '200px' });

    if (sentinelRef.value) {
        observer.observe(sentinelRef.value);
    }
}

// Вотчер для обсервера (переподключаемся при изменении DOM)
watch(sentinelRef, (el) => {
    if (el) setupObserver();
});

// Поиск с дебаунсом
watch(searchQuery, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        fetchProjects(true);
    }, 350);
});

// Фильтр по тегам — сразу перезагружаем
function onSelectTags(selected) {
    selectedTags.value = selected;
    fetchProjects(true);
}

onMounted(() => {
    fetchProjects(true);
    fetchTags();
});

onBeforeUnmount(() => {
    if (observer) observer.disconnect();
    clearTimeout(searchTimer);
});
</script>

<template>
    <section class="pt-16 sm:pt-24 pb-12 sm:pb-20">
        <div class="container mx-auto px-4 sm:px-6">
            <!-- Section header -->
            <div class="text-center mb-10 sm:mb-14">
                <h2 class="font-display text-3xl sm:text-5xl font-bold text-text-primary mb-2">
                    Проекты
                </h2>
                <p class="text-text-muted text-sm sm:text-base">
                    {{ total }} {{ total === 1 ? 'проект' : (total >= 2 && total <= 4 ? 'проекта' : 'проектов') }}
                </p>
            </div>

            <!-- Search & Tags -->
            <div class="mb-8 space-y-4">
                <!-- Search -->
                <div class="relative max-w-md mx-auto">
                    <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted pointer-events-none" />
                    <input
                        v-model="searchQuery"
                        class="w-full pl-10 pr-4 py-2.5 bg-warm-surface border border-border-default rounded-2xl text-sm text-text-primary placeholder:text-text-muted focus:outline-none focus:border-accent-terracotta focus:ring-2 focus:ring-accent-terracotta/20 transition-all duration-300"
                        type="search"
                        placeholder="Поиск проектов..."
                    />
                </div>

                <!-- Tags -->
                <div class="flex justify-center">
                    <ProjectsTags
                        :tags="tags"
                        :cnt="total"
                        :show="showTags"
                        @toggle="showTags = !showTags"
                        @select-tags="onSelectTags"
                    />
                </div>
            </div>

            <!-- Loading state (initial) -->
            <div v-if="initialLoading" class="text-center py-16">
                <div class="inline-block w-8 h-8 border-3 border-accent-terracotta/30 border-t-accent-terracotta rounded-full animate-spin"></div>
                <p class="text-text-muted mt-4">Загрузка проектов...</p>
            </div>

            <!-- Empty state -->
            <div v-else-if="projects.length === 0" class="text-center py-16">
                <p class="text-text-muted text-lg">Проекты не найдены</p>
                <p class="text-text-muted/70 text-sm mt-1">Попробуйте изменить параметры поиска или фильтры</p>
            </div>

            <!-- Projects grid -->
            <template v-else>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                    <ProjectItem
                        v-for="project in projects"
                        :key="project.id"
                        :project="project"
                    />
                </div>

                <!-- Loading more indicator -->
                <div v-if="hasMore" class="text-center py-8">
                    <div
                        v-if="loading"
                        class="inline-block w-6 h-6 border-3 border-accent-terracotta/30 border-t-accent-terracotta rounded-full animate-spin"
                    ></div>
                </div>
            </template>

            <!-- Sentinel для Intersection Observer -->
            <div ref="sentinelRef" class="h-1"></div>
        </div>
    </section>
</template>