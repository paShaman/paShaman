<script setup>
import { ref, computed } from 'vue';
import { Search } from 'lucide-vue-next';
import ProjectsTags from './ProjectsTags.vue';
import ProjectItem from './ProjectItem.vue';

const props = defineProps({
    projects: {
        type: Array,
        default: () => [],
    },
    tags: {
        type: Array,
        default: () => [],
    },
});

const searchProject = ref('');
const selectedTags = ref([]);
const showTags = ref(false);

const filteredProjects = computed(() => {
    let result = props.projects;

    if (selectedTags.value.length > 0) {
        const tagNames = selectedTags.value.map((t) => String(t.name));
        result = result.filter((el) => el.tags.some((v) => tagNames.includes(String(v))));
    }

    if (searchProject.value) {
        const search = new RegExp(searchProject.value, 'i');
        result = result.filter(
            (el) =>
                el.name.match(search) ||
                el.link.match(search) ||
                el.tags.join(' ').match(search),
        );
    }

    return result;
});

function selectTags(tags) {
    selectedTags.value = tags;
}
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
                    {{ filteredProjects.length }} {{ filteredProjects.length === 1 ? 'проект' : (filteredProjects.length >= 2 && filteredProjects.length <= 4 ? 'проекта' : 'проектов') }}
                </p>
            </div>

            <!-- Search & Tags -->
            <div class="mb-8 space-y-4">
                <!-- Search -->
                <div class="relative max-w-md mx-auto">
                    <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted pointer-events-none" />
                    <input
                        v-model="searchProject"
                        class="w-full pl-10 pr-4 py-2.5 bg-warm-surface border border-border-default rounded-2xl text-sm text-text-primary placeholder:text-text-muted focus:outline-none focus:border-accent-terracotta focus:ring-2 focus:ring-accent-terracotta/20 transition-all duration-300"
                        type="search"
                        placeholder="Поиск проектов..."
                    />
                </div>

                <!-- Tags -->
                <div class="flex justify-center">
                    <ProjectsTags
                        :tags="tags"
                        :cnt="props.projects.length"
                        :show="showTags"
                        @toggle="showTags = !showTags"
                        @select-tags="selectTags"
                    />
                </div>
            </div>

            <!-- Projects grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                <ProjectItem
                    v-for="project in filteredProjects"
                    :key="project.id"
                    :project="project"
                />
            </div>

            <!-- Empty state -->
            <div v-if="filteredProjects.length === 0" class="text-center py-16">
                <p class="text-text-muted text-lg">Проекты не найдены</p>
                <p class="text-text-muted/70 text-sm mt-1">Попробуйте изменить параметры поиска или фильтры</p>
            </div>
        </div>
    </section>
</template>