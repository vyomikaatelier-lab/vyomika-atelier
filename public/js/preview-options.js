/**
 * Live preview: color themes + hero layout switcher (preview.html only)
 */
(function () {
  'use strict';

  const THEME_KEY = 'ssmetal-theme';
  const HERO_KEY = 'ssmetal-hero';
  const VERSION_KEY = 'ssmetal-defaults-version';
  const SITE_DEFAULTS_VERSION = '5'; /* bump when canonical theme/hero changes */
  const DEFAULT_THEME = 'atelier';
  const DEFAULT_HERO = 'fullscreen';

  /** High-key bright palettes — listed first in preview bar */
  const BRIGHT_THEMES = ['bright-warm', 'bright-fresh', 'bright-sunny'];

  function applySiteDefaults() {
    try {
      if (localStorage.getItem(VERSION_KEY) !== SITE_DEFAULTS_VERSION) {
        localStorage.setItem(THEME_KEY, DEFAULT_THEME);
        localStorage.setItem(HERO_KEY, DEFAULT_HERO);
        localStorage.setItem(VERSION_KEY, SITE_DEFAULTS_VERSION);
      }
    } catch (_) { /* ignore */ }
  }

  function setTheme(theme) {
    document.documentElement.dataset.theme = theme;
    try { localStorage.setItem(THEME_KEY, theme); } catch (_) { /* ignore */ }
    document.querySelectorAll('[data-theme-set]').forEach((btn) => {
      btn.classList.toggle('is-active', btn.dataset.themeSet === theme);
    });
    if (BRIGHT_THEMES.includes(theme)) {
      const btn = document.querySelector(`[data-theme-set="${theme}"]`);
      btn?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
    }
  }

  function setHero(hero) {
    document.documentElement.dataset.hero = hero;
    const el = document.querySelector('.am-hero');
    if (el) el.dataset.heroVariant = hero;
    try { localStorage.setItem(HERO_KEY, hero); } catch (_) { /* ignore */ }
    document.querySelectorAll('[data-hero-set]').forEach((btn) => {
      btn.classList.toggle('is-active', btn.dataset.heroSet === hero);
    });
  }

  function initFromStorage() {
    applySiteDefaults();
    let theme = DEFAULT_THEME;
    let hero = DEFAULT_HERO;
    try {
      theme = localStorage.getItem(THEME_KEY) || DEFAULT_THEME;
      if (theme === 'corten-bottle') theme = 'corten-final';
      hero = localStorage.getItem(HERO_KEY) || DEFAULT_HERO;
    } catch (_) { /* ignore */ }
    setTheme(theme);
    setHero(hero);
  }

  function bindControls() {
    document.querySelectorAll('[data-theme-set]').forEach((btn) => {
      btn.addEventListener('click', () => setTheme(btn.dataset.themeSet));
    });
    document.querySelectorAll('[data-hero-set]').forEach((btn) => {
      btn.addEventListener('click', () => setHero(btn.dataset.heroSet));
    });
  }

  document.addEventListener('am-content-ready', () => {
    const hero = document.documentElement.dataset.hero || DEFAULT_HERO;
    setHero(hero);
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      initFromStorage();
      bindControls();
    });
  } else {
    initFromStorage();
    bindControls();
  }
})();
