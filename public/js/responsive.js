/**
 * Vyomika Atelier — minimal responsive utilities (matchMedia, no UA sniffing)
 */
(function () {
  'use strict';

  var resizeTimer = null;
  var focusScrollTimer = null;

  function debounce(fn, ms) {
    return function () {
      var args = arguments;
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        fn.apply(null, args);
      }, ms);
    };
  }

  function setViewportHeight() {
    var vh = window.innerHeight * 0.01;
    document.documentElement.style.setProperty('--am-vh-px', vh + 'px');
    document.documentElement.style.setProperty('--am-vh', window.innerHeight + 'px');
  }

  function scrollFieldIntoView(el) {
    if (!el || typeof el.scrollIntoView !== 'function') return;
    var narrow = window.matchMedia('(max-width: 767px)').matches;
    if (!narrow) return;

    clearTimeout(focusScrollTimer);
    focusScrollTimer = setTimeout(function () {
      try {
        el.scrollIntoView({ block: 'center', behavior: 'smooth' });
      } catch (e) {
        el.scrollIntoView(true);
      }

      if (window.visualViewport) {
        var vv = window.visualViewport;
        var rect = el.getBoundingClientRect();
        var bottomGap = vv.height - (rect.bottom - vv.offsetTop);
        if (bottomGap < 80) {
          window.scrollBy({ top: 80 - bottomGap, behavior: 'smooth' });
        }
      }
    }, 300);
  }

  function initFocusScroll() {
    document.addEventListener(
      'focusin',
      function (e) {
        var t = e.target;
        if (!t) return;
        var tag = t.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
          scrollFieldIntoView(t);
        }
      },
      true
    );
  }

  function initAdminTables() {
    document.querySelectorAll('.admin-main table').forEach(function (table) {
      if (table.closest('.admin-table-wrap')) return;
      var wrap = document.createElement('div');
      wrap.className = 'admin-table-wrap';
      table.parentNode.insertBefore(wrap, table);
      wrap.appendChild(table);
    });
  }

  function initAdminNav() {
    var toggle = document.getElementById('admin-menu-toggle');
    var sidebar = document.getElementById('admin-sidebar');
    var backdrop = document.getElementById('admin-sidebar-backdrop');
    if (!toggle || !sidebar) return;

    var close = function () {
      sidebar.classList.remove('is-open');
      backdrop?.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    };

    var open = function () {
      sidebar.classList.add('is-open');
      backdrop?.classList.add('is-open');
      toggle.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden';
    };

    toggle.addEventListener('click', function () {
      if (sidebar.classList.contains('is-open')) close();
      else open();
    });

    backdrop?.addEventListener('click', close);
    sidebar.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        if (window.matchMedia('(max-width: 1023px)').matches) close();
      });
    });

    window.matchMedia('(min-width: 1024px)').addEventListener('change', function (e) {
      if (e.matches) close();
    });
  }

  function initFooterAccordion() {
    var footer = document.querySelector('.am-footer');
    if (!footer || footer.dataset.footerAccordionBound === 'true') return;
    footer.dataset.footerAccordionBound = 'true';

    var mq = window.matchMedia('(max-width: 1023px)');
    var storageKey = 'am-footer-accordion';

    function readState() {
      try {
        return JSON.parse(sessionStorage.getItem(storageKey) || '{}');
      } catch (e) {
        return {};
      }
    }

    function writeState(id, open) {
      try {
        var state = readState();
        if (open) state[id] = true;
        else delete state[id];
        sessionStorage.setItem(storageKey, JSON.stringify(state));
      } catch (e) { /* ignore */ }
    }

    function setPanel(btn, open) {
      var panelId = btn.getAttribute('aria-controls');
      var panel = panelId ? document.getElementById(panelId) : btn.nextElementSibling;
      if (!panel) return;
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      panel.hidden = !open;
    }

    function closeAll(exceptBtn) {
      footer.querySelectorAll('[data-am-footer-toggle]').forEach(function (btn) {
        if (btn !== exceptBtn) setPanel(btn, false);
      });
    }

    footer.querySelectorAll('[data-am-footer-toggle]').forEach(function (btn) {
      var panelId = btn.getAttribute('aria-controls');
      var saved = readState();
      if (mq.matches && panelId && saved[panelId]) {
        setPanel(btn, true);
      }

      btn.addEventListener('click', function () {
        if (!mq.matches) return;
        var open = btn.getAttribute('aria-expanded') !== 'true';
        if (open) closeAll(btn);
        setPanel(btn, open);
        if (panelId) writeState(panelId, open);
      });
    });

    mq.addEventListener('change', function (e) {
      if (e.matches) return;
      footer.querySelectorAll('[data-am-footer-toggle]').forEach(function (btn) {
        setPanel(btn, false);
      });
    });
  }

  function scrollToElement(el) {
    if (!el || typeof el.getBoundingClientRect !== 'function') return;
    var styles = window.getComputedStyle(document.documentElement);
    var headerH = parseFloat(styles.getPropertyValue('--am-header-h')) || 72;
    var announceH = parseFloat(styles.getPropertyValue('--am-announce-h')) || 0;
    var offset = headerH + announceH + 16;
    var top = el.getBoundingClientRect().top + window.scrollY - offset;
    window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
  }

  function initInPageAnchorScroll() {
    document.addEventListener('click', function (e) {
      var link = e.target.closest('a[href^="#"]');
      if (!link) return;
      var hash = link.getAttribute('href');
      if (!hash || hash === '#') return;
      var id = hash.slice(1);
      var target = document.getElementById(id);
      if (!target) return;
      e.preventDefault();
      scrollToElement(target);
      if (history.pushState) {
        history.pushState(null, '', hash);
      } else {
        window.location.hash = hash;
      }
    });

    function scrollOnLoad() {
      if (!window.location.hash || window.location.hash.length < 2) return;
      var target = document.getElementById(window.location.hash.slice(1));
      if (target) {
        setTimeout(function () {
          scrollToElement(target);
        }, 100);
      }
    }

    scrollOnLoad();
    window.addEventListener('hashchange', scrollOnLoad);
  }

  function initDoubleSubmitGuard() {
    document.addEventListener(
      'submit',
      function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.noGuard === 'true') return;
        var btn = form.querySelector('[type="submit"]');
        if (!btn || btn.disabled) {
          e.preventDefault();
          return;
        }
        btn.disabled = true;
        setTimeout(function () {
          btn.disabled = false;
        }, 4000);
      },
      true
    );
  }

  setViewportHeight();
  window.addEventListener('resize', debounce(setViewportHeight, 150));
  window.addEventListener('orientationchange', debounce(setViewportHeight, 200));
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', debounce(setViewportHeight, 150));
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initFocusScroll();
      initInPageAnchorScroll();
      initAdminNav();
      initAdminTables();
      initFooterAccordion();
      initDoubleSubmitGuard();
    });
  } else {
    initFocusScroll();
    initInPageAnchorScroll();
    initAdminNav();
    initAdminTables();
    initFooterAccordion();
    initDoubleSubmitGuard();
  }
})();
