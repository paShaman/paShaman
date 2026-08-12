const pending = new Set();

let sweepScheduled = false;

// После полной загрузки страницы (load) браузер уже восстановил позицию скролла
// при обновлении. Прокидываем один проход: всё, что сейчас в поле зрения,
// раскрываем сразу — иначе элементы остаются opacity:0 до первого скролла.
function runSweep() {
    const viewportHeight = window.innerHeight;

    pending.forEach((el) => {
        if (!el.isConnected) {
            pending.delete(el);
            return;
        }

        const rect = el.getBoundingClientRect();
        if (rect.top < viewportHeight && rect.bottom > 0) {
            pending.delete(el);
            el._revealHandler?.();
        }
    });
}

function scheduleSweep() {
    if (sweepScheduled) return;
    sweepScheduled = true;

    const onReady = () => {
        // Двойной RAF: layout уже готов, а восстановленный скролл применён.
        requestAnimationFrame(() => requestAnimationFrame(runSweep));
    };

    if (document.readyState === 'complete') {
        onReady();
    } else {
        window.addEventListener('load', onReady, { once: true });
    }
}

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
            if (el.classList.contains('is-revealed')) return;
            el.classList.add('is-revealed');
            el.addEventListener('transitionend', () => {
                el.classList.remove('reveal');
                el.style.removeProperty('--reveal-delay');
            }, { once: true });
            observer.disconnect();
            pending.delete(el);
        };

        // threshold 0 + нижний rootMargin: блок раскрывается, как только начинает
        // входить во вьюпорт, а не когда видна его заметная доля (иначе высокие
        // блоки — например ProjectInfo — оставляют пустоту при загрузке/скролле).
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    reveal();
                }
            });
        }, { threshold: 0, rootMargin: '0px 0px -40px 0px' });

        el._revealObserver = observer;
        el._revealHandler = reveal;
        pending.add(el);
        scheduleSweep();
        observer.observe(el);
    },
    unmounted(el) {
        el._revealObserver?.disconnect();
        pending.delete(el);
        delete el._revealHandler;
    },
};
