<script setup>
import { ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import Fancybox from '@/Components/Fancybox.vue';

defineProps({
    project: {
        type: Object,
        required: true,
    },
});

const gradientClass = ref('');

const colors = [
    { from: 'from-slate-300', to: 'to-slate-100' },
    { from: 'from-zinc-300', to: 'to-zinc-100' },
    { from: 'from-neutral-300', to: 'to-neutral-100' },
    { from: 'from-red-300', to: 'to-red-100' },
    { from: 'from-stone-300', to: 'to-stone-100' },
    { from: 'from-orange-300', to: 'to-orange-100' },
    { from: 'from-amber-300', to: 'to-amber-100' },
    { from: 'from-yellow-300', to: 'to-yellow-100' },
    { from: 'from-lime-300', to: 'to-lime-100' },
    { from: 'from-green-300', to: 'to-green-100' },
    { from: 'from-emerald-300', to: 'to-emerald-100' },
    { from: 'from-teal-300', to: 'to-teal-100' },
    { from: 'from-cyan-300', to: 'to-cyan-100' },
    { from: 'from-sky-300', to: 'to-sky-100' },
    { from: 'from-blue-300', to: 'to-blue-100' },
    { from: 'from-indigo-300', to: 'to-indigo-100' },
    { from: 'from-violet-300', to: 'to-violet-100' },
    { from: 'from-purple-300', to: 'to-purple-100' },
    { from: 'from-fuchsia-300', to: 'to-fuchsia-100' },
    { from: 'from-pink-300', to: 'to-pink-100' },
    { from: 'from-rose-300', to: 'to-rose-100' },
    { from: 'from-gray-300', to: 'to-gray-100' },
];

function randomInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

onMounted(() => {
    const color = colors[randomInt(1, colors.length - 1)];
    gradientClass.value = color.from + ' ' + color.to;
});
</script>

<template>
    <div class="block md:flex gap-0 sm:gap-10 mt-14">
        <!-- Single project left section details -->
        <div class="w-full md:w-2/3 text-left">
            <Fancybox v-if="project.image">
                <a :href="project.host + (project.image_full || project.image)" data-fancybox>
                    <img :src="project.host + project.image" class="rounded-xl cursor-pointer shadow-lg sm:shadow-none" alt="" />
                </a>
            </Fancybox>

            <div v-else class="flex items-center justify-center w-full h-48 bg-gray-300 rounded dark:bg-gray-700 animate-pulse">
                <svg class="w-10 h-10 text-gray-200 dark:text-gray-600" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 18">
                    <path d="M18 0H2a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2Zm-5.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm4.376 10.481A1 1 0 0 1 16 15H4a1 1 0 0 1-.895-1.447l3.5-7A1 1 0 0 1 7.468 6a.965.965 0 0 1 .9.5l2.775 4.757 1.546-1.887a1 1 0 0 1 1.618.1l2.541 4a1 1 0 0 1 .028 1.011Z"/>
                </svg>
            </div>
        </div>

        <!-- Single project right section details -->
        <div class="w-full md:w-1/3 text-left mt-10 md:mt-0">
            <!-- Skeleton loader -->
            <div v-if="!project.image" class="w-full animate-pulse">
                <div class="h-2.5 bg-gray-200 rounded-full dark:bg-gray-700 w-48 mb-4"></div>
                <div class="h-2 bg-gray-200 rounded-full dark:bg-gray-700 max-w-[480px] mb-2.5"></div>
                <div class="h-2 bg-gray-200 rounded-full dark:bg-gray-700 mb-2.5"></div>
                <div class="h-2 bg-gray-200 rounded-full dark:bg-gray-700 max-w-[440px] mb-2.5"></div>
                <div class="h-2 bg-gray-200 rounded-full dark:bg-gray-700 max-w-[460px] mb-2.5"></div>
                <div class="h-2 bg-gray-200 rounded-full dark:bg-gray-700 max-w-[360px]"></div>
            </div>

            <template v-if="project.image">
                <!-- Authors -->
                <div class="mb-7">
                    <ul class="leading-loose grid gap-3">
                        <li v-for="author in project.authors" :key="author.id" class="font-normal text-ternary-dark dark:text-ternary-light flex items-center gap-5">
                            <img :src="project.host + author.image" class="w-16 rounded-full" alt="" />
                            <div>
                                <span v-if="!author.site">{{ author.name }}</span>
                                <a v-if="author.site" :href="author.site" class="hover:underline cursor-pointer">{{ author.name }}</a>
                                <div class="opacity-60 -mt-0.5 text-xs">
                                    {{ author.pivot?.role }}
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Description -->
                <div class="mb-7" v-if="project.info">
                    <p class="font-medium text-2xl text-ternary-dark dark:text-ternary-light mb-2">Описание</p>
                    <p class="font-normal text-primary-dark dark:text-ternary-light">
                        {{ project.info }}
                    </p>
                </div>

                <!-- Tags -->
                <div class="mb-7">
                    <p class="font-medium text-2xl text-ternary-dark dark:text-ternary-light mb-2">Теги</p>
                    <div class="font-normal text-primary-dark dark:text-ternary-light flex gap-1 flex-wrap">
                        <Link
                            v-for="tag in project.tags"
                            :key="tag"
                            :href="`/?tag=${tag}`"
                            class="inline-block rounded-md px-2.5 border-2 dark:border-white/20 hover:border-gray-400 cursor-pointer"
                        >
                            {{ tag }}
                        </Link>
                    </div>
                </div>

                <!-- Versions -->
                <div class="mb-7" v-if="project.versions && project.versions.length > 1">
                    <p class="font-medium text-2xl text-ternary-dark dark:text-ternary-light mb-2">Версии</p>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            v-for="version in project.versions"
                            :key="version.link"
                            :href="version.link"
                            class="font-medium flex items-center px-4 py-2 rounded-lg shadow-lg hover:shadow-xl text-white duration-300 cursor-pointer"
                            :class="version.current ? 'bg-emerald-500 hover:bg-emerald-600' : 'bg-indigo-500 hover:bg-indigo-600'"
                        >
                            {{ version.version }}
                        </Link>
                    </div>
                </div>

                <!-- Years -->
                <div class="mb-7" v-if="project.years && project.years.length > 1">
                    <p class="font-medium text-2xl text-ternary-dark dark:text-ternary-light mb-2">Года</p>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            v-for="year in project.years"
                            :key="year.link"
                            :href="year.link"
                            class="font-medium flex items-center px-4 py-2 rounded-lg shadow-lg hover:shadow-xl text-white duration-300 cursor-pointer"
                            :class="year.current ? 'bg-emerald-500 hover:bg-emerald-600' : 'bg-indigo-500 hover:bg-indigo-600'"
                        >
                            {{ year.year }}
                        </Link>
                    </div>
                </div>

                <!-- Works -->
                <div class="mb-7 px-6 py-4 rounded-xl bg-gradient-to-r" v-if="project.works && project.works.length > 1" :class="gradientClass">
                    <p class="font-medium text-2xl text-ternary-dark mb-4">Работа</p>
                    <div class="grid gap-4 text-primary-dark">
                        <div v-for="work in project.works" :key="work.link" class="flex gap-2 items-start">
                            <Link :href="work.link" class="font-medium hover:underline cursor-pointer w-[6rem]">
                                {{ work.name }}
                            </Link>
                            <div class="flex-1">
                                {{ work.years.join(', ') }}
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>