import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import '../css/app.css';

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        return pages[`./Pages/${name}.vue`]();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#e07a5f',
    },
    scrollBehavior() {
        // Всегда сбрасываем скролл при навигации
        return [0, 0];
    },
});
