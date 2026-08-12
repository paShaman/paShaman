import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { initLenis, getLenis } from './composables/useLenis';
import reveal from './directives/reveal';
import '../css/app.css';

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        return pages[`./Pages/${name}.vue`]();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .directive('reveal', reveal)
            .mount(el);

        initLenis();
    },
    progress: {
        color: '#e07a5f',
    },
});

router.on('navigate', () => {
    const lenis = getLenis();
    if (!lenis) return;

    // Inertia уже сбросил скролл (обычная навигация) или восстановил
    // сохранённую позицию (back/forward). Синхронизируем Lenis с реальной позицией,
    // иначе его внутренний таргет вернёт скролл на старое место.
    lenis.scrollTo(window.scrollY, { immediate: true });
});

// После обновления страницы браузер восстанавливает позицию скролла асинхронно —
// уже после инициализации Lenis. Синхронизируем таргет, чтобы Lenis не считал
// скролл нулевым и не «уводил» страницу вверх при первом же скролле.
window.addEventListener('load', () => {
    const lenis = getLenis();
    if (!lenis) return;
    lenis.scrollTo(window.scrollY, { immediate: true });
});
