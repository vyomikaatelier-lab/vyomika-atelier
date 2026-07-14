(function () {
    'use strict';

    /* Scroll reveal — CreativeCo-style staggered fade-up */
    const revealEls = document.querySelectorAll('.va-reveal, .va-reveal-stagger > *');
    if (revealEls.length && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('va-revealed');
                        observer.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
        );
        revealEls.forEach((el) => observer.observe(el));
    } else {
        revealEls.forEach((el) => el.classList.add('va-revealed'));
    }

    /* Fanned card stack — spreads on hover (Dribbble CreativeCo pattern) */
    const fan = document.querySelector('.va-card-fan');
    if (fan) {
        const cards = fan.querySelectorAll('.va-fan-card');
        cards.forEach((card, i) => {
            card.style.setProperty('--fan-i', i);
            card.style.setProperty('--fan-n', cards.length);
        });

        fan.addEventListener('mouseenter', () => fan.classList.add('va-fan-open'));
        fan.addEventListener('mouseleave', () => fan.classList.remove('va-fan-open'));

        /* Touch: tap to toggle */
        fan.addEventListener('click', (e) => {
            if (window.matchMedia('(hover: none)').matches) {
                e.preventDefault();
                fan.classList.toggle('va-fan-open');
            }
        });
    }

    /* Hero headline — stagger words on load */
    const heroTitle = document.querySelector('.va-hero-title');
    if (heroTitle && !heroTitle.dataset.split) {
        heroTitle.dataset.split = '1';
        const accent = heroTitle.querySelector('.va-text-accent');
        const html = heroTitle.innerHTML;
        heroTitle.innerHTML = html;
        heroTitle.classList.add('va-hero-ready');
    }

    /* Smooth counter tick for stat numbers */
    document.querySelectorAll('[data-count]').forEach((el) => {
        const target = parseInt(el.dataset.count, 10);
        if (isNaN(target)) return;
        const obs = new IntersectionObserver(([entry]) => {
            if (!entry.isIntersecting) return;
            obs.disconnect();
            let current = 0;
            const step = Math.max(1, Math.floor(target / 40));
            const tick = () => {
                current += step;
                if (current >= target) {
                    el.textContent = target + (el.dataset.suffix || '');
                    return;
                }
                el.textContent = current + (el.dataset.suffix || '');
                requestAnimationFrame(tick);
            };
            tick();
        }, { threshold: 0.5 });
        obs.observe(el);
    });

    /* Parallax subtle on hero stage */
    const stage = document.querySelector('.va-hero-stage');
    if (stage && window.matchMedia('(prefers-reduced-motion: no-preference)').matches) {
        window.addEventListener('scroll', () => {
            const y = window.scrollY;
            if (y < window.innerHeight) {
                stage.style.transform = `translateY(${y * 0.06}px)`;
            }
        }, { passive: true });
    }
})();
