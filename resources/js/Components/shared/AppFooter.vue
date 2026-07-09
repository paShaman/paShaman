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
    <footer class="relative">
        <!-- Wave divider -->
        <div class="wave-divider" aria-hidden="true">
            <svg viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" fill="#ffffff"/>
            </svg>
        </div>

        <!-- Footer content -->
        <div class="bg-warm-surface pt-14 pb-8">
            <div class="container mx-auto px-4 sm:px-6">
                <!-- Social icons -->
                <div class="flex justify-center gap-4 sm:gap-5 mb-10">
                    <a
                        v-for="social in socials"
                        :key="social.id"
                        :href="social.url"
                        target="_blank"
                        class="flex items-center justify-center w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-warm-bg text-text-muted hover:bg-accent-terracotta hover:text-white transition-all duration-300 shadow-sm hover:shadow-md hover:-translate-y-1 cursor-pointer"
                        :title="social.icon"
                    >
                        <component :is="iconComponent(social.icon)" class="w-5 sm:w-6 h-5 sm:h-6" />
                    </a>
                </div>

                <!-- Copyright -->
                <div class="text-center text-text-muted text-sm">
                    &copy; {{ new Date().getFullYear() }} paShaman. Все права защищены.
                </div>
            </div>
        </div>
    </footer>
</template>