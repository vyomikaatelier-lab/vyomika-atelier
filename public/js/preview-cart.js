/**
 * Local cart for static preview (localStorage).
 */
(function () {
  'use strict';

  const KEY = 'ssmetal-cart';

  function read() {
    try {
      return JSON.parse(localStorage.getItem(KEY) || '[]');
    } catch {
      return [];
    }
  }

  function write(items) {
    localStorage.setItem(KEY, JSON.stringify(items));
    syncUi();
  }

  function fmt(n) {
    return '₹' + Number(n).toLocaleString('en-IN');
  }

  function count() {
    return read().reduce((sum, i) => sum + (i.qty || 1), 0);
  }

  function add(product, qty) {
    const items = read();
    const n = Math.max(1, Number(qty) || 1);
    const existing = items.find((i) => i.slug === product.slug);
    if (existing) {
      existing.qty += n;
    } else {
      items.push({
        slug: product.slug,
        name: product.name,
        price: product.price,
        image: product.image,
        qty: n,
      });
    }
    write(items);
  }

  function remove(slug) {
    write(read().filter((i) => i.slug !== slug));
  }

  function setQty(slug, qty) {
    const items = read();
    const item = items.find((i) => i.slug === slug);
    if (!item) return;
    if (qty < 1) {
      remove(slug);
      return;
    }
    item.qty = qty;
    write(items);
  }

  function subtotal() {
    return read().reduce((sum, i) => sum + i.price * i.qty, 0);
  }

  function syncUi() {
    const n = count();
    document.querySelectorAll('[data-cart-count]').forEach((el) => {
      el.textContent = String(n);
      el.classList.toggle('is-empty', n === 0);
    });

    const body = document.querySelector('#am-cart-drawer .am-drawer__body');
    if (!body) return;

    const items = read();
    if (!items.length) {
      body.innerHTML = '<p style="text-align:center;padding:2rem 0;color:var(--am-muted)">Your cart is currently empty.</p>';
      return;
    }

    body.innerHTML = items.map((i) => `
      <div class="am-drawer-item" data-cart-slug="${i.slug}">
        <img src="${i.image}" alt="" class="am-drawer-item__img">
        <div class="am-drawer-item__body">
          <a href="/shop/${i.slug}" class="am-drawer-item__name">${i.name}</a>
          <p class="am-drawer-item__price">${fmt(i.price)} × ${i.qty}</p>
        </div>
        <button type="button" class="am-drawer-item__remove" data-remove-cart="${i.slug}" aria-label="Remove">✕</button>
      </div>`).join('') + `
      <p class="am-drawer-subtotal"><span>Subtotal</span><strong>${fmt(subtotal())}</strong></p>`;
  }

  document.addEventListener('click', (e) => {
    const rm = e.target.closest('[data-remove-cart]');
    if (rm) {
      e.preventDefault();
      remove(rm.getAttribute('data-remove-cart'));
    }
  });

  document.addEventListener('am-content-ready', syncUi);
  document.addEventListener('DOMContentLoaded', syncUi);

  window.AmPreviewCart = { add, remove, setQty, read, count, subtotal, fmt };
})();
