/**
 * Vyomika Atelier LLP — Amerce storefront interactions
 */
(function () {
  'use strict';

  /* Hero carousel */
  function initHero() {
    const slides = document.querySelectorAll('.am-hero__slide');
    const dots = document.querySelectorAll('.am-hero__dot');
    if (!slides.length) return;

    let current = 0;
    let timer;

    function goTo(i) {
      slides[current]?.classList.remove('is-active');
      dots[current]?.classList.remove('is-active');
      current = (i + slides.length) % slides.length;
      slides[current]?.classList.add('is-active');
      dots[current]?.classList.add('is-active');
    }

    function next() { goTo(current + 1); }

    dots.forEach((dot, i) => {
      dot.addEventListener('click', () => { goTo(i); resetTimer(); });
    });

    function resetTimer() {
      clearInterval(timer);
      timer = setInterval(next, 6000);
    }

    resetTimer();
  }

  /* Testimonial slider */
  function initTestimonials() {
    const slides = document.querySelectorAll('.am-testimonial-slide');
    const dots = document.querySelectorAll('.am-testimonial-dot');
    if (!slides.length) return;

    let current = 0;

    function goTo(i) {
      slides[current]?.classList.remove('is-active');
      dots[current]?.classList.remove('is-active');
      current = (i + slides.length) % slides.length;
      slides[current]?.classList.add('is-active');
      dots[current]?.classList.add('is-active');
    }

    dots.forEach((dot, i) => dot.addEventListener('click', () => goTo(i)));
    setInterval(() => goTo(current + 1), 7000);
  }

  /* Mobile menu */
  let mobileNavBound = false;
  function initMobileNav() {
    const toggle = document.getElementById('am-menu-toggle');
    const close = document.getElementById('am-menu-close');
    const nav = document.getElementById('am-mobile-nav');
    const overlay = document.getElementById('am-overlay');
    if (!toggle || !nav) return;
    if (mobileNavBound) return;
    mobileNavBound = true;

    const isMobileViewport = () => window.matchMedia('(max-width: 1023px)').matches;

    const open = () => {
      if (!isMobileViewport()) return;
      nav.classList.add('is-open');
      document.body.classList.add('am-menu-open');
      document.body.style.overflow = 'hidden';
      toggle.setAttribute('aria-expanded', 'true');
    };
    const shut = () => {
      nav.classList.remove('is-open');
      document.body.classList.remove('am-menu-open');
      document.body.style.overflow = '';
      toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', () => {
      if (nav.classList.contains('is-open')) shut();
      else open();
    });
    close?.addEventListener('click', shut);
    overlay?.addEventListener('click', () => {
      if (nav.classList.contains('is-open')) shut();
    });
    nav.querySelectorAll('a').forEach(a => a.addEventListener('click', shut));
    nav.querySelectorAll('[data-am-nav-toggle]').forEach(btn => {
      btn.addEventListener('click', () => {
        const expanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        btn.nextElementSibling?.classList.toggle('is-open', !expanded);
      });
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && nav.classList.contains('is-open')) shut();
    });

    window.matchMedia('(min-width: 1024px)').addEventListener('change', (e) => {
      if (e.matches) shut();
    });
  }

  /* Search bar */
  function initSearch() {
    const btn = document.getElementById('am-search-toggle');
    const bar = document.getElementById('am-search-bar');
    const close = document.getElementById('am-search-close');
    if (!btn || !bar) return;

    btn.addEventListener('click', () => {
      bar.classList.add('is-open');
      bar.querySelector('input')?.focus();
    });
    close?.addEventListener('click', () => bar.classList.remove('is-open'));
  }

  /* Cart drawer */
  function initCartDrawer() {
    const openBtn = document.getElementById('am-cart-toggle');
    const drawer = document.getElementById('am-cart-drawer');
    const overlay = document.getElementById('am-overlay');
    const closeBtn = document.getElementById('am-cart-close');
    if (!drawer) return;

    const open = () => {
      drawer.classList.add('is-open');
      overlay?.classList.add('is-open');
      document.body.style.overflow = 'hidden';
    };
    const shut = () => {
      drawer.classList.remove('is-open');
      overlay?.classList.remove('is-open');
      document.body.style.overflow = '';
    };

    openBtn?.addEventListener('click', (e) => { e.preventDefault(); open(); });
    closeBtn?.addEventListener('click', shut);
    overlay?.addEventListener('click', shut);
  }

  /* Order Now on product cards — go to product page */
  function initOrderNow() {
    document.querySelectorAll('[data-order-now]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const url = btn.dataset.productUrl || btn.closest('.am-product-card')?.dataset.productUrl;
        if (url) window.location.href = url;
      });
    });
  }

  /* Buy Now / Add to Bag forms must not bubble to product links */
  let buyNowFormsBound = false;
  function initBuyNowForms() {
    if (buyNowFormsBound) return;
    buyNowFormsBound = true;
    document.addEventListener('click', (e) => {
      const inBuyForm = e.target.closest('.am-product-card__buy-form, .am-design-gallery__buy-form, .am-pdp-buy__form');
      const inCardActions = e.target.closest('.am-product-card__actions, .am-design-gallery__actions');
      if (inBuyForm || inCardActions) {
        e.stopPropagation();
      }
    }, true);
  }

  /* Quick view modal */
  function initQuickView() {
    const modal = document.getElementById('am-quickview');
    const overlay = document.getElementById('am-overlay');
    const closeBtn = document.getElementById('am-quickview-close');
    if (!modal) return;

    document.querySelectorAll('[data-quickview]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const card = btn.closest('.am-product-card');
        if (!card) return;
        const name = card.querySelector('.am-product-card__name')?.textContent || '';
        const price = card.querySelector('.am-product-card__price-current')?.textContent || '';
        const img = card.querySelector('img')?.src || '';
        modal.querySelector('[data-qv-name]').textContent = name;
        modal.querySelector('[data-qv-price]').textContent = price;
        const imgEl = modal.querySelector('[data-qv-img]');
        if (imgEl) { imgEl.src = img; imgEl.alt = name; }
        const linkEl = modal.querySelector('[data-qv-link]');
        const productUrl = card.dataset.productUrl;
        if (linkEl && productUrl) linkEl.href = productUrl;
        modal.classList.add('is-open');
        overlay?.classList.add('is-open');
        document.body.style.overflow = 'hidden';
      });
    });

    const shut = () => {
      modal.classList.remove('is-open');
      if (!document.getElementById('am-cart-drawer')?.classList.contains('is-open')) {
        overlay?.classList.remove('is-open');
        document.body.style.overflow = '';
      }
    };
    closeBtn?.addEventListener('click', shut);
  }

  /* Size selector */
  function initSizeOptions() {
    document.querySelectorAll('.am-size-options').forEach(group => {
      group.querySelectorAll('.am-size-opt').forEach(opt => {
        opt.addEventListener('click', () => {
          group.querySelectorAll('.am-size-opt').forEach(o => o.classList.remove('is-active'));
          opt.classList.add('is-active');
        });
      });
    });
  }

  /* Sticky header shadow */
  function initHeaderScroll() {
    const header = document.querySelector('.am-header');
    if (!header) return;
    window.addEventListener('scroll', () => {
      header.style.boxShadow = window.scrollY > 10 ? 'var(--am-shadow)' : 'none';
    }, { passive: true });
  }

  /* PVD finish swatches */
  function initFinishSwatches() {
    function applyFinish(rate, name, isBlack) {
      document.querySelectorAll('.va-calculator').forEach((calc) => {
        calc.dataset.rate = rate;
        calc.querySelectorAll('.va-rate-hint').forEach((el) => {
          el.textContent = 'Length × Height × ₹' + Number(rate).toLocaleString('en-IN') + '/sq ft';
        });
      });
      document.querySelectorAll('[data-sqft-rate-display]').forEach((el) => {
        el.textContent = '₹' + Number(rate).toLocaleString('en-IN');
      });
      document.querySelectorAll('[data-sqft-black-note]').forEach((note) => {
        note.hidden = !isBlack;
      });
      document.dispatchEvent(new CustomEvent('am-recalc-calculators'));
    }

    document.querySelectorAll('[data-pdp-finish]').forEach((root) => {
      const label = root.querySelector('[data-finish-label]');
      const active = root.querySelector('[data-finish-slug].is-active') || root.querySelector('[data-finish-slug]');
      if (active) {
        document.querySelectorAll('[data-finish-input="slug"]').forEach((input) => {
          input.value = active.getAttribute('data-finish-slug') || '';
        });
        applyFinish(
          Number(active.dataset.finishRate),
          active.dataset.finishName || '',
          active.dataset.finishBlack === '1'
        );
      }

      root.querySelectorAll('[data-finish-slug]').forEach((btn) => {
        btn.addEventListener('click', () => {
          root.querySelectorAll('[data-finish-slug]').forEach((b) => {
            b.classList.remove('is-active');
            b.setAttribute('aria-selected', 'false');
          });
          btn.classList.add('is-active');
          btn.setAttribute('aria-selected', 'true');
          if (label) label.textContent = btn.getAttribute('data-finish-name') || '';
          document.querySelectorAll('[data-finish-input="slug"]').forEach((input) => {
            input.value = btn.getAttribute('data-finish-slug') || '';
          });
          applyFinish(
            Number(btn.dataset.finishRate),
            btn.dataset.finishName || '',
            btn.dataset.finishBlack === '1'
          );
        });
      });
    });
  }

  /* Product / service description tabs */
  function initProductTabs() {
    document.querySelectorAll('[data-am-tabs]').forEach((root) => {
      const tabs = root.querySelectorAll('[data-am-tab]');
      const panels = root.querySelectorAll('[data-am-panel]');
      tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
          const id = tab.getAttribute('data-am-tab');
          tabs.forEach((t) => {
            const active = t === tab;
            t.classList.toggle('is-active', active);
            t.setAttribute('aria-selected', active ? 'true' : 'false');
          });
          panels.forEach((panel) => {
            const active = panel.getAttribute('data-am-panel') === id;
            panel.classList.toggle('is-active', active);
            panel.hidden = !active;
          });
        });
      });
    });
  }

  function initAboutReveal() {
    const els = document.querySelectorAll('.am-reveal:not([data-reveal-bound])');
    if (!els.length) return;

    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    els.forEach((el) => {
      el.dataset.revealBound = '1';
      observer.observe(el);
    });
  }

  function initAboutLightbox() {
    const modal = document.getElementById('am-about-lightbox');
    if (!modal || modal.dataset.bound === '1') return;
    modal.dataset.bound = '1';

    const imgEl = document.getElementById('am-about-lightbox-img');
    const captionEl = document.getElementById('am-about-lightbox-caption');
    let items = [];
    let index = 0;

    function collectItems() {
      items = Array.from(document.querySelectorAll('[data-about-lightbox]'));
    }

    function show(i) {
      collectItems();
      if (!items.length) return;
      index = (i + items.length) % items.length;
      const btn = items[index];
      if (imgEl) {
        imgEl.src = btn.getAttribute('data-src') || '';
        imgEl.alt = btn.getAttribute('aria-label') || '';
      }
      if (captionEl) captionEl.textContent = btn.getAttribute('data-caption') || '';
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    }

    function close() {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      if (imgEl) imgEl.src = '';
    }

    document.addEventListener('click', (e) => {
      const trigger = e.target.closest('[data-about-lightbox]');
      if (trigger) {
        e.preventDefault();
        collectItems();
        const idx = items.indexOf(trigger);
        show(idx >= 0 ? idx : 0);
        return;
      }
      if (e.target.closest('[data-about-lightbox-close]') || e.target === modal) {
        e.preventDefault();
        close();
      }
      if (e.target.closest('[data-about-lightbox-prev]')) {
        e.preventDefault();
        show(index - 1);
      }
      if (e.target.closest('[data-about-lightbox-next]')) {
        e.preventDefault();
        show(index + 1);
      }
    });

    document.addEventListener('keydown', (e) => {
      if (!modal.classList.contains('is-open')) return;
      if (e.key === 'Escape') close();
      if (e.key === 'ArrowLeft') show(index - 1);
      if (e.key === 'ArrowRight') show(index + 1);
    });
  }

  function initPopupFormModal() {
    const modal = document.getElementById('va-order-modal');
    if (!modal || modal.dataset.bound === '1') return;

    const titleEl = document.getElementById('am-popup-form-title');
    const subtitleEl = modal.querySelector('[data-popup-subtitle]');
    const typeInput = document.getElementById('am-popup-type');
    const subjectInput = document.getElementById('va-modal-subject');
    const contextField = modal.querySelector('[data-popup-context-field]');
    const orderSummary = modal.querySelector('[data-popup-order-summary]');
    const productLabel = document.getElementById('va-modal-product');

    function setField(id, value) {
      const el = document.getElementById(id);
      if (el) el.value = value ?? '';
    }

    function clearOrderFields() {
      ['va-modal-service-slug', 'va-modal-design-slug', 'va-modal-price', 'va-modal-dimensions', 'va-modal-unit', 'va-modal-finish'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.value = '';
      });
      const dim = document.getElementById('va-modal-dim-display');
      const price = document.getElementById('va-modal-price-display');
      const svc = modal.querySelector('.va-modal-service-label');
      if (dim) dim.textContent = '';
      if (price) price.textContent = '';
      if (svc) svc.textContent = '';
    }

    function openModal(config) {
      if (titleEl) titleEl.textContent = config.title || 'Submit your requirement';
      if (subtitleEl) {
        subtitleEl.textContent = config.subtitle || '';
        subtitleEl.hidden = !config.subtitle;
      }
      if (typeInput) typeInput.value = config.type || 'service_inquiry';
      if (subjectInput) subjectInput.value = config.subject || '';
      if (contextField) contextField.value = config.context || '';
      if (productLabel) productLabel.value = config.context || '';
      if (orderSummary) orderSummary.hidden = !config.showOrderSummary;
      if (!config.showOrderSummary) clearOrderFields();
      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
      const form = modal.querySelector('#va-order-form');
      if (window.vaFormProtection && typeof window.vaFormProtection.refreshFormLoadedAt === 'function') {
        window.vaFormProtection.refreshFormLoadedAt(form);
      }
      modal.querySelector('#va-modal-name, input[name="name"]')?.focus();
    }

    function formatInr(value) {
      const n = Number(String(value).replace(/[^\d.]/g, ''));
      if (!Number.isFinite(n) || n <= 0) return '';
      return '₹' + n.toLocaleString('en-IN', { maximumFractionDigits: 0 });
    }

    function readGalleryOrderDataset(btn) {
      return {
        name: btn.getAttribute('data-product-name') || '',
        slug: btn.getAttribute('data-product-slug') || '',
        serviceSlug: btn.getAttribute('data-service-slug') || '',
        finish: btn.getAttribute('data-finish') || '',
        price: btn.getAttribute('data-price') || '',
        category: btn.getAttribute('data-category') || '',
        popupType: btn.getAttribute('data-popup-type') || 'order_now',
      };
    }

    function openGalleryOrder(btn) {
      const data = readGalleryOrderDataset(btn);
      const parts = [data.name];
      if (data.category) parts.push(data.category);
      const context = parts.filter(Boolean).join(' — ') || 'Product enquiry';
      const priceLabel = formatInr(data.price);
      openModal({
        title: 'Complete your order',
        subtitle: priceLabel ? `Listed from ${priceLabel}` : (data.category || ''),
        type: data.popupType === 'order_now' ? 'order_now' : data.popupType,
        subject: data.name ? `${data.name} — Order Request` : 'Order Request',
        context,
        showOrderSummary: false,
      });
      setField('va-modal-service-slug', data.serviceSlug);
      setField('va-modal-design-slug', data.slug);
      if (data.price) setField('va-modal-price', data.price);
      if (data.finish) setField('va-modal-finish', data.finish);
    }

    function close() {
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      const form = document.getElementById('va-order-form');
      if (form) {
        form.reset();
        delete form.dataset.submitting;
        const submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) submitBtn.disabled = false;
      }
      clearOrderFields();
      if (typeInput) typeInput.value = 'order_now';
      if (contextField) contextField.value = '';
      if (productLabel) productLabel.value = '';
      if (subtitleEl) {
        subtitleEl.textContent = '';
        subtitleEl.hidden = true;
      }
      if (orderSummary) orderSummary.hidden = true;
    }

    const form = document.getElementById('va-order-form');
    if (form && form.dataset.submitBound !== '1') {
      form.dataset.submitBound = '1';
      form.addEventListener('submit', (ev) => {
        if (form.dataset.submitting === '1') {
          ev.preventDefault();
          return;
        }
        form.dataset.submitting = '1';
        const submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
      });
    }

    document.addEventListener('click', (e) => {
      const orderPopupBtn = e.target.closest('[data-open-order-popup], [data-open-contact-studio][data-popup-type="order_now"]');
      if (orderPopupBtn) {
        e.preventDefault();
        e.stopPropagation();
        openGalleryOrder(orderPopupBtn);
        return;
      }

      const contactBtn = e.target.closest('[data-open-contact-studio]');
      if (contactBtn && contactBtn.getAttribute('data-popup-type') !== 'order_now') {
        e.preventDefault();
        e.stopPropagation();
        const context = contactBtn.getAttribute('data-contact-context') || '';
        openModal({
          title: 'Enquire about this piece',
          subtitle: context,
          type: 'service_inquiry',
          subject: context || 'Studio contact',
          context: context || 'General studio enquiry',
          showOrderSummary: false,
        });
        return;
      }

      const projectBtn = e.target.closest('[data-open-project-enquiry]');
      if (projectBtn) {
        e.preventDefault();
        e.stopPropagation();
        const title = projectBtn.getAttribute('data-project-title') || '';
        openModal({
          title: 'Inquire about a similar project',
          subtitle: title ? `Reference: ${title}` : '',
          type: 'service_inquiry',
          subject: title ? `Similar to: ${title}` : 'Similar project enquiry',
          context: title || 'Similar project enquiry',
          showOrderSummary: false,
        });
        return;
      }

      if (e.target.closest('[data-close-popup-form]')) {
        e.preventDefault();
        close();
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modal.classList.contains('open')) close();
    });

    window.AmPopupForm = { openModal, close };
    modal.dataset.bound = '1';
  }

  function initCheckoutPayMethods() {
    document.querySelectorAll('[data-pay-methods]').forEach((root) => {
      if (root.dataset.bound === '1') return;
      root.dataset.bound = '1';
      const sync = () => {
        root.querySelectorAll('.am-pay-method').forEach((label) => {
          const input = label.querySelector('input[type="radio"]');
          label.classList.toggle('is-active', Boolean(input?.checked));
        });
      };
      root.addEventListener('change', sync);
      sync();
    });
  }

  function initAddressForms() {
    document.querySelectorAll('.am-address-form').forEach((form) => {
      if (form.dataset.nameBound === '1') return;
      form.dataset.nameBound = '1';
      const hidden = form.querySelector('#am-addr-full-name');
      const first = form.querySelector('#am-addr-first');
      const last = form.querySelector('#am-addr-last');
      const update = () => {
        if (!hidden || !first || !last) return;
        hidden.value = [first.value.trim(), last.value.trim()].filter(Boolean).join(' ');
      };
      first?.addEventListener('input', update);
      last?.addEventListener('input', update);
      form.addEventListener('submit', update);
      update();
    });

    document.querySelectorAll('[data-country-select]').forEach((select) => {
      if (select.dataset.countryBound === '1') return;
      select.dataset.countryBound = '1';
      const grid = select.closest('.am-address-form__grid');
      const otherWrap = grid?.querySelector('[data-country-other-wrap]');
      const otherInput = grid?.querySelector('[name="country_other"]');
      const toggle = () => {
        const isOther = select.value === 'Other';
        if (otherWrap) otherWrap.hidden = !isOther;
        if (otherInput) otherInput.required = isOther;
      };
      select.addEventListener('change', toggle);
      toggle();
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    initPopupFormModal();
    initHero();
    initTestimonials();
    initMobileNav();
    initSearch();
    initCartDrawer();
    initOrderNow();
    initBuyNowForms();
    initQuickView();
    initSizeOptions();
    initHeaderScroll();
    initProductTabs();
    initFinishSwatches();
    initAboutReveal();
    initAboutLightbox();
    initCheckoutPayMethods();
    initAddressForms();
  });

  document.addEventListener('am-content-ready', () => {
    initPopupFormModal();
    initHero();
    initTestimonials();
    initOrderNow();
    initBuyNowForms();
    initQuickView();
    initSizeOptions();
    initProductTabs();
    initFinishSwatches();
    initAboutReveal();
    initAboutLightbox();
    initCheckoutPayMethods();
    initAddressForms();
  });
})();
