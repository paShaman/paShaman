export default {
    mounted(el, binding) {
        el.classList.add('reveal');

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            el.classList.add('is-revealed');
            return;
        }

        const delay = binding.value?.delay ?? 0;
        el.style.setProperty('--reveal-delay', `${delay}ms`);

        const reveal = () => {
            el.classList.add('is-revealed');
            el.addEventListener('transitionend', () => {
                el.classList.remove('reveal');
                el.style.removeProperty('--reveal-delay');
            }, { once: true });
            observer.disconnect();
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    reveal();
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

        el._revealObserver = observer;
        observer.observe(el);
    },
    unmounted(el) {
        el._revealObserver?.disconnect();
    },
};
