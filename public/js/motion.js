(function () {
    'use strict';

    /* Header: transparent on hero, solid on scroll */
    const header = document.querySelector('.va-header');
    const hero = document.querySelector('.va-luxe-hero');
    if (header && hero) {
        const onScroll = () => {
            header.classList.toggle('va-header--solid', window.scrollY > 60);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    } else if (header) {
        header.classList.add('va-header--solid');
    }

    /* Scroll reveal */
    const revealEls = document.querySelectorAll('.va-reveal, .va-reveal-stagger > *');
    if (revealEls.length && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('va-revealed');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
        revealEls.forEach((el) => observer.observe(el));
    } else {
        revealEls.forEach((el) => el.classList.add('va-revealed'));
    }
})();
