/**
 * Renders Vyomika Atelier LLP homepage from site-content.json into preview.html
 */
(function () {
  'use strict';

  function fmt(n) {
    if (n == null) return '';
    return '₹' + Number(n).toLocaleString('en-IN');
  }

  function productCard(p) {
    const url = `/shop/${p.slug || ''}`;
    const badge = p.badge
      ? `<span class="am-product-card__badge ${p.badge === 'NEW' ? 'am-product-card__badge--new' : ''}">${p.badge}</span>`
      : '';
    const old = p.compare_price ? `<span class="am-product-card__price-old">${fmt(p.compare_price)}</span>` : '';
    return `<article class="am-product-card" data-product-url="${url}">
      <a href="${url}" class="am-product-card__thumb">
        ${badge}
        <img src="${p.image}" alt="${p.name}" loading="lazy">
        <div class="am-product-card__actions">
          <form action="/cart/add/${p.slug || ''}" method="POST" class="am-product-card__buy-form"><input type="hidden" name="_token" value="preview"><input type="hidden" name="quantity" value="1"><input type="hidden" name="buy_now" value="1"><button type="submit" class="am-btn am-btn--primary am-btn--sm am-btn--full">Buy Now</button></form>
        </div>
      </a>
      <div class="am-product-card__body">
        <h3 class="am-product-card__name"><a href="${url}">${p.name}</a></h3>
        <div class="am-product-card__stars" aria-hidden="true">★★★★★</div>
        <div class="am-product-card__price">
          <span class="am-product-card__price-current">${fmt(p.price)}</span>${old}
        </div>
      </div>
    </article>`;
  }

  function trustIcon(icon) {
    const icons = {
      shipping: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 6h13v10H1zM14 9h4l3 3v4h-7V9z"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg>',
      delivery: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
      returns: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 7v6h6M21 17a9 9 0 00-15-6.7L3 13"/></svg>',
      support: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2a7 7 0 00-7 7v3a3 3 0 003 3h1v-6H7a5 5 0 019.9-1M12 22v-4M8 22h8"/></svg>',
      discount: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2l3 7h7l-5.5 4.5L18 21l-6-4-6 4 1.5-7.5L2 9h7z"/></svg>',
    };
    return icons[icon] || icons.discount;
  }

  function calculatorHtml(featured) {
    const rate = featured.rate_per_sqft || 1800;
    const name = featured.name || 'PVD Partition';
    return `<div class="am-calculator va-calculator" data-rate="${rate}">
      <p class="am-calculator__label">Sq Ft Calculator</p>
      <h3 class="am-calculator__title">Estimate your partition</h3>
      <p class="am-calculator__hint va-rate-hint">Length × Height × ₹${Number(rate).toLocaleString('en-IN')}/sq ft</p>
      <div class="am-calculator__units">
        <button type="button" class="am-calculator__unit va-unit-btn active" data-unit="ft-in">Ft-In</button>
        <button type="button" class="am-calculator__unit va-unit-btn" data-unit="mm">MM</button>
        <button type="button" class="am-calculator__unit va-unit-btn" data-unit="cm">CM</button>
      </div>
      <div class="am-calculator__dims">
        <div class="am-calculator__field"><label>Length</label>
          <div class="va-dim-ft-in va-dim-group"><div class="am-calculator__dim-row">
            <input type="number" min="0" class="am-input va-len-ft" value="10"><input type="number" min="0" max="11" class="am-input va-len-in" value="0">
          </div></div>
          <div class="va-dim-mm va-dim-group hidden"><input type="number" min="0" class="am-input va-len-mm" value="3048"></div>
          <div class="va-dim-cm va-dim-group hidden"><input type="number" min="0" class="am-input va-len-cm" value="304.8"></div>
        </div>
        <div class="am-calculator__field"><label>Height</label>
          <div class="va-dim-ft-in va-dim-group"><div class="am-calculator__dim-row">
            <input type="number" min="0" class="am-input va-hgt-ft" value="8"><input type="number" min="0" max="11" class="am-input va-hgt-in" value="0">
          </div></div>
          <div class="va-dim-mm va-dim-group hidden"><input type="number" min="0" class="am-input va-hgt-mm" value="2438"></div>
          <div class="va-dim-cm va-dim-group hidden"><input type="number" min="0" class="am-input va-hgt-cm" value="243.8"></div>
        </div>
      </div>
      <div class="am-calculator__result">
        <div class="am-calculator__result-row"><span>Area</span><span class="va-area-display">80.00 sq ft</span></div>
        <div class="am-calculator__result-row am-calculator__result-row--price"><span>Estimated</span><span class="va-price-display">₹1,44,000</span></div>
      </div>
      <button type="button" class="am-btn am-btn--primary am-btn--full va-order-btn" data-service-slug="partitions" data-design-slug="" data-service-name="${name}">Order Now</button>
    </div>`;
  }

  function render(data) {
    const hero = data.hero?.slides || [];
    const bs = data.best_sellers || {};
    const banner = bs.banner || {};
    const trending = data.trending || {};
    const spotlights = data.spotlights || {};
    const cta = data.cta_band || {};
    const testimonials = data.testimonials || [];
    const blog = data.blog || {};
    const trust = data.trust_badges || [];

    const main = document.getElementById('am-main');
    if (!main) return;

    main.innerHTML = `
<section class="am-hero" data-hero-variant="${document.documentElement.dataset.hero || 'fullscreen'}">
  <div class="am-hero__slides">
    ${hero.map((s, i) => `
    <div class="am-hero__slide ${i === 0 ? 'is-active' : ''}">
      <div class="am-hero__content">
        <p class="am-hero__kicker">${s.kicker || ''}</p>
        <h1 class="am-hero__title">${s.title || ''}</h1>
        <p class="am-hero__desc">${s.description || ''}</p>
        <a href="${s.cta_href || '/shop'}" class="am-btn am-btn--primary am-btn--lg">${s.cta_label || 'Shop Now'}</a>
      </div>
      <div class="am-hero__image"><img src="${s.image}" alt="${s.title}" ${i === 0 ? 'fetchpriority="high"' : 'loading="lazy"'}></div>
    </div>`).join('')}
  </div>
  <div class="am-hero__dots">${hero.map((_, i) => `<button type="button" class="am-hero__dot ${i === 0 ? 'is-active' : ''}" aria-label="Slide ${i + 1}"></button>`).join('')}</div>
</section>

<section class="am-section am-section--white am-section--edge">
  <div class="am-section__intro">
    <div class="am-section-head am-section-head--row">
      <div>
        <h2>${bs.title || 'Best-Selling Products'}</h2>
        <p>${bs.subtitle || ''}</p>
      </div>
      <a href="/shop" class="am-section-head__link">${bs.cta_label || 'View All'}</a>
    </div>
  </div>
  <div class="am-section__body">
    <div class="am-product-grid am-product-grid--with-banner">
      <a href="${banner.href || '/shop'}" class="am-product-banner">
        <img src="${banner.image}" alt="${banner.title}" loading="lazy">
        <h3>${banner.title}</h3><p>${banner.subtitle}</p>
        <span class="am-btn am-btn--white am-btn--sm">${banner.cta || 'Shop now'}</span>
      </a>
      ${(bs.products || []).map(productCard).join('')}
    </div>
  </div>
</section>

<section class="am-section am-section--edge">
  <div class="am-section__body">
    <div class="am-cat-grid">
      ${(data.category_banners || []).map(c => `
      <a href="${c.href}" class="am-cat-tile">
        <img src="${c.image}" alt="${c.title}" loading="lazy">
        <h3>${c.title}</h3><p>${c.subtitle}</p>
        <span class="am-btn am-btn--white am-btn--sm">${c.cta}</span>
      </a>`).join('')}
    </div>
  </div>
</section>

<section class="am-section am-section--white am-section--edge">
  <div class="am-section__intro">
    <div class="am-section-head">
      <h2>${trending.title || ''}</h2><p>${trending.subtitle || ''}</p>
    </div>
  </div>
  <div class="am-section__body">
    <div class="am-product-grid am-product-grid--4">
      ${(trending.products || []).map(productCard).join('')}
    </div>
  </div>
</section>

<section class="am-section am-section--edge">
  <div class="am-section__intro">
    <div class="am-section-head">
      <h2>${spotlights.title || ''}</h2><p>${spotlights.subtitle || ''}</p>
    </div>
  </div>
  <div class="am-section__body">
    <div class="am-spotlight-grid">
      ${(spotlights.items || []).map(item => `
      <div class="am-spotlight">
        <div class="am-spotlight__image"><img src="${item.image}" alt="${item.title}" loading="lazy"></div>
        <div class="am-spotlight__body">
          <h3>${item.title}</h3><p>${item.description}</p>
          <p class="am-spotlight__price">${fmt(item.price)} <span style="font-weight:400;font-size:0.85rem;color:var(--am-muted)">${item.price_unit || ''}</span></p>
          <a href="${item.href}" class="am-btn am-btn--primary">${item.cta}</a>
        </div>
      </div>`).join('')}
    </div>
  </div>
</section>

<section class="am-cta-band">
  <h2>${cta.title || ''}</h2><p>${cta.description || ''}</p>
  <a href="${cta.cta_href || '/shop'}" class="am-btn am-btn--primary am-btn--lg">${cta.cta_label || 'Shop'}</a>
</section>

<section class="am-section am-testimonials">
  <div class="am-container">
    <div class="am-section-head"><h2>What Our Customers Say</h2><p>Real stories from architects, designers, and homeowners across India.</p></div>
    <div class="am-testimonial-slider">
      ${testimonials.map((t, i) => `
      <div class="am-testimonial-slide ${i === 0 ? 'is-active' : ''}">
        <p class="am-testimonial-quote">"${t.quote}"</p>
        <p class="am-testimonial-author">${t.client}</p>
        <p class="am-testimonial-role">${t.role}</p>
      </div>`).join('')}
      <div class="am-testimonial-dots">
        ${testimonials.map((_, i) => `<button type="button" class="am-testimonial-dot ${i === 0 ? 'is-active' : ''}"></button>`).join('')}
      </div>
    </div>
  </div>
</section>

<section class="am-section am-section--white am-section--edge">
  <div class="am-section__intro">
    <div class="am-section-head"><h2>${blog.title || ''}</h2><p>${blog.subtitle || ''}</p></div>
  </div>
  <div class="am-section__body">
    <div class="am-blog-grid">
      ${(blog.posts || []).map(p => `
      <article class="am-blog-card">
        <a href="/blog/${p.slug}">
          <div class="am-blog-card__thumb"><img src="${p.image}" alt="${p.title}" loading="lazy"></div>
          <div class="am-blog-card__body">
            <div class="am-blog-card__meta"><span class="am-blog-cat">${p.category}</span><span>${p.date}</span></div>
            <h3 class="am-blog-card__title">${p.title}</h3>
            <p class="am-blog-card__excerpt">${p.excerpt}</p>
          </div>
        </a>
      </article>`).join('')}
    </div>
  </div>
</section>

<section class="am-trust">
  <div class="am-trust-grid">
      ${trust.map(b => `
      <div class="am-trust-item">${trustIcon(b.icon)}<h4>${b.title}</h4><p>${b.text}</p></div>`).join('')}
  </div>
</section>`;

    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function load() {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'data/site-content.json', true);
    xhr.onload = function () {
      if (xhr.status >= 200 && xhr.status < 300) {
        try { render(JSON.parse(xhr.responseText)); } catch (e) { console.error(e); }
      }
    };
    xhr.onerror = function () { console.error('Could not load site-content.json'); };
    xhr.send();
  }

  window.AmPreview = { render, load, fmt, productCard, calculatorHtml, trustIcon };

  if (!window.AmPreviewRouter) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', load);
    } else {
      load();
    }
  }
})();
