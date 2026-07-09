<script setup>
import { usePage } from '@inertiajs/vue3';
import { Globe, Linkedin, Link, Send } from 'lucide-vue-next';
import { computed } from 'vue';

const socials = computed(() => usePage().props.site?.social || []);

function iconComponent(icon) {
    const icons = { globe: Globe, linkedin: Linkedin, link: Link, send: Send };
    return icons[icon] || Link;
}
</script>

<template>
    <div class="container mx-auto">
        <div class="pt-20 sm:pt-30 pb-8 mt-20 border-t-2 border-primary-light dark:border-secondary-dark">
            <!-- Footer social links -->
            <div class="flex flex-col justify-center items-center mb-12 sm:mb-20">
                <p class="font-semibold text-3xl sm:text-4xl text-primary-dark dark:text-primary-light mb-5">
                    Социальные сети
                </p>
                <ul class="flex gap-4 sm:gap-8">
                    <a
                        v-for="social in socials"
                        :key="social.id"
                        :href="social.url"
                        target="_blank"
                        class="text-gray-400 hover:text-indigo-500 dark:hover:text-indigo-400 cursor-pointer rounded-lg bg-gray-50 dark:bg-ternary-dark hover:bg-gray-100 shadow-sm p-4 duration-500"
                    >
                        <component :is="iconComponent(social.icon)" class="w-5 sm:w-8 h-5 sm:h-8" />
                    </a>
                </ul>
            </div>

            <!-- Footer copyright -->
            <div class="text-center text-gray-500 dark:text-gray-400 text-sm">
                &copy; {{ new Date().getFullYear() }} paShaman. Все права защищены.
            </div>
        </div>
    </div>
</template>