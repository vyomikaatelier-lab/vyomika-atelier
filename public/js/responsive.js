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
      initAdminNav();
      initAdminTables();
      initDoubleSubmitGuard();
    });
  } else {
    initFocusScroll();
    initAdminNav();
    initAdminTables();
    initDoubleSubmitGuard();
  }
})();
