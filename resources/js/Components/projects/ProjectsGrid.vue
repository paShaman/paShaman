<script setup>
import { ref, computed } from 'vue';
import { Search, ChevronDown } from 'lucide-vue-next';
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
        const tagNames = selectedTags.value.map((t) => t.name);
        result = result.filter((el) => el.tags.some((v) => tagNames.includes(v)));
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
    <section class="pt-10 sm:pt-14">
        <div class="text-center">
            <p class="text-2xl sm:text-5xl font-semibold mb-2 text-ternary-dark flex items-baseline justify-center gap-3">
                Проекты
                <small class="text-gray-300 font-light">{{ filteredProjects.length }}</small>
            </p>
        </div>

        <div class="mt-10 sm:mt-10">
            <div class="flex justify-between border-b border-primary-light pb-3 gap-2">
                <div class="flex justify-between gap-2">
                    <span class="hidden sm:block bg-primary-light p-2.5 shadow-sm rounded-xl cursor-pointer">
                        <Search class="text-ternary-dark w-5 h-5" />
                    </span>
                    <input
                        v-model="searchProject"
                        class="font-medium pl-3 pr-1 sm:px-4 py-2 border-1 border-gray-200 rounded-lg text-sm sm:text-md bg-secondary-light text-primary-dark w-full sm:w-auto"
                        type="search"
                        placeholder="Поиск..."
                    />
                </div>

                <span
                    @click="showTags = !showTags"
                    class="font-medium flex items-center px-4 py-2 rounded-lg shadow-lg hover:shadow-xl bg-indigo-500 hover:bg-indigo-600 text-white duration-300 cursor-pointer"
                    :class="{ active: showTags }"
                >
                    Теги
                    <ChevronDown class="size-4 ms-2" :class="{ 'rotate-180': showTags }" />
                </span>
            </div>
        </div>

        <div class="tags-outer" :class="{ active: showTags }">
            <div>
                <div>
                    <ProjectsTags :tags="tags" :cnt="projects.length" @select-tags="selectTags" />
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 mt-6 sm:gap-10">
            <ProjectItem
                v-for="project in filteredProjects"
                :key="project.id"
                :project="project"
            />
        </div>
    </section>
</template>

<style scoped>
.tags-outer {
    display: grid;
    grid-template-rows: 0fr;
    transition: all 0.3s ease;
}
.tags-outer > div {
    overflow: hidden;
}
.tags-outer > div > div {
    padding-block: 0.5rem;
}
.tags-outer.active {
    grid-template-rows: 1fr;
}
</style>