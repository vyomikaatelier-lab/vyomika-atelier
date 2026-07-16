/**
 * Multi-page static preview router (shop, product, cart, inner pages).
 */
(function () {
  'use strict';

  window.AmPreviewRouter = true;

  const CATEGORIES = [
    { slug: '', name: 'All Products' },
    { slug: 'partitions', name: 'PVD Partitions', keys: ['partition', 'fluted', 'divider', 'laser', 'panel'] },
    { slug: 'corner-tables', name: 'Corner Tables', keys: ['corner'] },
    { slug: 'coffee-tables', name: 'Coffee Tables', keys: ['coffee', 'console'] },
    { slug: 'glass-tables', name: 'Glass Tables', keys: ['glass'] },
    { slug: 'door-handles', name: 'Door Handles', keys: ['handle', 'pull'] },
    { slug: 'metal-furniture', name: 'Metal Furniture', keys: ['rack', 'door', 'furniture'] },
  ];

  const CHECKOUT_CATEGORIES = ['mirror-frames', 'coffee-tables', 'corner-tables', 'glass-tables', 'door-handles'];
  const CALC_CATEGORIES = ['partitions', 'fluted-panels', 'room-dividers'];

  const FINISH_SWATCHES = [
    { slug: 'gold-mirror', name: 'Gold Mirror', hex: '#D4AF37', rate: 1800, is_black: false },
    { slug: 'gold-brush', name: 'Gold Brush', hex: '#C5A028', rate: 1800, is_black: false },
    { slug: 'rose-gold-mirror', name: 'Rose Gold Mirror', hex: '#B76E79', rate: 1800, is_black: false },
    { slug: 'rose-gold-brush', name: 'Rose Gold Brush', hex: '#A85A65', rate: 1800, is_black: false },
    { slug: 'champagne-mirror', name: 'Champagne Mirror', hex: '#C9A86C', rate: 1800, is_black: false },
    { slug: 'champagne-brush', name: 'Champagne Brush', hex: '#B8956A', rate: 1800, is_black: false },
    { slug: 'black-mirror', name: 'Black Mirror', hex: '#1A1A1A', rate: 2340, is_black: true },
    { slug: 'black-brush', name: 'Black Brush', hex: '#2C2C2C', rate: 2340, is_black: true },
  ];
  const BLACK_SQFT_RATE = 2340;

  function finishSwatchesHtml() {
    const def = FINISH_SWATCHES.find((s) => s.slug === 'champagne-mirror') || FINISH_SWATCHES[0];
    return `<div class="am-pdp-finish" data-pdp-finish data-base-rate="1800">
      <label class="am-pdp-finish__label">PVD Finish: <span class="am-pdp-finish__value" data-finish-label>${def.name}</span></label>
      <div class="am-pdp-finish__swatches" role="listbox" aria-label="Select PVD finish">
        ${FINISH_SWATCHES.map((s) => `
        <button type="button" class="am-pdp-finish__swatch ${s.slug === def.slug ? 'is-active' : ''}" role="option"
          aria-selected="${s.slug === def.slug ? 'true' : 'false'}" aria-label="${s.name}"
          data-finish-slug="${s.slug}" data-finish-name="${s.name}" data-finish-rate="${s.rate}"
          data-finish-black="${s.is_black ? '1' : '0'}" style="--swatch-color: ${s.hex}"
          title="${s.name}${s.is_black ? ' (+30%)' : ''}">
          <img src="images/finishes/${s.slug}.jpg" alt="" class="am-pdp-finish__swatch-img"
            data-finish-fallback="images/finishes/${s.slug}.svg"
            onerror="if(this.dataset.fallback){this.onerror=null;this.src=this.dataset.fallback}">
        </button>`).join('')}
      </div>
      <p class="am-pdp-finish__note">Black Mirror &amp; Black Brush: +30% on sq ft rate</p>
    </div>`;
  }

  let siteData = null;
  let legalData = null;
  let cortenData = null;
  let professionalsData = null;
  let railingsData = null;
  let mirrorFramesData = null;
  let aboutData = null;
  let blogData = null;

  const LEGAL_PATHS = {
    'privacy-policy': 'privacy',
    'terms-and-conditions': 'terms',
    'shipping-delivery-policy': 'shipping',
    'cancellation-refund-policy': 'cancellation',
    'warranty-returns-policy': 'warranty',
    'contact-grievance-policy': 'grievance',
    privacy: 'privacy',
    terms: 'terms',
    shipping: 'shipping',
    'shipping-returns': 'shipping',
  };

  const LEGAL_REDIRECTS = {
    '/privacy': '/privacy-policy',
    '/terms': '/terms-and-conditions',
    '/shipping-returns': '/shipping-delivery-policy',
    '/shipping': '/shipping-delivery-policy',
  };

  function fmt(n) {
    return window.AmPreview?.fmt(n) ?? ('₹' + Number(n).toLocaleString('en-IN'));
  }

  function productCard(p) {
    return window.AmPreview?.productCard(p) ?? '';
  }

  function pageHero(label, title, subtitle) {
    return `<section class="am-page-hero">
      <div class="am-container">
        <p class="am-page-hero__label">${label}</p>
        <h1 class="am-page-hero__title">${title}</h1>
        ${subtitle ? `<p class="am-page-hero__subtitle">${subtitle}</p>` : ''}
      </div>
    </section>`;
  }

  function breadcrumbsLegal(items) {
    return `<nav class="am-breadcrumbs am-breadcrumbs--legal" aria-label="Breadcrumb">
      <div class="am-container">
        ${items.map((item, i) => {
          if (i > 0) return `<span class="am-breadcrumbs__sep">/</span>${item.url && i < items.length - 1 ? `<a href="${item.url}">${item.label}</a>` : `<span${i === items.length - 1 ? ' aria-current="page"' : ''}>${item.label}</span>`}`;
          return item.url && i < items.length - 1 ? `<a href="${item.url}">${item.label}</a>` : `<span${i === items.length - 1 ? ' aria-current="page"' : ''}>${item.label}</span>`;
        }).join('')}
      </div>
    </nav>`;
  }

  function interpolateLegal(text) {
    if (!legalData?.business) return text;
    return text.replace(/\{\{(\w+)\}\}/g, (_, key) => legalData.business[key] ?? `{{${key}}}`);
  }

  function formatLegalParagraph(text) {
    return interpolateLegal(text).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  }

  function renderLegal(pageKey) {
    const page = legalData?.pages?.[pageKey];
    if (!page) {
      renderNotFound('/legal/' + pageKey);
      return;
    }
    const metaTitle = page.meta_title || page.title;
    setTitle(metaTitle);
    if (page.meta_description) {
      let meta = document.querySelector('meta[name="description"]');
      if (!meta) {
        meta = document.createElement('meta');
        meta.name = 'description';
        document.head.appendChild(meta);
      }
      meta.content = page.meta_description;
    }
    const lastUpdated = legalData.last_updated || '';
    const business = legalData.business || {};
    const sectionsHtml = (page.sections || []).map((section) => `
        <h2 class="am-legal-prose__heading">${section.heading}</h2>
        ${(section.paragraphs || []).map((p) => `<p>${formatLegalParagraph(p)}</p>`).join('')}
      `).join('');

    document.getElementById('am-main').innerHTML = `
${breadcrumbsLegal([
  { label: 'Home', url: '/' },
  { label: 'Legal', url: '/privacy-policy' },
  { label: page.title },
])}
${pageHero('Legal', page.title, lastUpdated ? 'Last updated: ' + lastUpdated : '')}
<section class="am-page-body am-page-body--narrow am-legal-page">
  <div class="am-container">
    <div class="am-prose am-legal-prose">
      ${sectionsHtml}
      <div class="am-legal-prose__cta">
        <p>Questions? <a href="/contact">Contact us</a> or email
          <a href="mailto:${business.email || ''}">${business.email || '[email]'}</a>.</p>
      </div>
    </div>
  </div>
</section>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function breadcrumbs(items) {
    return `<nav class="am-breadcrumbs" aria-label="Breadcrumb">
      ${items.map((item, i) => {
        if (i === items.length - 1) return `<span aria-current="page">${item.label}</span>`;
        return `<a href="${item.url}">${item.label}</a><span class="am-breadcrumbs__sep">/</span>`;
      }).join('')}
    </nav>`;
  }

  function collectProducts(data) {
    const map = new Map();
    const add = (p) => {
      if (!p?.slug) return;
      if (!map.has(p.slug)) {
        map.set(p.slug, {
          slug: p.slug,
          name: p.name,
          price: p.price,
          compare_price: p.compare_price ?? null,
          badge: p.badge ?? null,
          image: p.image,
          category: p.category || 'PVD Partitions',
          description: p.description || p.desc || 'Precision stainless PVD fabrication with Pan-India delivery.',
          sku: p.sku || 'SSM-' + p.slug.toUpperCase().slice(0, 8),
        });
      }
    };
    partitionGalleryProducts().forEach(add);
    slimDoorGalleryProducts().forEach(add);
    entranceDoorGalleryProducts().forEach(add);
    rackGalleryProducts().forEach(add);
    furnitureGalleryProducts().forEach(add);
    mirrorGalleryProducts().forEach(add);
    (data.best_sellers?.products || []).forEach(add);
    (data.trending?.products || []).forEach(add);
    if (data.featured_product) add(data.featured_product);
    return Array.from(map.values());
  }

  function partitionGalleryProducts() {
    const patterns = ['Wave', 'Fluted', 'Laser-Cut', 'Geometric', 'Herringbone', 'Ripple', 'Arc', 'Linear', 'Mesh', 'Perforated', 'Crescent', 'Zigzag', 'Lattice', 'Slat', 'Pleat'];
    const finishes = ['Champagne', 'Rose Gold', 'Matte Black', 'Gold Mirror', 'Champagne Brush'];
    const types = ['Partition', 'Panel', 'Screen', 'Divider'];
    const categories = ['PVD Partitions', 'Fluted Panels', 'Room Dividers'];
    const images = [
      'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
      'https://www.delhiduniya.com/vyomika/images/shop/product/big/722414.jpeg',
      'https://www.vyomikaatelier.com/assets/campaign-partitions.jpeg',
      'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=800&q=80',
      'https://images.unsplash.com/photo-1497366216548-37526070297c?w=800&q=80',
    ];
    const featured = [
      { name: 'Champagne Wave Partition', slug: 'champagne-wave-partition', category: 'PVD Partitions', price: 28999, compare_price: 38999, image: images[0], description: 'Precision stainless wave partition with champagne PVD finish.' },
      { name: 'Veil Fluted Panel', slug: 'veil-fluted-panel', category: 'Fluted Panels', price: 24999, compare_price: null, image: images[1], description: 'Vertical fluted PVD panel with soft light diffusion.' },
      { name: 'Rose Gold Room Divider', slug: 'rose-gold-room-divider', category: 'Room Dividers', price: 32999, compare_price: 42999, image: images[2], description: 'Statement rose gold PVD room divider.' },
      { name: 'Matte Black PVD Partition', slug: 'matte-black-pvd-partition', category: 'PVD Partitions', price: 26999, compare_price: 35999, image: images[3], description: 'Bold matte black partition with PVD coating.' },
      { name: 'Laser-Cut Partition', slug: 'laser-cut-partition', category: 'PVD Partitions', price: 31999, compare_price: 41999, image: images[0], description: 'Custom laser-cut stainless partition patterns.' },
    ];
    const items = [...featured];
    let i = items.length;
    for (const pattern of patterns) {
      for (const finish of finishes) {
        if (items.length >= 40) return items;
        const type = types[i % types.length];
        const name = `${finish} ${pattern} ${type}`;
        const baseSlug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        items.push({
          name,
          slug: `${baseSlug}-${String(i + 1).padStart(2, '0')}`,
          category: categories[i % categories.length],
          price: 22000 + (i % 12) * 1500,
          compare_price: i % 3 === 0 ? 32000 + i * 200 : null,
          image: images[i % images.length],
          description: `Custom ${pattern.toLowerCase()} PVD ${type.toLowerCase()} in ${finish.toLowerCase()} finish.`,
        });
        i++;
      }
    }
    return items;
  }

  function generateGenericGalleryProducts({ featured, patterns, finishes, types, categories, images, skuPrefix, target = 40 }) {
    const items = [...featured];
    let i = items.length;
    for (const pattern of patterns) {
      for (const finish of finishes) {
        if (items.length >= target) return items;
        const type = types[i % types.length];
        const name = `${finish} ${pattern} ${type}`;
        const baseSlug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        items.push({
          name,
          slug: `${baseSlug}-${String(i + 1).padStart(2, '0')}`,
          category: categories[i % categories.length],
          price: 18000 + (i % 14) * 2000,
          compare_price: i % 3 === 0 ? 28000 + i * 250 : null,
          image: images[i % images.length],
          description: `Custom ${pattern.toLowerCase()} ${type.toLowerCase()} in ${finish.toLowerCase()} PVD finish.`,
          sku: skuPrefix + String(i + 1).padStart(3, '0'),
        });
        i++;
      }
    }
    return items;
  }

  const doorImages = [
    'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&q=80',
    'https://images.unsplash.com/photo-1600607687644-c7171b42498f?w=800&q=80',
    'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=800&q=80',
    'https://images.unsplash.com/photo-1497366216548-37526070297c?w=800&q=80',
  ];
  const rackImages = [
    'https://images.unsplash.com/photo-1615529182904-896166571fac?w=800&q=80',
    'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?w=800&q=80',
    'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=800&q=80',
  ];
  const furnitureImages = [
    'https://images.unsplash.com/photo-1532372320572-127d86b32558?w=800&q=80',
    'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=800&q=80',
    'https://images.unsplash.com/photo-1617806118233-18e1de247200?w=800&q=80',
  ];

  function slimDoorGalleryProducts() {
    return generateGenericGalleryProducts({
      featured: [
        { name: 'Slim Profile Pivot Door', slug: 'slim-profile-pivot-door', category: 'Metal Furniture', price: 45999, compare_price: 59999, image: doorImages[0], description: 'Ultra-slim pivot entrance with concealed PVD frame.' },
        { name: 'Slim Sliding Patio Door', slug: 'slim-sliding-patio-door', category: 'Metal Furniture', price: 52999, compare_price: 64999, image: doorImages[1], description: 'Minimal-track sliding door for indoor-outdoor flow.' },
        { name: 'Slim Hinged Suite Door', slug: 'slim-hinged-suite-door', category: 'Metal Furniture', price: 48999, compare_price: null, image: doorImages[2], description: 'Premium hinged suite door with PVD frame.' },
      ],
      patterns: ['Pivot', 'Sliding', 'Hinged', 'Folding', 'Stacking', 'Frameless', 'Glass', 'Double', 'Single', 'Arched'],
      finishes: ['Champagne', 'Rose Gold', 'Matte Black', 'Gold Mirror', 'Brushed Brass'],
      types: ['Door', 'Entrance', 'Portal', 'System', 'Suite'],
      categories: ['Metal Furniture'],
      images: doorImages,
      skuPrefix: 'SSM-SD',
    });
  }

  function entranceDoorGalleryProducts() {
    return generateGenericGalleryProducts({
      featured: [
        { name: 'Slim Profile Door', slug: 'slim-profile-door', category: 'Metal Furniture', price: 45999, compare_price: 59999, image: doorImages[0], description: 'Grand main entrance PVD door system.' },
        { name: 'PVD Door Pull Handle', slug: 'pvd-door-pull-handle', category: 'Door Handles', price: 2400, compare_price: 3200, image: doorImages[2], description: 'Slim profile pull handle in PVD finishes.' },
        { name: 'Brass Entrance Pull', slug: 'brass-entrance-pull', category: 'Door Handles', price: 3200, compare_price: null, image: doorImages[3], description: 'Statement entrance pull in brushed brass PVD.' },
      ],
      patterns: ['Entrance', 'Main', 'Grand', 'Pivot', 'Double', 'Single', 'Security', 'Glass', 'Panel', 'Monumental'],
      finishes: ['Champagne', 'Rose Gold', 'Matte Black', 'Bronze', 'Gold Mirror'],
      types: ['Door', 'Entrance', 'Portal', 'Gate', 'System'],
      categories: ['Metal Furniture', 'Door Handles'],
      images: doorImages,
      skuPrefix: 'SSM-ED',
    });
  }

  function rackGalleryProducts() {
    return generateGenericGalleryProducts({
      featured: [
        { name: 'Wall Rack System', slug: 'wall-rack-system', category: 'Metal Furniture', price: 12500, compare_price: 16500, image: rackImages[0], description: 'Modular wall-mounted PVD display rack.' },
        { name: 'Freestanding Wine Rack', slug: 'freestanding-wine-rack', category: 'Metal Furniture', price: 18900, compare_price: null, image: rackImages[1], description: 'Freestanding wine storage in PVD metal.' },
        { name: 'Retail Display Rack', slug: 'retail-display-rack', category: 'Metal Furniture', price: 22400, compare_price: 28900, image: rackImages[2], description: 'Retail shelving rack with champagne PVD finish.' },
      ],
      patterns: ['Wall', 'Floating', 'Modular', 'Wine', 'Display', 'Shelf', 'Ladder', 'Grid', 'Boutique', 'Gallery'],
      finishes: ['Champagne', 'Rose Gold', 'Matte Black', 'Gold Mirror', 'Brushed Brass'],
      types: ['Rack', 'Shelf', 'System', 'Unit', 'Storage'],
      categories: ['Metal Furniture'],
      images: rackImages,
      skuPrefix: 'SSM-RK',
    });
  }

  function furnitureGalleryProducts() {
    return generateGenericGalleryProducts({
      featured: [
        { name: 'Brushed Brass Coffee Table', slug: 'brushed-brass-coffee-table', category: 'Coffee Tables', price: 18900, compare_price: null, image: furnitureImages[0], description: 'Bespoke brass PVD coffee table.' },
        { name: 'Marble Top Corner Table', slug: 'marble-top-corner-table', category: 'Corner Tables', price: 16500, compare_price: null, image: furnitureImages[1], description: 'Corner table with marble top and PVD frame.' },
        { name: 'Rose Gold Glass Side Table', slug: 'rose-gold-glass-side-table', category: 'Glass Tables', price: 14200, compare_price: 18900, image: furnitureImages[2], description: 'Glass side table with rose gold PVD frame.' },
        { name: 'Gold Fluted Console', slug: 'gold-fluted-console', category: 'Metal Furniture', price: 22400, compare_price: 29999, image: furnitureImages[1], description: 'Fluted console table for entryways.' },
      ],
      patterns: ['Coffee', 'Console', 'Side', 'Corner', 'Glass', 'Nested', 'Accent', 'Entry', 'Lounge', 'Statement'],
      finishes: ['Champagne', 'Rose Gold', 'Matte Black', 'Brass', 'Bronze'],
      types: ['Table', 'Console', 'Desk', 'Stand', 'Piece'],
      categories: ['Coffee Tables', 'Corner Tables', 'Glass Tables', 'Metal Furniture'],
      images: furnitureImages,
      skuPrefix: 'SSM-BF',
    });
  }

  function mirrorGalleryProducts() {
    const catalog = mirrorFramesData?.products || [];
    const bySlug = Object.fromEntries(catalog.map((p) => [p.slug, p]));
    return (mirrorFramesData?.designs || []).map((design) => {
      const row = bySlug[design.product_slug] || {};
      return {
        name: design.name,
        slug: design.product_slug || design.slug,
        category: 'Mirror Frames',
        price: row.price ?? 0,
        compare_price: row.compare_price ?? null,
        badge: design.badge ?? null,
        image: design.image || row.image,
        description: design.description || row.desc || row.description || '',
        sku: row.sku || 'SSM-MIR-' + (design.slug || '').slice(0, 6).toUpperCase(),
        design_slug: design.slug,
      };
    });
  }

  const SERVICE_GALLERY_CATALOG = {
    partitions: partitionGalleryProducts,
    'slim-profile-door-system': slimDoorGalleryProducts,
    'main-entrance-pvd-doors': entranceDoorGalleryProducts,
    'rack-systems-metal-pvd': rackGalleryProducts,
    'bespoke-metal-furniture': furnitureGalleryProducts,
  };

  function catalogProductsForService(serviceSlug) {
    const fn = SERVICE_GALLERY_CATALOG[serviceSlug];
    return fn ? fn() : [];
  }

  function serviceGalleryMeta(serviceSlug) {
    const map = {
      partitions: { heading: 'Explore Partition Designs', label: 'partition designs', cta: 'Order Now', action: 'select a style to configure & order' },
      'slim-profile-door-system': { heading: 'Explore Door Designs', label: 'door designs', cta: 'Order Now', action: 'select a style to configure & order' },
      'main-entrance-pvd-doors': { heading: 'Explore Entrance Doors', label: 'door designs', cta: 'Order Now', action: 'select a style to configure & order' },
      'rack-systems-metal-pvd': { heading: 'Explore Rack Designs', label: 'rack designs', cta: 'Order Now', action: 'select a style to configure & order' },
      'bespoke-metal-furniture': { heading: 'Explore Furniture Designs', label: 'furniture designs', cta: 'Order Now', action: 'select a piece to configure & order' },
    };
    return map[serviceSlug] || { heading: 'Design Gallery', label: 'designs', cta: 'Order Now', action: 'click any to order' };
  }

  function serviceProductCardHtml(product, ctaLabel) {
    const actionHtml = usesCheckoutFlow(product)
      ? `<form action="/cart/add/${product.slug}" method="POST" class="am-design-gallery__buy-cta"><input type="hidden" name="_token" value="preview"><input type="hidden" name="quantity" value="1"><input type="hidden" name="buy_now" value="1"><button type="submit" class="am-btn am-btn--primary am-btn--sm am-btn--full">Buy Now</button></form>`
      : ctaLabel === 'Request Quote'
        ? `<a href="/custom-order" class="am-btn am-btn--primary am-btn--sm am-btn--full">Request Quote</a>`
        : `<button type="button" class="am-btn am-btn--primary am-btn--sm am-btn--full" data-open-order-popup data-product-name="${product.name}" data-product-slug="${product.slug}" data-service-slug="${serviceSlugForProduct(product)}">Order Now</button>`;
    return `<article class="am-design-gallery__card">
      <a href="/shop/${product.slug}" class="am-design-gallery__media">
        ${product.image ? `<img src="${product.image}" alt="${product.name}" loading="lazy">` : ''}
      </a>
      <div class="am-design-gallery__body">
        <h3 class="am-design-gallery__name"><a href="/shop/${product.slug}">${product.name}</a></h3>
        <p class="am-design-gallery__cat">${product.category || ''}</p>
        ${actionHtml}
      </div>
    </article>`;
  }

  function renderServiceGallery(slug) {
    const service = getService(slug);
    if (!service) {
      renderNotFound('/services/' + slug);
      return;
    }
    const products = catalogProductsForService(slug);
    const meta = serviceGalleryMeta(slug);
    setTitle(service.name);
    document.getElementById('am-main').innerHTML = `
${pageHero('Service', service.name, products.length + ' ' + meta.label + ' — ' + meta.action)}
<section class="am-page-body am-page-body--gallery-only">
  <div class="am-container">
    <section class="am-design-gallery am-design-gallery--service">
      <p class="am-card__label">Design Gallery</p>
      <h2 class="am-design-gallery__title">${meta.heading}</h2>
      <p class="am-design-gallery__count">${products.length} designs · click any to order</p>
      <div class="am-design-gallery__grid am-design-gallery__grid--dense">
        ${products.map((p) => serviceProductCardHtml(p, meta.cta)).join('')}
      </div>
    </section>
  </div>
</section>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function renderCortenService(service) {
    const page = cortenData || {};
    const hero = page.hero || {};
    setTitle(page.meta_title || service.name);
    const meta = document.querySelector('meta[name="description"]');
    if (meta && page.meta_description) meta.setAttribute('content', page.meta_description);

    const appsHtml = (page.applications?.items || []).map((app) => `
      <article class="am-corten-apps__card">
        <div class="am-corten-apps__media"><img src="${app.image}" alt="${app.name}" loading="lazy"></div>
        <h3 class="am-corten-apps__name">${app.name}</h3>
      </article>`).join('');

    const whyHtml = (page.why?.points || []).map((p) => `<li>${p}</li>`).join('');

    const stages = page.finish_evolution?.stages || [];
    const timelineHtml = stages.map((stage, i) => `
      <div class="am-corten-timeline__step">
        <div class="am-corten-timeline__media"><img src="${stage.image}" alt="${stage.label}" loading="lazy"></div>
        <p class="am-corten-timeline__label">${stage.label}</p>
        ${i < stages.length - 1 ? '<span class="am-corten-timeline__arrow" aria-hidden="true">→</span>' : ''}
      </div>`).join('');

    const processHtml = (page.process?.steps || []).map((step, i) => `
      <li class="am-corten-process__step">
        <span class="am-corten-process__num">${i + 1}</span>
        <span class="am-corten-process__text">${step}</span>
      </li>`).join('');

    const projectsHtml = (page.featured_projects?.items || []).map((p) => {
      const href = p.slug ? `/projects/${p.slug}` : null;
      const tag = href ? 'a' : 'article';
      const cls = href ? 'am-card am-corten-project' : 'am-card am-corten-project am-corten-project--static';
      const open = href ? `<a href="${href}" class="${cls}">` : `<article class="${cls}">`;
      const close = href ? '</a>' : '</article>';
      return `${open}
        <div class="am-card__thumb"><img src="${p.image}" alt="${p.title}" loading="lazy"></div>
        <div class="am-card__body">
          <p class="am-card__label">${p.category || ''}${p.location ? ' · ' + p.location : ''}</p>
          <h3 class="am-card__title">${p.title}</h3>
        </div>${close}`;
    }).join('');

    const techHtml = (page.technical?.options || []).map((o) => `<li>${o}</li>`).join('');
    const considerHtml = (page.considerations?.points || []).map((p) => `<li>${p}</li>`).join('');
    const faqHtml = (page.faq?.items || []).map((item) => `
      <details class="am-corten-faq__item">
        <summary>${item.q}</summary>
        <p>${item.a}</p>
      </details>`).join('');

    const heroImg = hero.image || service.image;

    document.getElementById('am-main').innerHTML = `
<section class="am-corten-hero" style="--corten-hero-img: url('${heroImg}')">
  <div class="am-container am-corten-hero__inner">
    <p class="am-page-hero__label">Corten Steel</p>
    <h1 class="am-corten-hero__title">${hero.title || service.name}</h1>
    <p class="am-corten-hero__subtitle">${hero.subtitle || service.summary}</p>
    <div class="am-corten-hero__actions">
      ${hero.cta_primary ? `<a href="${hero.cta_primary.href}" class="am-btn am-btn--primary">${hero.cta_primary.label}</a>` : ''}
      ${hero.cta_secondary ? `<a href="${hero.cta_secondary.href}" class="am-btn am-btn--outline am-btn--light">${hero.cta_secondary.label}</a>` : ''}
    </div>
  </div>
</section>
<section class="am-section am-section--white">
  <div class="am-container am-corten-intro">
    <h2 class="am-corten-section__title">${page.intro?.title || ''}</h2>
    <p class="am-corten-section__lead">${page.intro?.body || ''}</p>
  </div>
</section>
<section class="am-section am-section--dark" id="corten-applications">
  <div class="am-container">
    <div class="am-section-head am-section-head--left"><h2>${page.applications?.title || 'Applications'}</h2></div>
    <div class="am-corten-apps">${appsHtml}</div>
  </div>
</section>
<section class="am-section am-section--white">
  <div class="am-container am-corten-split">
    <div>
      <h2 class="am-corten-section__title">${page.why?.title || ''}</h2>
      <ul class="am-corten-checklist">${whyHtml}</ul>
    </div>
    <div class="am-corten-split__media"><img src="${service.image}" alt="Corten steel" loading="lazy"></div>
  </div>
</section>
<section class="am-section am-section--dark">
  <div class="am-container">
    <h2 class="am-corten-section__title am-corten-section__title--center">${page.finish_evolution?.title || ''}</h2>
    <div class="am-corten-timeline">${timelineHtml}</div>
    ${page.finish_evolution?.note ? `<p class="am-corten-timeline__note">${page.finish_evolution.note}</p>` : ''}
  </div>
</section>
<section class="am-section am-section--white">
  <div class="am-container">
    <h2 class="am-corten-section__title am-corten-section__title--center">${page.process?.title || ''}</h2>
    <ol class="am-corten-process">${processHtml}</ol>
  </div>
</section>
<section class="am-section am-section--dark">
  <div class="am-container">
    <div class="am-section-head am-section-head--row">
      <div>
        <h2>${page.featured_projects?.title || 'Projects'}</h2>
        ${page.featured_projects?.categories ? `<p>${page.featured_projects.categories.join(' · ')}</p>` : ''}
      </div>
      <a href="/projects" class="am-section-head__link">View all projects →</a>
    </div>
    <div class="am-grid-4 am-corten-projects">${projectsHtml}</div>
  </div>
</section>
<section class="am-section am-section--white">
  <div class="am-container am-corten-split am-corten-split--reverse">
    <div>
      <h2 class="am-corten-section__title">${page.technical?.title || ''}</h2>
      <ul class="am-corten-bullets">${techHtml}</ul>
    </div>
    <div class="am-corten-split__media"><img src="https://images.unsplash.com/photo-1503387762-592deb58ef4e?w=900&q=80" alt="Fabrication" loading="lazy"></div>
  </div>
</section>
<section class="am-section am-section--dark am-corten-consider">
  <div class="am-container">
    <h2 class="am-corten-section__title">${page.considerations?.title || ''}</h2>
    <ul class="am-corten-consider__list">${considerHtml}</ul>
  </div>
</section>
<section class="am-section am-section--white">
  <div class="am-container am-corten-faq-wrap">
    <h2 class="am-corten-section__title am-corten-section__title--center">${page.faq?.title || 'FAQ'}</h2>
    <div class="am-corten-faq">${faqHtml}</div>
  </div>
</section>
<section class="am-section am-section--dark am-corten-cta" id="corten-quote">
  <div class="am-container am-corten-cta__grid">
    <div>
      <h2 class="am-corten-section__title">${page.cta?.title || ''}</h2>
      <p class="am-corten-section__lead">${page.cta?.body || ''}</p>
      ${page.cta?.secondary ? `<a href="${page.cta.secondary.href}" class="am-btn am-btn--outline am-btn--light" style="margin-top:1rem">${page.cta.secondary.label}</a>` : ''}
    </div>
    <aside>${serviceLeadFormHtml(service)}</aside>
  </div>
</section>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function categorySlug(product) {
    const cat = (product.category || '').toLowerCase();
    const slug = (product.slug || '').toLowerCase();
    if (cat.includes('fluted') || slug.includes('fluted')) return 'fluted-panels';
    if (cat.includes('room divider') || cat.includes('divider') || slug.includes('divider')) return 'room-dividers';
    if (cat.includes('door handle') || cat.includes('handle') || slug.includes('handle') || slug.includes('pull')) return 'door-handles';
    if (cat.includes('coffee') || slug.includes('coffee')) return 'coffee-tables';
    if (cat.includes('corner') || slug.includes('corner')) return 'corner-tables';
    if (cat.includes('glass') || slug.includes('glass')) return 'glass-tables';
    if (cat.includes('console') || slug.includes('console')) return 'coffee-tables';
    if ((cat.includes('table') || slug.includes('table')) && !slug.includes('door') && !slug.includes('rack')) return 'coffee-tables';
    if (slug.includes('door') || slug.includes('rack')) return 'metal-furniture';
    if (cat.includes('partition') || slug.includes('partition')) return 'partitions';
    for (const c of CATEGORIES) {
      if (!c.slug || !c.keys) continue;
      if (c.keys.some((k) => cat.includes(k) || slug.includes(k))) return c.slug;
    }
    return '';
  }

  function isFurnitureProduct(product) {
    const slug = (product.slug || '').toLowerCase();
    if (slug.includes('door') || slug.includes('rack')) return false;
    if (['coffee-tables', 'corner-tables', 'glass-tables'].includes(categorySlug(product))) return true;
    return ['coffee', 'table', 'console'].some((k) => slug.includes(k));
  }

  function usesCheckoutFlow(product) {
    return CHECKOUT_CATEGORIES.includes(categorySlug(product));
  }

  function showsSqFtCalculator(product) {
    if (usesCheckoutFlow(product) || isFurnitureProduct(product)) return false;
    const slug = (product.slug || '').toLowerCase();
    const catSlug = categorySlug(product);
    if (CALC_CATEGORIES.includes(catSlug)) return true;
    if (catSlug === 'metal-furniture') return slug.includes('door') || slug.includes('rack');
    if (slug.includes('door') || slug.includes('rack')) return true;
    return false;
  }

  function serviceSlugForProduct(product) {
    const slug = product.slug || '';
    const catSlug = categorySlug(product);
    if (slug.includes('door') || slug.includes('handle')) return 'main-entrance-pvd-doors';
    if (slug.includes('rack')) return 'rack-systems-metal-pvd';
    if (['partitions', 'fluted-panels', 'room-dividers'].includes(catSlug)) return 'partitions';
    if (catSlug === 'door-handles') return 'main-entrance-pvd-doors';
    return 'partitions';
  }

  function estimateLabelForProduct(product) {
    const slug = (product.slug || '').toLowerCase();
    if (slug.includes('door') || slug.includes('handle') || slug.includes('pull')) return 'door';
    if (slug.includes('rack')) return 'display rack';
    const catSlug = categorySlug(product);
    if (catSlug === 'door-handles') return 'door';
    if (['partitions', 'fluted-panels', 'room-dividers'].includes(catSlug)) return 'partition';
    return 'partition';
  }

  function careGuidelinesForProduct(product) {
    const serviceSlug = serviceSlugForProduct(product);
    const service = (siteData.services || []).find((s) => s.slug === serviceSlug);
    return service?.care || [];
  }

  function relatedForProduct(product) {
    const all = collectProducts(siteData);
    const catSlug = categorySlug(product);
    const sameCat = all.filter((p) => p.slug !== product.slug && categorySlug(p) === catSlug);
    if (sameCat.length >= 4) return sameCat.slice(0, 4);
    const rest = all.filter((p) => p.slug !== product.slug && !sameCat.includes(p));
    return sameCat.concat(rest).slice(0, 4);
  }

  function checkoutStepsHtml(current) {
    const steps = [
      { n: 1, label: 'Cart', href: '/cart' },
      { n: 2, label: 'Details', href: '/checkout' },
      { n: 3, label: 'Payment', href: null },
      { n: 4, label: 'Confirmed', href: null },
    ];
    return `<nav class="am-checkout-steps" aria-label="Checkout progress"><ol class="am-checkout-steps__list">${steps.map((s) => {
      const cls = s.n < current ? 'is-complete' : s.n === current ? 'is-current' : 'is-upcoming';
      const inner = `<span class="am-checkout-steps__dot" aria-hidden="true">${s.n}</span><span class="am-checkout-steps__label">${s.label}</span>`;
      const link = s.n < current && s.href
        ? `<a href="${s.href}" class="am-checkout-steps__link">${inner}</a>`
        : `<span class="am-checkout-steps__link"${s.n === current ? ' aria-current="step"' : ''}>${inner}</span>`;
      return `<li class="am-checkout-steps__item ${cls}">${link}</li>`;
    }).join('')}</ol></nav>`;
  }

  function checkoutTrustHtml() {
    return `<div class="am-pdp-checkout-trust">
      <ul class="am-pdp-shipping-notes">
        <li>
          <svg class="am-pdp-shipping-notes__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
          <span>Estimated delivery: <strong>3–4 weeks</strong> from order confirmation. <a href="/shipping-delivery-policy">Shipping details</a></span>
        </li>
      </ul>
      <div class="am-pdp-safe-checkout">
        <p class="am-pdp-safe-checkout__title">Guaranteed Safe Checkout</p>
        <div class="am-payment-logos" aria-label="Accepted payment methods">
          <span class="am-pay-logo am-pay-logo--visa" title="Visa"><svg viewBox="0 0 48 16"><text x="0" y="13" font-family="Arial Black, sans-serif" font-size="14" font-weight="700" fill="#1A1F71">VISA</text></svg></span>
          <span class="am-pay-logo am-pay-logo--mastercard" title="Mastercard"><svg viewBox="0 0 32 20"><circle cx="12" cy="10" r="8" fill="#EB001B"/><circle cx="20" cy="10" r="8" fill="#F79E1B" fill-opacity="0.9"/></svg></span>
          <span class="am-pay-logo am-pay-logo--rupay" title="RuPay"><svg viewBox="0 0 56 16"><text x="0" y="12" font-family="Arial, sans-serif" font-size="11" font-weight="700" fill="#097B44">RuPay</text></svg></span>
          <span class="am-pay-logo am-pay-logo--razorpay" title="Razorpay"><svg viewBox="0 0 72 16"><text x="0" y="12" font-family="Arial, sans-serif" font-size="10" font-weight="700" fill="#072654">Razorpay</text></svg></span>
          <span class="am-pay-logo am-pay-logo--paypal" title="PayPal"><svg viewBox="0 0 64 16"><text x="0" y="12" font-family="Arial, sans-serif" font-size="11" font-weight="700"><tspan fill="#003087">Pay</tspan><tspan fill="#009CDE">Pal</tspan></text></svg></span>
        </div>
      </div>
    </div>`;
  }

  function pdpBuyActionsHtml(product, inStock = true) {
    if (!inStock) {
      return `<p class="am-pdp-buy__oos">Out of stock — <a href="/contact">contact the studio</a> for waitlist.</p>`;
    }
    return `<div class="am-pdp-buy">
      <div class="am-pdp-buy__qty">
        <label for="pdp-qty" class="am-pdp-buy__qty-label">Quantity</label>
        <input type="number" id="pdp-qty" value="1" min="1" max="99" class="am-input am-pdp-buy__qty-input" inputmode="numeric">
      </div>
      <div class="am-pdp-buy__actions">
        <button type="button" class="am-btn am-btn--outline am-btn--lg am-pdp-buy__btn" data-add-cart="${product.slug}">Add to Bag</button>
        <button type="button" class="am-btn am-btn--primary am-btn--lg am-pdp-buy__btn" data-buy-now="${product.slug}">Buy Now</button>
      </div>
      <p class="am-pdp-buy__stock">In stock</p>
    </div>`;
  }

  function inlineCalcHtml(product) {
    const serviceSlug = serviceSlugForProduct(product);
    const service = getService(serviceSlug) || { slug: serviceSlug, name: product.name, rate_per_sqft: 1800 };
    const label = estimateLabelForProduct(product);
    const calcHtml = serviceCalcHtml(
      { ...service, calc_label: label },
      { name: product.name, slug: product.slug }
    );
    return `<div class="am-pdp__calc-inline">${calcHtml}${checkoutTrustHtml()}</div>`;
  }

  function filterProducts(products, params) {
    let list = [...products];
    const cat = params.get('category');
    if (cat) list = list.filter((p) => categorySlug(p) === cat);
    const q = (params.get('search') || '').trim().toLowerCase();
    if (q) list = list.filter((p) => p.name.toLowerCase().includes(q) || (p.category || '').toLowerCase().includes(q));
    const sort = params.get('sort') || 'newest';
    if (sort === 'price_asc') list.sort((a, b) => a.price - b.price);
    else if (sort === 'price_desc') list.sort((a, b) => b.price - a.price);
    else if (sort === 'name') list.sort((a, b) => a.name.localeCompare(b.name));
    return list;
  }

  function setTitle(title) {
    document.title = title + ' — Vyomika Atelier LLP (Preview)';
  }

  function contactStudioBtn(label, context, classes = 'am-btn am-btn--primary') {
    const safe = String(context || '').replace(/"/g, '&quot;');
    return `<button type="button" class="${classes}" data-open-contact-studio data-contact-context="${safe}">${label}</button>`;
  }

  function renderRoute(pathname, search) {
    const main = document.getElementById('am-main');
    if (!main || !siteData) return;

    const params = new URLSearchParams(search || '');
    const path = pathname.replace(/\/+$/, '') || '/';
    const parts = path.split('/').filter(Boolean);

    if (path === '/' || path === '/preview.html') {
      setTitle('Home');
      window.AmPreview.render(siteData);
      return;
    }

    if (path === '/corten-steel') {
      renderService('corten-steel-facade');
      return;
    }

    if (path === '/sitemap.xml') {
      renderSitemap();
      return;
    }

    if (parts[0] === 'shop' && parts.length === 1) {
      renderShop(params);
      return;
    }

    if (parts[0] === 'shop' && parts.length === 2) {
      renderProduct(parts[1]);
      return;
    }

    if (parts[0] === 'cart') {
      renderCart();
      return;
    }

    if (parts[0] === 'checkout' && parts[1] === 'success') {
      renderCheckoutSuccess();
      return;
    }

    if (parts[0] === 'checkout') {
      renderCheckout();
      return;
    }

    if (parts[0] === 'services' && parts.length === 1) {
      renderServices();
      return;
    }

    if (parts[0] === 'services' && parts.length === 2) {
      renderService(parts[1]);
      return;
    }

    if (parts[0] === 'services' && parts.length === 3) {
      renderServiceDesign(parts[1], parts[2]);
      return;
    }

    if (parts[0] === 'projects' && parts.length === 1) {
      renderProjects();
      return;
    }

    if (parts[0] === 'projects' && parts.length === 2) {
      renderProjectDetail(parts[1]);
      return;
    }

    if (parts[0] === 'blog' && parts.length === 1) {
      renderBlog();
      return;
    }

    if (parts[0] === 'blog' && parts.length === 2) {
      renderBlogPost(parts[1]);
      return;
    }

    if (parts[0] === 'contact') {
      renderContact();
      return;
    }

    if (parts[0] === 'about') {
      renderAbout();
      return;
    }

    if (LEGAL_REDIRECTS[path]) {
      navigate(LEGAL_REDIRECTS[path], '', true);
      return;
    }

    const legalKey = LEGAL_PATHS[parts[0]];
    if (legalKey && parts.length === 1) {
      renderLegal(legalKey);
      return;
    }

    if (parts[0] === 'account') {
      if (parts.length === 1 || parts[1] === 'login') {
        renderAccountAuthPreview('login');
        return;
      }
      if (parts[1] === 'register') {
        renderAccountAuthPreview('register');
        return;
      }
      if (parts[1] === 'verify-otp') {
        renderAccountAuthPreview('verify');
        return;
      }
      if (parts[1] === 'forgot') {
        renderAccountAuthPreview('forgot');
        return;
      }
      renderAccountAuthPreview('login');
      return;
    }

    if (parts[0] === 'team') {
      renderTeam();
      return;
    }

    if (parts[0] === 'studio' && parts[1] === 'railings') {
      renderRailings();
      return;
    }

    if (parts[0] === 'collections' && parts[1] === 'mirror-frames' && parts.length === 2) {
      renderMirrorFramesIndex();
      return;
    }

    if (parts[0] === 'collections' && parts[1] === 'mirror-frames' && parts.length === 3) {
      renderMirrorFramesDesign(parts[2]);
      return;
    }

    if (parts[0] === 'professionals') {
      renderProfessionals();
      return;
    }

    if (parts[0] === 'custom-order' || parts[0] === 'leads' || parts[0] === 'create') {
      renderLeadForm('Custom Fabrication', 'Share dimensions, finish, and timeline — we will quote within 24 hours.');
      return;
    }

    renderNotFound(path);
  }

  function renderShop(params) {
    const products = filterProducts(collectProducts(siteData), params);
    const activeCat = params.get('category') || '';
    const catLabel = CATEGORIES.find((c) => c.slug === activeCat)?.name || 'Shop';
    const calcService = serviceForCategory(activeCat);
    const showCategoryCalc = CALC_CATEGORIES.includes(activeCat) && calcService;
    setTitle(catLabel);

    const counts = {};
    collectProducts(siteData).forEach((p) => {
      const slug = categorySlug(p) || 'other';
      counts[slug] = (counts[slug] || 0) + 1;
    });

    document.getElementById('am-main').innerHTML = `
${pageHero('Products', catLabel, showCategoryCalc ? calcService.summary : 'PVD partitions, fluted panels, and bespoke metal furniture.')}
${showCategoryCalc ? serviceFeaturedCalc(calcService) : ''}
<section class="am-page-body">
  <div class="am-container">
    ${breadcrumbs([
      { label: 'Home', url: '/' },
      { label: 'Shop', url: '/shop' },
      ...(activeCat ? [{ label: catLabel }] : []),
    ])}
    <div class="am-layout-shop">
      <aside class="am-shop-sidebar">
        <p class="am-sidebar-title">Category</p>
        ${CATEGORIES.map((c) => {
          const href = c.slug ? `/shop?category=${c.slug}` : '/shop';
          const n = c.slug ? (counts[c.slug] || 0) : collectProducts(siteData).length;
          return `<a href="${href}" class="am-sidebar-link ${activeCat === c.slug ? 'is-active' : ''}">${c.name}<span class="am-sidebar-count">${n}</span></a>`;
        }).join('')}
      </aside>
      <div class="am-shop-main">
        <div class="am-shop-toolbar">
          <form class="am-shop-search" data-preview-form>
            ${activeCat ? `<input type="hidden" name="category" value="${activeCat}">` : ''}
            <input type="search" name="search" value="${params.get('search') || ''}" placeholder="Search products…" class="am-input">
            <button type="submit" class="am-btn am-btn--primary am-btn--sm">Search</button>
          </form>
          <form class="am-shop-sort" data-preview-form>
            ${activeCat ? `<input type="hidden" name="category" value="${activeCat}">` : ''}
            ${params.get('search') ? `<input type="hidden" name="search" value="${params.get('search')}">` : ''}
            <label for="shop-sort">Sort</label>
            <select name="sort" id="shop-sort" class="am-input am-input--select">
              <option value="newest" ${params.get('sort', 'newest') === 'newest' ? 'selected' : ''}>Newest</option>
              <option value="price_asc" ${params.get('sort') === 'price_asc' ? 'selected' : ''}>Price: Low to High</option>
              <option value="price_desc" ${params.get('sort') === 'price_desc' ? 'selected' : ''}>Price: High to Low</option>
              <option value="name" ${params.get('sort') === 'name' ? 'selected' : ''}>Name A–Z</option>
            </select>
          </form>
        </div>
        <p class="am-shop-results">${products.length} product${products.length === 1 ? '' : 's'}</p>
        ${products.length ? `<div class="am-product-grid am-product-grid--shop">${products.map(productCard).join('')}</div>` : `
        <div class="am-empty"><h3>No products found</h3><p>Try another category or search term.</p><a href="/shop" class="am-btn am-btn--outline">View All Products</a></div>`}
        ${showCategoryCalc ? productTabsHtml(calcService.name, calcService.content || '<p>' + calcService.summary + '</p>', calcService.care, null) : ''}
      </div>
    </div>
  </div>
</section>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function renderProduct(slug) {
    const product = collectProducts(siteData).find((p) => p.slug === slug);
    if (!product) {
      renderNotFound('/shop/' + slug);
      return;
    }
    setTitle(product.name);
    const old = product.compare_price ? `<span class="am-featured__price-old">${fmt(product.compare_price)}</span>` : '';
    const badge = product.badge ? `<span class="am-featured__badge">${product.badge}</span>` : '';
    const showCalc = showsSqFtCalculator(product);
    const related = relatedForProduct(product);

    document.getElementById('am-main').innerHTML = `
<section class="am-page-body am-page-body--pdp">
  <div class="am-container">
    ${breadcrumbs([
      { label: 'Home', url: '/' },
      { label: 'Shop', url: '/shop' },
      { label: product.name },
    ])}
    <div class="am-pdp">
      <div class="am-pdp__gallery">
        <div class="am-pdp__gallery-inner">
          <div class="am-pdp__main"><img src="${product.image}" alt="${product.name}" class="am-pdp__main-img"></div>
        </div>
      </div>
      <div class="am-pdp__info">
        <p class="am-featured__cat">${product.category}</p>
        <h1 class="am-pdp__title">${product.name}</h1>
        <p class="am-featured__meta">SKU: ${product.sku} · Pan-India shipping</p>
        ${showCalc ? `
        <div class="am-featured__price am-featured__price--sqft">
          <div class="am-pdp__sqft-price">
            <span class="am-pdp__sqft-price-current" data-sqft-rate-display>₹1,800</span>
            <span class="am-pdp__sqft-price-unit">/ sq ft</span>
          </div>
          <p class="am-pdp__sqft-price-note" data-sqft-black-note hidden>Black finish selected — ₹2,340/sq ft (+30%)</p>
        </div>` : `
        <div class="am-featured__price">
          <span class="am-featured__price-current">${fmt(product.price)}</span>${old}${badge}
        </div>`}
        <ul class="am-pdp__trust">
          <li>✓ PVD stainless fabrication</li>
          <li>✓ Secure packaging</li>
          <li>✓ Estimated delivery: <strong>3–4 weeks</strong></li>
        </ul>
        ${finishSwatchesHtml()}
        <div class="am-prose am-pdp__desc"><p>${product.description}</p></div>
        ${showCalc ? inlineCalcHtml(product) : usesCheckoutFlow(product)
          ? `<div class="am-pdp__buy-inline" id="buy">${pdpBuyActionsHtml(product)}${checkoutTrustHtml()}</div>`
          : `<div class="am-pdp__quote-cta"><button type="button" class="am-btn am-btn--primary am-btn--lg am-btn--full" data-open-order-popup data-product-name="${product.name}" data-product-slug="${product.slug}" data-service-slug="${serviceSlugForProduct(product)}">Order Now</button></div>${checkoutTrustHtml()}`}
      </div>
    </div>
    ${productTabsHtml(product.name, '<p>' + product.description + '</p>', careGuidelinesForProduct(product), related, product)}
  </div>
</section>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function renderCart() {
    setTitle('Cart');
    const cart = window.AmPreviewCart;
    const items = cart.read();
    const shipping = cart.subtotal() >= 5000 ? 0 : 199;
    document.getElementById('am-main').innerHTML = `
${pageHero('Cart', 'Your Cart', items.length ? `${items.length} line item${items.length === 1 ? '' : 's'}` : 'Your cart is empty.')}
<section class="am-page-body">
  <div class="am-container am-checkout-flow">
    ${checkoutStepsHtml(1)}
    ${items.length ? `
    <div class="am-checkout-layout">
      <div class="am-checkout-main">
        <div class="am-cart-list am-card"><div class="am-card__body am-cart-list__body">
          ${items.map((i) => `
          <article class="am-cart-row" data-cart-slug="${i.slug}">
            <a href="/shop/${i.slug}" class="am-cart-thumb"><img src="${i.image}" alt=""></a>
            <div class="am-cart-row__body">
              <h3 class="am-cart-row__name"><a href="/shop/${i.slug}">${i.name}</a></h3>
              <p class="am-cart-row__unit">${cart.fmt(i.price)} each</p>
              <div class="am-cart-row__qty-form">
                <label>Quantity</label>
                <button type="button" data-cart-qty="${i.slug}" data-delta="-1" class="am-btn am-btn--outline am-btn--sm">−</button>
                <span>${i.qty}</span>
                <button type="button" data-cart-qty="${i.slug}" data-delta="1" class="am-btn am-btn--outline am-btn--sm">+</button>
              </div>
            </div>
            <div class="am-cart-row__total">
              <p class="am-cart-row__line-total">${cart.fmt(i.price * i.qty)}</p>
              <button type="button" data-remove-cart="${i.slug}" class="am-cart-row__remove">Remove</button>
            </div>
          </article>`).join('')}
        </div></div>
        ${checkoutTrustHtml()}
      </div>
      <div class="am-checkout-sidebar">
        <aside class="am-order-summary"><div class="am-order-summary__card am-card"><div class="am-card__body">
          <h2 class="am-order-summary__title">Order Summary</h2>
          <ul class="am-order-summary__lines">${items.map((i) => `
            <li class="am-order-summary__line">
              <span class="am-order-summary__meta"><span class="am-order-summary__name">${i.name}</span><span class="am-order-summary__qty">Qty ${i.qty}</span></span>
              <span class="am-order-summary__price">${cart.fmt(i.price * i.qty)}</span>
            </li>`).join('')}</ul>
          <div class="am-order-summary__totals">
            <div class="am-order-summary__row"><span>Subtotal</span><span>${cart.fmt(cart.subtotal())}</span></div>
            <div class="am-order-summary__row am-order-summary__row--muted"><span>Shipping</span><span>${shipping ? cart.fmt(shipping) : 'Free'}</span></div>
            <div class="am-order-summary__row am-order-summary__row--total"><span>Total</span><span>${cart.fmt(cart.subtotal() + shipping)}</span></div>
          </div>
        </div></div></aside>
        <div class="am-checkout-sidebar__actions">
          <a href="/checkout" class="am-btn am-btn--primary am-btn--full am-btn--lg">Proceed to Checkout</a>
          <a href="/shop" class="am-btn am-btn--outline am-btn--full">Continue Shopping</a>
        </div>
      </div>
    </div>` : `
    <div class="am-checkout-empty am-card"><div class="am-card__body">
      <div class="am-checkout-empty__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><path d="M6 6h15l-1.5 9h-12z"/><path d="M6 6l-1-3H2"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg></div>
      <h2 class="am-checkout-empty__title">Your cart is empty</h2>
      <p class="am-checkout-empty__text">Browse our PVD partitions and metal furniture.</p>
      <a href="/shop" class="am-btn am-btn--primary">Shop Now</a>
    </div></div>`}
  </div>
</section>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  const ADDRESS_COUNTRIES = [
    'India', 'United States', 'United Kingdom', 'Canada', 'Australia', 'United Arab Emirates',
    'Singapore', 'Germany', 'France', 'Italy', 'Spain', 'Netherlands', 'Switzerland',
    'Belgium', 'Sweden', 'Norway', 'Denmark', 'Japan', 'South Korea', 'China', 'Hong Kong',
    'Malaysia', 'Thailand', 'Indonesia', 'Philippines', 'Saudi Arabia', 'Qatar', 'Kuwait',
    'Bahrain', 'Oman', 'South Africa', 'New Zealand', 'Brazil', 'Mexico', 'Portugal',
    'Ireland', 'Austria', 'Turkey', 'Israel', 'Other',
  ];

  function addressCountrySelectHtml(selected = 'India') {
    return `<select name="country" class="am-input am-input--select" required data-country-select>${ADDRESS_COUNTRIES.map((c) => `<option value="${c}"${c === selected ? ' selected' : ''}>${c}</option>`).join('')}</select>`;
  }

  function addressFormGridHtml() {
    return `<div class="am-address-form__grid">
            <div class="am-address-form__field"><label>First Name <span class="am-address-form__req">*</span></label><input type="text" name="first_name" required class="am-input" placeholder="First Name"></div>
            <div class="am-address-form__field"><label>Last Name <span class="am-address-form__req">*</span></label><input type="text" name="last_name" required class="am-input" placeholder="Last Name"></div>
            <div class="am-address-form__field"><label>Company name <span class="am-address-form__optional">(optional)</span></label><input type="text" name="company" class="am-input" placeholder="Company"></div>
            <div class="am-address-form__field"><label>Country / Region <span class="am-address-form__req">*</span></label>${addressCountrySelectHtml()}</div>
            <div class="am-address-form__field am-address-form__field--full" data-country-other-wrap hidden><label>Specify country <span class="am-address-form__req">*</span></label><input type="text" name="country_other" class="am-input" placeholder="Country name"></div>
            <div class="am-address-form__field am-address-form__field--full"><label>Street Address <span class="am-address-form__req">*</span></label><input type="text" name="address" required class="am-input" placeholder="Street Address"></div>
            <div class="am-address-form__field"><label>City / Town <span class="am-address-form__req">*</span></label><input type="text" name="city" required class="am-input" placeholder="City / Town"></div>
            <div class="am-address-form__field"><label>State / Province <span class="am-address-form__req">*</span></label><input type="text" name="state" required class="am-input" placeholder="State / Province"></div>
            <div class="am-address-form__field"><label>Pincode / ZIP <span class="am-address-form__req">*</span></label><input type="text" name="zip" required class="am-input" placeholder="Pincode / ZIP"></div>
            <div class="am-address-form__field"><label>Phone <span class="am-address-form__req">*</span></label><input type="tel" name="phone" required class="am-input" placeholder="Phone"></div>
            <div class="am-address-form__field am-address-form__field--full"><label>Email <span class="am-address-form__req">*</span></label><input type="email" name="email" required class="am-input" placeholder="Email"></div>
          </div>`;
  }

  function renderCheckout() {
    setTitle('Checkout');
    const cart = window.AmPreviewCart;
    const items = cart.read();
    const shipping = cart.subtotal() >= 5000 ? 0 : 199;
    document.getElementById('am-main').innerHTML = `
${pageHero('Secure Checkout', 'Checkout', 'Preview mode — form submission is simulated.')}
<section class="am-page-body">
  <div class="am-container am-checkout-flow am-checkout-flow--centered">
    ${checkoutStepsHtml(2)}
    <form class="am-checkout-stack am-checkout-form am-address-form" id="preview-checkout-form">
      <input type="hidden" name="payment_method" value="razorpay">
      <div class="am-card am-checkout-panel"><div class="am-card__body">
        <h2 class="am-checkout-panel__title">Shipping details</h2>
        <p class="am-checkout-panel__hint">Worldwide delivery · estimated 3–4 weeks after order confirmation</p>
        ${addressFormGridHtml()}
      </div></div>
      <div class="am-card am-checkout-panel am-checkout-panel--payment"><div class="am-card__body">
        <h2 class="am-checkout-panel__title">Payment</h2>
        <p class="am-checkout-panel__hint">Pay securely online with UPI or card after you place the order.</p>
        <div class="am-checkout-pay-badges" aria-label="Accepted payment methods">
          <span class="am-checkout-pay-badge">UPI</span>
          <span class="am-checkout-pay-badge">Debit / Credit Card</span>
          <span class="am-checkout-pay-badge">Net Banking</span>
        </div>
        ${checkoutTrustHtml()}
      </div></div>
      <aside class="am-order-summary am-order-summary--compact"><div class="am-order-summary__card am-card"><div class="am-card__body">
        <h2 class="am-order-summary__title">Order Summary</h2>
        <ul class="am-order-summary__lines">${items.map((i) => `
          <li class="am-order-summary__line am-order-summary__line--plain">
            <span class="am-order-summary__meta"><span class="am-order-summary__name">${i.name}</span><span class="am-order-summary__qty">Qty ${i.qty}</span></span>
            <span class="am-order-summary__price">${cart.fmt(i.price * i.qty)}</span>
          </li>`).join('')}</ul>
        <div class="am-order-summary__totals">
          <div class="am-order-summary__row"><span>Subtotal</span><span>${cart.fmt(cart.subtotal())}</span></div>
          <div class="am-order-summary__row am-order-summary__row--muted"><span>Shipping</span><span>${shipping ? cart.fmt(shipping) : 'Free'}</span></div>
          <div class="am-order-summary__row am-order-summary__row--total"><span>Total</span><span>${cart.fmt(cart.subtotal() + shipping)}</span></div>
        </div>
      </div></div></aside>
      <div class="am-checkout-stack__actions">
        <button type="submit" class="am-btn am-btn--primary am-btn--full am-btn--lg">Continue to Payment (Preview)</button>
        <a href="/cart" class="am-btn am-btn--outline am-btn--full">Back to Cart</a>
      </div>
    </form>
  </div>
</section>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function getService(slug) {
    return (siteData.services || []).find((s) => s.slug === slug);
  }

  function serviceForCategory(cat) {
    if (['partitions', 'fluted-panels', 'room-dividers'].includes(cat)) return getService('partitions');
    if (cat === 'door-handles') return getService('main-entrance-pvd-doors');
    if (cat === 'metal-furniture') return getService('rack-systems-metal-pvd');
    return null;
  }

  function productUsesCalc(product) {
    return showsSqFtCalculator(product);
  }

  function serviceCalcHtml(service, design) {
    if (window.AmPreview?.calculatorHtml) {
      const featured = {
        name: design?.name || service.name,
        rate_per_sqft: service.rate_per_sqft || 1800,
      };
      return window.AmPreview.calculatorHtml(featured)
        .replace('data-service-slug="partitions"', `data-service-slug="${service.slug}"`)
        .replace('data-design-slug=""', `data-design-slug="${design?.slug || ''}"`)
        .replace(`data-service-name="${featured.name}"`, `data-service-name="${featured.name}"`)
        .replace('Estimate your partition', 'Estimate your ' + (service.calc_label || 'partition'));
    }
    return '';
  }

  function productTabsHtml(title, contentHtml, careItems, relatedProducts, product) {
    const care = (careItems || []).map((item) => `<li>${item}</li>`).join('');
    const related = relatedProducts?.length ? `
    <div class="am-pdp-related-block">
      <div class="am-pdp-tabs__nav am-pdp-tabs__nav--sub"><span class="am-pdp-tabs__tab is-active">Related Products</span></div>
      <div class="am-product-grid am-product-grid--4">${relatedProducts.map(productCard).join('')}</div>
    </div>` : '';
    const specRows = product ? `
      <div><dt>Category</dt><dd>${product.category || '—'}</dd></div>
      <div><dt>SKU</dt><dd>${product.sku || '—'}</dd></div>
      <div><dt>Material</dt><dd>Grade 304/316 stainless steel with PVD coating</dd></div>
      <div><dt>Finish options</dt><dd>Gold Mirror, Gold Brush, Rose Gold Mirror, Rose Gold Brush, Champagne Mirror, Champagne Brush, Black Mirror (+30%), Black Brush (+30%)</dd></div>
      <div><dt>Price</dt><dd>${fmt(product.price)}</dd></div>` : `
      <div><dt>Material</dt><dd>Grade 304/316 stainless steel with PVD coating</dd></div>
      <div><dt>Finish options</dt><dd>Gold Mirror, Gold Brush, Rose Gold Mirror, Rose Gold Brush, Champagne Mirror, Champagne Brush, Black Mirror (+30%), Black Brush (+30%)</dd></div>`;

    return `<section class="am-pdp-tabs-wrap">
      <div class="am-pdp-tabs" data-am-tabs>
        <div class="am-pdp-tabs__nav" role="tablist">
          <button type="button" class="am-pdp-tabs__tab is-active" data-am-tab="description">Description</button>
          <button type="button" class="am-pdp-tabs__tab" data-am-tab="specifications">Specifications</button>
          <button type="button" class="am-pdp-tabs__tab" data-am-tab="packaging">Packaging</button>
          <button type="button" class="am-pdp-tabs__tab" data-am-tab="shipping">Shipping</button>
        </div>
        <div class="am-pdp-tabs__panel is-active" data-am-panel="description">
          <div class="am-pdp-tabs__desc-grid">
            <div class="am-pdp-tabs__desc-main">
              <h2 class="am-pdp-tabs__desc-title">${title}</h2>
              <div class="am-prose am-pdp-tabs__prose">${contentHtml}</div>
            </div>
            ${care ? `<div class="am-pdp-tabs__desc-aside">
              <h3 class="am-pdp-tabs__care-title">Composition, Material &amp; Care Guidelines</h3>
              <ul class="am-pdp-tabs__care-list">${care}</ul>
            </div>` : ''}
          </div>
        </div>
        <div class="am-pdp-tabs__panel" data-am-panel="specifications" hidden>
          <div class="am-prose am-pdp-tabs__prose">
            <h3>Product Specifications</h3>
            <dl class="am-pdp-spec-table">${specRows}
              <div><dt>Delivery</dt><dd>3–4 weeks — Pan-India from Mumbai studio</dd></div>
            </dl>
          </div>
        </div>
        <div class="am-pdp-tabs__panel" data-am-panel="packaging" hidden>
          <div class="am-prose am-pdp-tabs__prose">
            <h3>Packaging &amp; Handling</h3>
            <p>Protective foam, corner guards, and plywood crating for Pan-India transit. PVD surfaces are film-wrapped against scratches.</p>
            <ul class="am-pdp-tabs__care-list">
              <li>Partition panels — vertical crate with foam spacers</li>
              <li>Door systems — reinforced frame crate with glass protection</li>
              <li>Furniture &amp; racks — flat-pack or assembled crate</li>
            </ul>
          </div>
        </div>
        <div class="am-pdp-tabs__panel" data-am-panel="shipping" hidden>
          <div class="am-prose am-pdp-tabs__prose">
            <h3>Shipping</h3>
            <p>Fabrication from our Mumbai studio with delivery across India. Estimated lead time: <strong>3–4 weeks</strong> from order confirmation.</p>
            <ul class="am-pdp-tabs__care-list">
              <li><strong>Metro cities:</strong> Door delivery with installation support on request</li>
              <li><strong>Other locations:</strong> Pan-India courier or freight partner</li>
              <li><strong>Made to order:</strong> All items are custom fabricated — no returns on bespoke metalwork</li>
            </ul>
            <p><a href="/shipping-delivery-policy">Full shipping policy →</a></p>
          </div>
        </div>
      </div>
      ${related}
    </section>`;
  }

  function serviceLeadFormHtml(service) {
    return `<div class="am-card" style="height:100%">
      <div class="am-card__body">
        <p class="am-card__label" style="margin-bottom:1rem">Enquire</p>
        <h3 class="am-card__title" style="font-size:1.35rem;margin-bottom:1.5rem">Request Information</h3>
        <form class="am-form-stack" id="preview-service-lead-form">
          <input type="text" name="name" placeholder="Your name" required class="am-input">
          <input type="email" name="email" placeholder="Email" required class="am-input">
          <input type="tel" name="phone" placeholder="Phone / WhatsApp" required class="am-input">
          <textarea name="message" placeholder="Describe your requirements — dimensions, material, finish, timeline…" required rows="4" class="am-input am-textarea"></textarea>
          <button type="submit" class="am-btn am-btn--primary am-btn--full">Submit Enquiry (Preview)</button>
        </form>
      </div>
    </div>`;
  }

  function serviceFeaturedSection(service, design) {
    if (service.has_calculator === false) {
      return serviceFeaturedLead(service, design);
    }
    return serviceFeaturedCalc(service, design);
  }

  function serviceFeaturedLead(service, design) {
    const name = design?.name || service.name;
    const image = design?.image || service.image;
    const summary = design?.description || service.summary;
    return `<section class="am-section am-section--featured am-section--edge">
      <div class="am-section__body">
        <div class="am-featured am-featured--edge am-featured--with-calc">
          <div class="am-featured__image am-featured__image--portrait">
            ${image ? `<img src="${image}" alt="${name}" loading="lazy">` : ''}
          </div>
          <div class="am-featured__body">
            <p class="am-featured__cat">${service.name}</p>
            <h2 class="am-featured__name">${name}</h2>
            <p class="am-featured__meta">Custom fabrication · Pan-India delivery</p>
            <p class="am-featured__desc">${summary}</p>
            ${design ? `<a href="/services/${service.slug}" class="am-featured__view-link">← All ${service.name} designs</a>` : ''}
          </div>
          <div class="am-pdp__calc-column">
            ${serviceLeadFormHtml(service)}
          </div>
        </div>
      </div>
    </section>`;
  }

  function serviceFeaturedCalc(service, design) {
    const name = design?.name || service.name;
    const image = design?.image || service.image;
    const summary = design?.description || service.summary;
    const rate = service.rate_per_sqft || 1800;
    return `<section class="am-section am-section--featured am-section--edge">
      <div class="am-section__body">
        <div class="am-featured am-featured--edge am-featured--with-calc">
          <div class="am-featured__image am-featured__image--portrait">
            ${image ? `<img src="${image}" alt="${name}" loading="lazy">` : ''}
          </div>
          <div class="am-featured__body">
            <p class="am-featured__cat">${service.name}</p>
            <h2 class="am-featured__name">${name}</h2>
            <p class="am-featured__meta">Custom fabrication · Pan-India delivery · From ₹${Number(rate).toLocaleString('en-IN')}/sq ft</p>
            <div class="am-featured__price">
              <span class="am-featured__price-current">From ₹${Number(rate).toLocaleString('en-IN')}</span>
              <span style="font-weight:400;font-size:0.85rem;color:var(--am-muted)">per sq ft</span>
            </div>
            <p class="am-featured__desc">${summary}</p>
            <p class="am-featured__viewers">Use the calculator — then Order Now for a studio quote.</p>
            ${design ? `<a href="/services/${service.slug}" class="am-featured__view-link">← All ${service.name} designs</a>` : ''}
          </div>
          <div class="am-pdp__calc-column">
            ${serviceCalcHtml(service, design)}
            ${checkoutTrustHtml()}
          </div>
        </div>
      </div>
    </section>`;
  }

  function relatedForService(service) {
    const products = collectProducts(siteData);
    const keys = service.slug === 'partitions' ? ['partition', 'fluted', 'divider']
      : service.slug === 'rack-systems-metal-pvd' ? ['rack', 'table']
      : ['door', 'handle'];
    return products.filter((p) => keys.some((k) => p.slug.includes(k) || (p.category || '').toLowerCase().includes(k))).slice(0, 4);
  }

  function serviceCtaText(service) {
    if (service.slug === 'corten-steel-facade') return 'Request Quote';
    return 'Order Now';
  }

  function designCardHtml(service, design) {
    const inner = `
      ${design.image ? `<div class="am-design-gallery__media"><img src="${design.image}" alt="${design.name}" loading="lazy"></div>` : ''}
      <div class="am-design-gallery__body">
        <h3 class="am-design-gallery__name">${design.name}</h3>
        <p class="am-design-gallery__desc">${design.description}</p>
        ${service.slug !== 'rack-systems-metal-pvd' ? '<span class="am-design-gallery__cta">Order Now</span>' : ''}
      </div>`;
    if (service.slug === 'rack-systems-metal-pvd') {
      return `<article class="am-design-gallery__card am-design-gallery__card--static">${inner}</article>`;
    }
    if (service.slug === 'partitions' && design.product_slug) {
      return `<a href="/shop/${design.product_slug}" class="am-design-gallery__card">${inner}</a>`;
    }
    return `<a href="/services/${service.slug}/${design.slug}" class="am-design-gallery__card">${inner}</a>`;
  }

  function renderService(slug) {
    const service = getService(slug);
    if (!service) {
      renderNotFound('/services/' + slug);
      return;
    }
    if (slug === 'corten-steel-facade') {
      renderCortenService(service);
      return;
    }
    renderServiceGallery(slug);
  }

  function renderServiceDesign(serviceSlug, designSlug) {
    if (serviceSlug !== 'corten-steel-facade') {
      const service = getService(serviceSlug);
      const design = service?.designs?.find((d) => d.slug === designSlug);
      if (design?.product_slug) {
        navigate('/shop/' + design.product_slug, '', true);
        return;
      }
      navigate('/services/' + serviceSlug, '', true);
      return;
    }
    const service = getService(serviceSlug);
    const design = service?.designs?.find((d) => d.slug === designSlug);
    if (!service || !design) {
      renderNotFound('/services/' + serviceSlug + '/' + designSlug);
      return;
    }
    setTitle(design.name + ' — ' + service.name);
    const related = relatedForService(service);

    document.getElementById('am-main').innerHTML = `
${pageHero(service.name, design.name, design.description)}
${serviceFeaturedSection(service, design)}
<section class="am-page-body">
  <div class="am-container">
    ${productTabsHtml(design.name, '<p>' + design.description + '</p>', service.care, related)}
  </div>
</section>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function renderServices() {
    setTitle('Studio');
    const services = siteData.services || [];
    document.getElementById('am-main').innerHTML = `
${pageHero('Studio', 'Fabrication & Design', 'PVD partitions, metal furniture, and custom fabrication for Indian interiors.')}
<section class="am-page-body">
  <div class="am-container">
    <div class="am-card-grid">
      ${services.map((s) => `
      <article class="am-card">
        <div class="am-card__body">
          <h2 class="am-card__title">${s.name}</h2>
          <p class="am-card__text">${s.summary}</p>
          <a href="/services/${s.slug}" class="am-btn am-btn--primary am-btn--sm">${serviceCtaText(s)}</a>
        </div>
      </article>`).join('')}
    </div>
  </div>
</section>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function renderProjects() {
    const page = siteData.projects_page || {};
    const hero = page.hero || {};
    const params = new URLSearchParams(location.search);
    const activeCat = params.get('category') || '';
    const categories = page.categories || [
      { slug: '', label: 'All' },
      { slug: 'residential', label: 'Residential' },
      { slug: 'commercial', label: 'Commercial' },
      { slug: 'hospitality', label: 'Hospitality' },
    ];
    let projects = siteData.portfolio || [];
    if (activeCat) projects = projects.filter((p) => p.category === activeCat);

    setTitle(page.meta_title || 'Projects');
    const meta = document.querySelector('meta[name="description"]');
    if (meta && page.meta_description) meta.setAttribute('content', page.meta_description);

    const filtersHtml = categories.map((cat) => {
      const href = cat.slug ? `/projects?category=${cat.slug}` : '/projects';
      const active = activeCat === (cat.slug || '') ? ' is-active' : '';
      return `<a href="${href}" class="am-project-filters__btn${active}">${cat.label}</a>`;
    }).join('');

    const gridHtml = projects.length ? projects.map((p) => `
      <a href="/projects/${p.slug}" class="am-project-card">
        <div class="am-project-card__media"><img src="${p.image}" alt="${p.title}" loading="lazy"></div>
        <div class="am-project-card__body">
          <p class="am-project-card__meta">
            ${p.category ? `<span>${p.category.charAt(0).toUpperCase() + p.category.slice(1)}</span>` : ''}
            ${p.location ? `<span>${p.location}</span>` : ''}
          </p>
          <h2 class="am-project-card__title">${p.title}</h2>
          <p class="am-project-card__excerpt">${p.summary || ''}</p>
          <span class="am-project-card__link">View project →</span>
        </div>
      </a>`).join('') : `<div class="am-empty" style="text-align:center;padding:3rem 0"><p style="color:var(--am-muted)">No projects in this category.</p><a href="/projects" class="am-btn am-btn--outline">View all</a></div>`;

    const cta = page.footer_cta || {};
    document.getElementById('am-main').innerHTML = `
${pageHero(hero.label || 'Our Work', hero.title || 'Projects', hero.subtitle || '')}
<section class="am-page-body am-projects-index">
  <div class="am-container">
    <nav class="am-project-filters" aria-label="Filter projects">${filtersHtml}</nav>
    <div class="am-project-grid">${gridHtml}</div>
  </div>
</section>
${cta.title ? `<section class="am-section am-section--dark am-projects-cta"><div class="am-container am-projects-cta__inner"><div><h2 class="am-corten-section__title">${cta.title}</h2><p class="am-corten-section__lead">${cta.body || ''}</p></div><div class="am-projects-cta__actions"><a href="/custom-order" class="am-btn am-btn--primary">${cta.primary_label || 'Request a Quote'}</a>${contactStudioBtn(cta.secondary_label || 'Contact Us', 'Project enquiry', 'am-btn am-btn--outline am-btn--light')}</div></div></section>` : ''}`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function renderProjectDetail(slug) {
    const project = (siteData.portfolio || []).find((p) => p.slug === slug);
    if (!project) {
      renderNotFound('/projects/' + slug);
      return;
    }
    setTitle(project.meta_title || project.title);
    const meta = document.querySelector('meta[name="description"]');
    if (meta && project.meta_description) meta.setAttribute('content', project.meta_description);

    const catLabel = project.category ? project.category.charAt(0).toUpperCase() + project.category.slice(1) : '';
    const materialsHtml = (project.materials || []).map((m) => `<li>${m}</li>`).join('');
    const galleryHtml = (project.gallery || []).map((img) => `
      <figure class="am-project-gallery__item"><img src="${img}" alt="" loading="lazy"></figure>`).join('');
    const designHtml = project.design_details
      ? project.design_details.split('\n').filter(Boolean).map((l) => `<p>${l}</p>`).join('')
      : '';

    document.getElementById('am-main').innerHTML = `
<nav class="am-breadcrumbs am-breadcrumbs--legal" aria-label="Breadcrumb">
  <div class="am-container">
    <a href="/">Home</a><span class="am-breadcrumbs__sep">/</span>
    <a href="/projects">Projects</a><span class="am-breadcrumbs__sep">/</span>
    <span aria-current="page">${project.title}</span>
  </div>
</nav>
${project.image ? `<section class="am-project-hero"><img src="${project.image}" alt="${project.title}" class="am-project-hero__img"></section>` : ''}
<section class="am-page-body am-project-detail">
  <div class="am-container">
    <div class="am-project-detail__layout">
      <div class="am-project-detail__main">
        <h1 class="am-project-detail__title">${project.title}</h1>
        ${project.summary ? `<p class="am-project-detail__overview">${project.summary}</p>` : ''}
        ${project.content ? `<div class="am-prose am-project-detail__content">${project.content}</div>` : ''}
        ${project.design_details ? `<div class="am-project-block"><h2 class="am-project-block__title">Design Details</h2><div class="am-prose">${designHtml}</div></div>` : ''}
        ${materialsHtml ? `<div class="am-project-block"><h2 class="am-project-block__title">Materials &amp; Finishes</h2><ul class="am-corten-checklist">${materialsHtml}</ul></div>` : ''}
        ${galleryHtml ? `<div class="am-project-block"><h2 class="am-project-block__title">Gallery</h2><div class="am-project-gallery">${galleryHtml}</div></div>` : ''}
        ${project.testimonial_quote ? `<blockquote class="am-project-testimonial"><p class="am-project-testimonial__quote">"${project.testimonial_quote}"</p><footer><cite class="am-project-testimonial__author">${project.testimonial_author || ''}</cite>${project.testimonial_role ? `<span class="am-project-testimonial__role">${project.testimonial_role}</span>` : ''}</footer></blockquote>` : ''}
        <div class="am-project-detail__cta">
          <button type="button" class="am-btn am-btn--primary" data-open-project-enquiry data-project-slug="${project.slug}" data-project-title="${project.title}">Inquire About a Similar Project</button>
          <a href="/projects" class="am-btn am-btn--outline">← All Projects</a>
        </div>
      </div>
      <aside class="am-project-sidebar">
        <dl class="am-project-meta">
          ${project.client ? `<div><dt>Client</dt><dd>${project.client}</dd></div>` : ''}
          ${project.location ? `<div><dt>Location</dt><dd>${project.location}</dd></div>` : ''}
          ${project.year ? `<div><dt>Year</dt><dd>${project.year}</dd></div>` : ''}
          ${catLabel ? `<div><dt>Category</dt><dd>${catLabel}</dd></div>` : ''}
        </dl>
        <div class="am-card" style="margin-top:1.5rem"><div class="am-card__body">
          <p class="am-card__label">Similar project?</p>
          <h3 class="am-card__title" style="font-size:1.1rem;margin-bottom:0.75rem">Get a Quote</h3>
          <button type="button" class="am-btn am-btn--primary am-btn--full am-btn--sm" data-open-contact-studio data-contact-context="Quote — ${project.title}">Contact Studio</button>
        </div></div>
      </aside>
    </div>
  </div>
</section>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function renderBlog() {
    const blog = blogData || {};
    const index = blog.index || {};
    const categories = blog.categories || [];
    const posts = blog.posts || [];
    const params = new URLSearchParams(location.search);
    const activeCat = params.get('category') || '';
    const featured = !activeCat ? posts.find((p) => p.is_featured) || posts[0] : null;
    let gridPosts = posts.filter((p) => !featured || p.slug !== featured.slug);
    if (activeCat) {
      gridPosts = posts.filter((p) => (p.category_slug || '') === activeCat || p.category === activeCat);
    }
    setTitle(blog.meta_title || 'Ideas, Materials & Projects');
    document.getElementById('am-main').innerHTML = `
${pageHero(index.label || 'Journal', index.title || 'Ideas, Materials & Projects', index.subtitle || '')}
<section class="am-page-body am-blog-index">
  <div class="am-container">
    ${featured ? `
    <article class="am-blog-featured">
      <a href="/blog/${featured.slug}" class="am-blog-featured__link">
        <div class="am-blog-featured__media"><img src="${featured.image}" alt="${featured.hero_image_alt || featured.title}" loading="eager"></div>
        <div class="am-blog-featured__body">
          <span class="am-blog-featured__label">Featured</span>
          <span class="am-blog-cat">${featured.category}</span>
          <h2 class="am-blog-featured__title">${featured.title}</h2>
          <p class="am-blog-featured__excerpt">${featured.excerpt}</p>
          <div class="am-blog-meta"><span>${featured.author || 'Vyomika Atelier LLP'}</span><span>${featured.date || ''}</span><span>${featured.reading_time_minutes || 5} min read</span></div>
          <span class="am-blog-featured__cta">Read article →</span>
        </div>
      </a>
    </article>` : ''}
    <nav class="am-blog-filters" aria-label="Filter articles by category">
      <a href="/blog" class="am-blog-filters__btn ${!activeCat ? 'is-active' : ''}">All</a>
      ${categories.map((c) => `<a href="/blog?category=${c.slug}" class="am-blog-filters__btn ${activeCat === c.slug ? 'is-active' : ''}">${c.label}</a>`).join('')}
    </nav>
    <div class="am-blog-grid">
      ${gridPosts.map((p) => `
      <article class="am-blog-card">
        <a href="/blog/${p.slug}">
          <div class="am-blog-card__thumb"><img src="${p.image}" alt="${p.hero_image_alt || p.title}" loading="lazy"></div>
          <div class="am-blog-card__body">
            <div class="am-blog-card__meta"><span class="am-blog-cat">${p.category}</span><span>${p.date || ''}</span></div>
            <h3 class="am-blog-card__title">${p.title}</h3>
            <p class="am-blog-card__excerpt">${p.excerpt}</p>
            <span class="am-blog-card__read">${p.reading_time_minutes || 5} min read</span>
          </div>
        </a>
      </article>`).join('')}
    </div>
  </div>
</section>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function renderBlogPost(slug) {
    const post = (blogData?.posts || []).find((p) => p.slug === slug);
    if (!post) {
      renderNotFound('/blog/' + slug);
      return;
    }
    setTitle(post.meta_title || post.title);
    const related = (blogData.posts || []).filter((p) => p.slug !== slug).slice(0, 3);
    const galleryHtml = (post.gallery || []).length
      ? `<section class="am-blog-block"><h2 class="am-blog-block__title">Project Gallery</h2><div class="am-blog-gallery">${post.gallery.map((img) => `<figure class="am-blog-gallery__item"><img src="${img}" alt="${post.title} detail" loading="lazy"></figure>`).join('')}</div></section>`
      : '';
    const faqHtml = (post.faq || []).length
      ? `<section class="am-blog-block am-blog-faq"><h2 class="am-blog-block__title">Frequently Asked Questions</h2><div class="am-corten-faq-wrap"><div class="am-corten-faq am-corten-faq--light">${post.faq.map((f) => `<details class="am-corten-faq__item"><summary>${f.question}</summary><p>${f.answer}</p></details>`).join('')}</div></div></section>`
      : '';
    document.getElementById('am-main').innerHTML = `
<nav class="am-breadcrumbs" aria-label="Breadcrumb"><ol><li><a href="/">Home</a></li><li><a href="/blog">Blog</a></li><li><span aria-current="page">${post.title}</span></li></ol></nav>
<article class="am-blog-article">
  <header class="am-blog-article__header am-container">
    <p class="am-blog-article__category"><a href="/blog?category=${post.category_slug || ''}">${post.category}</a></p>
    <h1 class="am-blog-article__title">${post.title}</h1>
    <p class="am-blog-article__excerpt">${post.excerpt}</p>
    <div class="am-blog-meta am-blog-article__meta"><span>${post.author || 'Vyomika Atelier LLP'}</span><span>${post.date || ''}</span><span>${post.reading_time_minutes || 5} min read</span></div>
  </header>
  <figure class="am-blog-article__hero"><img src="${post.image}" alt="${post.hero_image_alt || post.title}" loading="eager"></figure>
  <div class="am-container am-blog-article__body">
    <div class="am-prose am-blog-article__content">${post.content || ''}</div>
    ${galleryHtml}
    ${faqHtml}
    <section class="am-blog-cta"><div class="am-blog-cta__inner">
      <h2 class="am-blog-cta__title">Discuss Your Project</h2>
      <p class="am-blog-cta__text">Share drawings, dimensions, and finish preferences — our Mumbai studio team responds within one business day.</p>
      <div class="am-blog-cta__actions"><button type="button" class="am-btn am-btn--primary" data-open-contact-studio data-contact-context="Re: ${post.title}">Contact Studio</button><a href="/professionals" class="am-btn am-btn--outline">Trade Programme</a></div>
      <p class="am-blog-cta__contact"><a href="mailto:namaste@vyomikaatelier.com">namaste@vyomikaatelier.com</a> · <a href="tel:+919205850254">+91 9205850254</a></p>
    </div></section>
    ${related.length ? `<section class="am-blog-block"><h2 class="am-blog-block__title">Related Articles</h2><div class="am-blog-grid am-blog-grid--related">${related.map((p) => `
      <article class="am-blog-card"><a href="/blog/${p.slug}">
        <div class="am-blog-card__thumb"><img src="${p.image}" alt="${p.hero_image_alt || p.title}" loading="lazy"></div>
        <div class="am-blog-card__body"><div class="am-blog-card__meta"><span class="am-blog-cat">${p.category}</span></div><h3 class="am-blog-card__title">${p.title}</h3></div>
      </a></article>`).join('')}</div><p class="am-blog-block__more"><a href="/blog">← All articles</a></p></section>` : ''}
  </div>
</article>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function renderContact() {
    setTitle('Contact');
    const b = siteData.brand || {};
    document.getElementById('am-main').innerHTML = `
${pageHero('Contact', 'Get in Touch', 'Mumbai studio — Mon–Sat, 10am–7pm IST')}
<section class="am-page-body">
  <div class="am-container am-contact-grid">
    <div>
      <p><strong>Email</strong><br><a href="mailto:${b.email}">${b.email}</a></p>
      <p><strong>Phone</strong><br><a href="tel:${(b.phone || '').replace(/\s/g, '')}">${b.phone}</a></p>
      <p><strong>Studio</strong><br>${b.address_office || ''}</p>
    </div>
    <form class="am-form-stack" id="preview-contact-form">
      <input type="text" name="name" placeholder="Your name" required class="am-input">
      <input type="email" name="email" placeholder="Email" required class="am-input">
      <textarea name="message" placeholder="How can we help?" required rows="4" class="am-input am-textarea"></textarea>
      <button type="submit" class="am-btn am-btn--primary">Send Message (Preview)</button>
    </form>
  </div>
</section>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function renderAbout() {
    const page = aboutData || {};
    const hero = page.hero || {};
    const story = page.brand_story || {};
    const capabilities = page.capabilities || {};
    const exhibitions = page.exhibitions || {};
    const values = page.values || {};
    const cta = page.cta || {};

    setTitle(page.meta_title || 'About Vyomika Atelier');
    const meta = document.querySelector('meta[name="description"]');
    if (meta && page.meta_description) meta.setAttribute('content', page.meta_description);

    const heroStyle = hero.image ? ` style="--about-hero-img: url('${hero.image}')"` : '';
    const storyParagraphs = (story.paragraphs || []).map((p) => `<p class="am-corten-section__lead">${p}</p>`).join('');
    const capsHtml = (capabilities.items || []).map((item) => `
      <article class="am-about-caps__card am-reveal">
        <a href="${item.href || '#'}" class="am-about-caps__link">
          <div class="am-about-caps__media">
            <img src="${item.image || ''}" alt="${item.name}" loading="lazy">
          </div>
          <div class="am-about-caps__body">
            <h3>${item.name}</h3>
            <p>${item.text}</p>
          </div>
        </a>
      </article>`).join('');

    const eventsHtml = (exhibitions.events || []).map((event) => {
      const galleryHtml = (event.images || []).map((img, i) => `
        <button type="button" class="am-about-gallery__item" data-about-lightbox
          data-src="${img}" data-caption="${event.name} — ${event.location}, ${event.year}"
          aria-label="View ${event.name} photo ${i + 1}">
          <img src="${img}" alt="${event.name} — photo ${i + 1}" loading="lazy">
        </button>`).join('');
      return `
      <article class="am-about-timeline__event am-reveal" id="exhibition-${event.slug}">
        <div class="am-about-timeline__meta">
          <span class="am-about-timeline__year">${event.year}</span>
          <h3 class="am-about-timeline__name">${event.name}</h3>
          <p class="am-about-timeline__location">${event.location}</p>
        </div>
        <div class="am-about-timeline__content">
          ${event.summary ? `<p class="am-about-timeline__summary">${event.summary}</p>` : ''}
          ${galleryHtml ? `<div class="am-about-gallery" data-about-gallery>${galleryHtml}</div>` : ''}
        </div>
      </article>`;
    }).join('');

    const valuesHtml = (values.items || []).map((item) => `
      <article class="am-about-values__card am-reveal">
        <h3>${item.title}</h3>
        <p>${item.text}</p>
      </article>`).join('');

    document.getElementById('am-main').innerHTML = `
<section class="am-about-hero"${heroStyle}>
  <div class="am-container am-about-hero__inner am-reveal">
    ${hero.label ? `<p class="am-page-hero__label">${hero.label}</p>` : ''}
    <h1 class="am-about-hero__title">${hero.title || 'About Vyomika Atelier'}</h1>
    ${hero.subtitle ? `<p class="am-about-hero__subtitle">${hero.subtitle}</p>` : ''}
  </div>
</section>
${storyParagraphs ? `
<section class="am-section am-section--white">
  <div class="am-container am-about-story">
    <div class="am-about-story__copy am-reveal">
      <h2 class="am-corten-section__title">${story.title || 'Crafted Beyond Convention'}</h2>
      ${storyParagraphs}
    </div>
    ${story.image ? `<div class="am-about-story__media am-reveal am-reveal--delay"><img src="${story.image}" alt="Vyomika Atelier studio" loading="lazy"></div>` : ''}
  </div>
</section>` : ''}
${capsHtml ? `
<section class="am-section am-section--cream" id="capabilities">
  <div class="am-container">
    <h2 class="am-corten-section__title am-corten-section__title--center am-reveal">${capabilities.title || 'Capabilities'}</h2>
    <div class="am-about-caps">${capsHtml}</div>
  </div>
</section>` : ''}
${eventsHtml ? `
<section class="am-section am-section--white" id="exhibitions">
  <div class="am-container">
    <div class="am-about-exhibitions__head am-reveal">
      <h2 class="am-corten-section__title">${exhibitions.title || 'Our Exhibition Journey'}</h2>
      ${exhibitions.subtitle ? `<p class="am-corten-section__lead">${exhibitions.subtitle}</p>` : ''}
    </div>
    <div class="am-about-timeline">${eventsHtml}</div>
  </div>
</section>` : ''}
${valuesHtml ? `
<section class="am-section am-section--dark">
  <div class="am-container">
    <h2 class="am-corten-section__title am-corten-section__title--center am-reveal">${values.title || 'What We Stand For'}</h2>
    <div class="am-about-values">${valuesHtml}</div>
  </div>
</section>` : ''}
${cta.title ? `
<section class="am-section am-section--white am-about-cta">
  <div class="am-container am-about-cta__inner am-reveal">
    <div>
      <h2 class="am-corten-section__title">${cta.title}</h2>
      ${cta.body ? `<p class="am-corten-section__lead">${cta.body}</p>` : ''}
    </div>
    <div class="am-about-cta__actions">
      ${cta.cta_primary?.href ? `<a href="${cta.cta_primary.href}" class="am-btn am-btn--primary am-btn--lg">${cta.cta_primary.label}</a>` : ''}
      ${cta.cta_secondary?.label ? contactStudioBtn(cta.cta_secondary.label, 'About page enquiry', 'am-btn am-btn--outline') : ''}
    </div>
  </div>
</section>` : ''}
<div class="am-about-lightbox" id="am-about-lightbox" aria-hidden="true" role="dialog" aria-label="Exhibition photo">
  <button type="button" class="am-about-lightbox__close" data-about-lightbox-close aria-label="Close">&times;</button>
  <button type="button" class="am-about-lightbox__nav am-about-lightbox__nav--prev" data-about-lightbox-prev aria-label="Previous">&lsaquo;</button>
  <figure class="am-about-lightbox__figure">
    <img src="" alt="" class="am-about-lightbox__img" id="am-about-lightbox-img">
    <figcaption class="am-about-lightbox__caption" id="am-about-lightbox-caption"></figcaption>
  </figure>
  <button type="button" class="am-about-lightbox__nav am-about-lightbox__nav--next" data-about-lightbox-next aria-label="Next">&rsaquo;</button>
</div>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function railingRadios(name, legend, options, grid = false) {
    const entries = Object.entries(options || {});
    if (!entries.length) return '';
    const cls = grid ? 'am-railings-form__choices am-railings-form__choices--grid' : 'am-railings-form__choices';
    return `<fieldset class="am-railings-form__group"><legend class="am-pro-form__label">${legend}</legend><div class="${cls}">${entries.map(([v, l]) => `<label class="am-railings-form__choice"><input type="radio" name="${name}" value="${v}" required><span>${l}</span></label>`).join('')}</div></fieldset>`;
  }

  function railingSelect(name, legend, options) {
    return `<label class="am-pro-form__field"><span class="am-pro-form__label">${legend}</span><select name="${name}" class="am-input am-input--select" required><option value="">Select</option>${Object.entries(options || {}).map(([v, l]) => `<option value="${v}">${l}</option>`).join('')}</select></label>`;
  }

  function renderRailings() {
    const page = railingsData || {};
    const hero = page.hero || {};
    const form = page.form || {};
    setTitle(page.meta_title || 'Railings');
    const meta = document.querySelector('meta[name="description"]');
    if (meta && page.meta_description) meta.setAttribute('content', page.meta_description);

    const highlightsHtml = (hero.highlights || []).map((h) => `<li>${h}</li>`).join('');
    const categoriesHtml = (page.categories?.items || []).map((item) => `
      <article class="am-railings-card">
        ${item.image ? `<div class="am-railings-card__media"><img src="${item.image}" alt="${item.title}" loading="lazy"></div>` : ''}
        <div class="am-railings-card__body"><h3>${item.title}</h3><p>${item.text}</p></div>
      </article>`).join('');
    const layoutsHtml = (page.layouts?.items || []).map((item) => `
      <article class="am-railings-layout"><h3>${item.title}</h3><p>${item.text}</p></article>`).join('');
    const whyHtml = (page.why?.items || []).map((item) => `<li>${item}</li>`).join('');

    document.getElementById('am-main').innerHTML = `
<section class="am-railings-hero" style="--railings-hero-img: url('${hero.image || ''}')">
  <div class="am-container am-railings-hero__inner">
    <p class="am-page-hero__label">${hero.label || 'Studio'}</p>
    <h1 class="am-railings-hero__title">${hero.title || 'Railings'}</h1>
    <p class="am-railings-hero__subtitle">${hero.subtitle || ''}</p>
    ${highlightsHtml ? `<ul class="am-pro-hero__highlights">${highlightsHtml}</ul>` : ''}
    <div class="am-pro-hero__actions">
      ${hero.cta_primary ? `<a href="${hero.cta_primary.href}" class="am-btn am-btn--primary">${hero.cta_primary.label}</a>` : ''}
      ${hero.cta_secondary ? `<a href="${hero.cta_secondary.href}" class="am-btn am-btn--outline am-btn--light">${hero.cta_secondary.label}</a>` : ''}
    </div>
  </div>
</section>
${page.intro?.body ? `<section class="am-section am-section--white"><div class="am-container am-railings-intro"><h2 class="am-corten-section__title am-corten-section__title--center">${page.intro.title || ''}</h2><p class="am-corten-section__lead am-corten-section__lead--center">${page.intro.body}</p></div></section>` : ''}
${categoriesHtml ? `<section class="am-section am-section--cream" id="railing-categories"><div class="am-container"><div class="am-railings-section-head"><h2 class="am-corten-section__title">${page.categories.title}</h2>${page.categories.subtitle ? `<p class="am-corten-section__lead">${page.categories.subtitle}</p>` : ''}</div><div class="am-railings-grid">${categoriesHtml}</div></div></section>` : ''}
${layoutsHtml ? `<section class="am-section am-section--white" id="railing-layouts"><div class="am-container"><div class="am-railings-section-head am-railings-section-head--center"><h2 class="am-corten-section__title">${page.layouts.title}</h2>${page.layouts.subtitle ? `<p class="am-corten-section__lead">${page.layouts.subtitle}</p>` : ''}</div><div class="am-railings-layouts">${layoutsHtml}</div></div></section>` : ''}
${whyHtml ? `<section class="am-section am-section--dark"><div class="am-container am-corten-split"><div><h2 class="am-corten-section__title">${page.why.title}</h2><ul class="am-corten-checklist">${whyHtml}</ul></div><div class="am-corten-split__media"><img src="https://images.unsplash.com/photo-1600607687920-4e3a09aebb82?w=900&q=80" alt="Staircase railing" loading="lazy"></div></div></section>` : ''}
<section class="am-section am-section--white am-railings-quote" id="railing-quote">
  <div class="am-container am-railings-quote__grid">
    <div>
      <h2 class="am-corten-section__title">Request a Quotation</h2>
      <p class="am-corten-section__lead">Tell us about your staircase, material preferences and site location. Attach a photo or drawing if you have one.</p>
      <ul class="am-corten-bullets am-railings-quote__bullets"><li>Site measurement in Mumbai metro</li><li>Shop drawings before fabrication</li><li>Glass, stainless, MS and wrought iron</li></ul>
    </div>
    <div class="am-card am-pro-form-card"><div class="am-card__body">
      <form class="am-form-stack am-lead-form am-railings-form" id="am-railings-quote-form">
        ${railingRadios('customer_type', 'Customer type *', form.customer_types)}
        ${railingRadios('usage', 'Indoor or exterior use *', form.usage)}
        ${railingRadios('railing_category', 'Railing category *', form.railing_categories, true)}
        ${railingRadios('layout_shape', 'Staircase / layout shape *', form.layout_shapes, true)}
        <div class="am-pro-form__grid am-pro-form__grid--2">
          ${railingSelect('material', 'Material *', form.materials)}
          ${railingSelect('finish', 'Finish *', form.finishes)}
        </div>
        <div class="am-pro-form__grid">
          <label class="am-pro-form__field"><span class="am-pro-form__label">Approx. running feet *</span><input type="number" name="running_feet" min="1" step="0.5" placeholder="e.g. 24" required class="am-input"></label>
          <label class="am-pro-form__field"><span class="am-pro-form__label">Number of steps</span><input type="number" name="step_count" min="0" placeholder="If applicable" class="am-input"></label>
          ${railingSelect('railing_height', 'Railing height *', form.heights)}
          <label class="am-pro-form__field"><span class="am-pro-form__label">Project location *</span><input type="text" name="project_location" placeholder="City / site" required class="am-input"></label>
        </div>
        ${railingSelect('timeline', 'Timeline *', form.timelines)}
        <label class="am-pro-form__field"><span class="am-pro-form__label">Upload image or drawing</span><input type="file" name="drawing" accept="image/jpeg,image/png,image/webp,application/pdf" class="am-input am-input--file"><span class="am-railings-form__hint">JPG, PNG, WebP or PDF — max 8 MB (preview only)</span></label>
        <div class="am-pro-form__grid">
          <input type="text" name="name" placeholder="Full name *" required class="am-input">
          <input type="tel" name="phone" placeholder="Mobile *" required class="am-input">
          <input type="tel" name="whatsapp" placeholder="WhatsApp" class="am-input">
          <input type="email" name="email" placeholder="Email *" required class="am-input">
        </div>
        <textarea name="message" placeholder="Additional notes…" rows="4" class="am-input am-textarea"></textarea>
        <select name="preferred_contact" class="am-input am-input--select"><option value="">Preferred contact</option><option value="phone">Phone</option><option value="whatsapp">WhatsApp</option><option value="email">Email</option></select>
        <button type="submit" class="am-btn am-btn--primary am-btn--full">Submit Quotation Request (Preview)</button>
      </form>
    </div></div>
  </div>
</section>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function renderMirrorFramesIndex() {
    const page = mirrorFramesData || {};
    const hero = page.hero || {};
    const designs = page.designs || [];
    setTitle(page.meta_title || 'Mirror Frames');
    const meta = document.querySelector('meta[name="description"]');
    if (meta && page.meta_description) meta.setAttribute('content', page.meta_description);

    const highlightsHtml = (hero.highlights || []).map((h) => `<li>${h}</li>`).join('');
    const designsHtml = designs.map((design) => `
      <a href="/collections/mirror-frames/${design.slug}" class="am-design-gallery__card am-mirror-frames-card">
        ${design.image ? `<div class="am-design-gallery__media"><img src="${design.image}" alt="${design.name}" loading="lazy">${design.badge ? `<span class="am-mirror-frames-card__badge">${design.badge}</span>` : ''}</div>` : ''}
        <div class="am-design-gallery__body">
          <h3 class="am-design-gallery__name">${design.name}</h3>
          ${design.description ? `<p class="am-design-gallery__desc">${design.description.slice(0, 90)}${design.description.length > 90 ? '…' : ''}</p>` : ''}
          <span class="am-design-gallery__cta">View &amp; Buy →</span>
        </div>
      </a>`).join('');
    const finishesHtml = (page.finishes?.items || []).map((finish) => `
      <article class="am-mirror-frames-finish">
        ${finish.image ? `<img src="${finish.image}" alt="${finish.name}" loading="lazy">` : ''}
        <p>${finish.name}</p>
      </article>`).join('');

    document.getElementById('am-main').innerHTML = `
<section class="am-mirror-frames-hero" style="--mirror-frames-hero-img: url('${hero.image || ''}')">
  <div class="am-container am-mirror-frames-hero__inner">
    <p class="am-page-hero__label">${hero.label || 'Collections'}</p>
    <h1 class="am-mirror-frames-hero__title">${hero.title || 'Mirror Frames'}</h1>
    <p class="am-mirror-frames-hero__subtitle">${hero.subtitle || ''}</p>
    ${highlightsHtml ? `<ul class="am-pro-hero__highlights">${highlightsHtml}</ul>` : ''}
    <div class="am-pro-hero__actions">
      ${hero.cta_primary ? `<a href="${hero.cta_primary.href}" class="am-btn am-btn--primary">${hero.cta_primary.label}</a>` : ''}
      ${hero.cta_secondary ? `<a href="${hero.cta_secondary.href}" class="am-btn am-btn--outline am-btn--light">${hero.cta_secondary.label}</a>` : ''}
    </div>
  </div>
</section>
${page.intro?.body ? `<section class="am-section am-section--white"><div class="am-container am-mirror-frames-intro"><h2 class="am-corten-section__title am-corten-section__title--center">${page.intro.title || ''}</h2><p class="am-corten-section__lead am-corten-section__lead--center">${page.intro.body}</p></div></section>` : ''}
${designsHtml ? `<section class="am-section am-section--cream am-mirror-frames-designs" id="mirror-designs"><div class="am-container"><div class="am-mirror-frames-section-head"><p class="am-card__label">Design Gallery</p><h2 class="am-corten-section__title">Mirror Frame Designs</h2><p class="am-corten-section__lead">${designs.length} designs · fixed prices · add to bag or buy now</p></div><div class="am-design-gallery__grid am-design-gallery__grid--dense am-mirror-frames-grid">${designsHtml}</div></div></section>` : ''}
${finishesHtml ? `<section class="am-section am-section--white"><div class="am-container"><div class="am-mirror-frames-section-head am-mirror-frames-section-head--center"><h2 class="am-corten-section__title">${page.finishes?.title || 'PVD Frame Finishes'}</h2>${page.finishes?.subtitle ? `<p class="am-corten-section__lead">${page.finishes.subtitle}</p>` : ''}</div><div class="am-mirror-frames-finishes">${finishesHtml}</div></div></section>` : ''}`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function renderMirrorFramesDesign(designSlug) {
    const page = mirrorFramesData || {};
    const design = (page.designs || []).find((d) => d.slug === designSlug);
    if (!design) {
      renderNotFound('/collections/mirror-frames/' + designSlug);
      return;
    }
    const product = mirrorGalleryProducts().find((p) => p.slug === design.product_slug || p.design_slug === designSlug);
    if (!product) {
      renderNotFound('/collections/mirror-frames/' + designSlug);
      return;
    }
    setTitle(design.name);
    const old = product.compare_price ? `<span class="am-featured__price-old">${fmt(product.compare_price)}</span>` : '';
    const badge = product.badge ? `<span class="am-featured__badge">${product.badge}</span>` : '';
    const highlightsHtml = (design.highlights || []).map((h) => `<li>${h}</li>`).join('');
    const related = mirrorGalleryProducts().filter((p) => p.slug !== product.slug).slice(0, 4);

    document.getElementById('am-main').innerHTML = `
<section class="am-page-body am-page-body--pdp am-page-body--mirror-frames">
  <div class="am-container">
    ${breadcrumbs([
      { label: 'Home', url: '/' },
      { label: 'Collections', url: '/shop' },
      { label: 'Mirror Frames', url: '/collections/mirror-frames' },
      { label: design.name },
    ])}
    <div class="am-pdp">
      <div class="am-pdp__gallery">
        <div class="am-pdp__gallery-inner">
          <div class="am-pdp__main"><img src="${product.image}" alt="${design.name}" class="am-pdp__main-img"></div>
        </div>
      </div>
      <div class="am-pdp__info">
        <p class="am-featured__cat">Mirror Frames</p>
        <h1 class="am-pdp__title">${design.name}</h1>
        <p class="am-featured__meta">SKU: ${product.sku} · Pan-India shipping</p>
        <div class="am-featured__price">
          <span class="am-featured__price-current">${fmt(product.price)}</span>${old}${badge}
        </div>
        <ul class="am-pdp__trust">
          <li>✓ PVD stainless frame fabrication</li>
          <li>✓ Secure crated packaging</li>
          <li>✓ Estimated delivery: <strong>3–4 weeks</strong></li>
        </ul>
        ${highlightsHtml ? `<ul class="am-mirror-frames-highlights">${highlightsHtml}</ul>` : ''}
        ${finishSwatchesHtml()}
        <div class="am-prose am-pdp__desc"><p>${design.description || product.description}</p></div>
        <div class="am-pdp__buy-inline">
          ${pdpBuyActionsHtml(product)}
          ${checkoutTrustHtml()}
        </div>
      </div>
    </div>
    ${productTabsHtml(design.name, '<p>' + (design.description || product.description) + '</p>', [], related.map((p) => ({ ...p, href: '/collections/mirror-frames/' + (p.design_slug || p.slug) })), product)}
  </div>
</section>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function renderProfessionals() {
    const page = professionalsData || {};
    const hero = page.hero || {};
    setTitle(page.meta_title || 'Professionals');
    const meta = document.querySelector('meta[name="description"]');
    if (meta && page.meta_description) meta.setAttribute('content', page.meta_description);

    const highlightsHtml = (hero.highlights || []).map((h) => `<li>${h}</li>`).join('');
    const audienceHtml = (page.who_can_apply?.items || []).map((item) => `
      <article class="am-pro-audience__card">
        <h3>${item.title}</h3>
        <p>${item.text}</p>
      </article>`).join('');
    const benefitsHtml = (page.benefits?.items || []).map((item) => `<li>${item}</li>`).join('');
    const tagsHtml = (page.categories?.items || []).map((item) => `<span class="am-pro-tags__item">${item}</span>`).join('');
    const stepsHtml = (page.process?.steps || []).map((step, i) => `
      <article class="am-pro-steps__item">
        <span class="am-pro-steps__num">${i + 1}</span>
        <h3>${step.title}</h3>
        <p>${step.text}</p>
      </article>`).join('');
    const pricingHtml = (page.pricing?.items || []).map((item) => `<li>${item}</li>`).join('');
    const typesHtml = (page.partnership_types?.items || []).map((item) => `
      <article class="am-pro-types__card">
        <h3>${item.title}</h3>
        <p>${item.text}</p>
      </article>`).join('');
    const dealerHtml = (page.dealer_support?.items || []).map((item) => `<li>${item}</li>`).join('');
    const whyHtml = (page.why_partner?.items || []).map((item) => `
      <article>
        <h3>${item.title}</h3>
        <p>${item.text}</p>
      </article>`).join('');
    const faqHtml = (page.faq?.items || []).map((item) => `
      <details class="am-corten-faq__item">
        <summary>${item.q}</summary>
        <p>${item.a}</p>
      </details>`).join('');

    const slugs = page.featured_projects?.slugs || [];
    const portfolio = siteData?.portfolio || [];
    const featured = slugs.map((s) => portfolio.find((p) => p.slug === s)).filter(Boolean);
    const projectsHtml = featured.map((p) => `
      <a href="/projects/${p.slug}" class="am-project-card">
        <div class="am-project-card__media">${p.image ? `<img src="${p.image}" alt="${p.title}" loading="lazy">` : ''}</div>
        <div class="am-project-card__body">
          <p class="am-project-card__meta">
            ${p.category ? `<span>${p.category.charAt(0).toUpperCase() + p.category.slice(1)}</span>` : ''}
            ${p.location ? `<span>${p.location}</span>` : ''}
          </p>
          <h3 class="am-project-card__title">${p.title}</h3>
        </div>
      </a>`).join('');

    const form = page.form || {};
    const interests = form.interest_options || {};
    const years = form.years_options || [];
    const volumes = form.volume_options || [];
    const interestChecks = Object.entries(interests).map(([value, label]) => `
      <label class="am-pro-form__check">
        <input type="checkbox" name="interest_areas[]" value="${value}">
        <span>${label}</span>
      </label>`).join('');
    const yearsOpts = years.map((opt) => `<option value="${opt}">${opt}</option>`).join('');
    const volumeOpts = volumes.map((opt) => `<option value="${opt}">${opt}</option>`).join('');

    const cta = page.final_cta || {};
    const ctaHighlights = (cta.highlights || []).map((h) => `<li>${h}</li>`).join('');

    document.getElementById('am-main').innerHTML = `
<section class="am-pro-hero" style="--pro-hero-img: url('${hero.image || ''}')">
  <div class="am-container am-pro-hero__inner">
    <p class="am-page-hero__label">${hero.label || 'Professionals'}</p>
    <h1 class="am-pro-hero__title">${hero.title || 'Partner with Us'}</h1>
    <p class="am-pro-hero__subtitle">${hero.subtitle || ''}</p>
    ${highlightsHtml ? `<ul class="am-pro-hero__highlights">${highlightsHtml}</ul>` : ''}
    <div class="am-pro-hero__actions">
      ${hero.cta_primary ? `<a href="${hero.cta_primary.href}" class="am-btn am-btn--primary">${hero.cta_primary.label}</a>` : ''}
      ${hero.cta_secondary ? `<a href="${hero.cta_secondary.href}" class="am-btn am-btn--outline am-btn--light">${hero.cta_secondary.label}</a>` : ''}
    </div>
  </div>
</section>
${audienceHtml ? `<section class="am-section am-section--white" id="who-can-apply">
  <div class="am-container">
    <h2 class="am-corten-section__title am-corten-section__title--center">${page.who_can_apply.title}</h2>
    <div class="am-pro-audience">${audienceHtml}</div>
  </div>
</section>` : ''}
${benefitsHtml ? `<section class="am-section am-section--dark" id="partnership-benefits">
  <div class="am-container am-corten-split">
    <div>
      <h2 class="am-corten-section__title">${page.benefits.title}</h2>
      <ul class="am-corten-checklist">${benefitsHtml}</ul>
    </div>
    <div class="am-corten-split__media"><img src="https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=900&q=80" alt="Professional metalwork" loading="lazy"></div>
  </div>
</section>` : ''}
${tagsHtml ? `<section class="am-section am-section--white">
  <div class="am-container">
    <h2 class="am-corten-section__title am-corten-section__title--center">${page.categories.title}</h2>
    <div class="am-pro-tags">${tagsHtml}</div>
  </div>
</section>` : ''}
${stepsHtml ? `<section class="am-section am-section--dark">
  <div class="am-container">
    <h2 class="am-corten-section__title am-corten-section__title--center">${page.process.title}</h2>
    <div class="am-pro-steps">${stepsHtml}</div>
  </div>
</section>` : ''}
<section class="am-section am-section--white am-pro-apply" id="professional-apply">
  <div class="am-container am-pro-apply__grid">
    <div>
      <h2 class="am-corten-section__title">Professional Application</h2>
      <p class="am-corten-section__lead">Tell us about your practice and project focus. Fields marked * are required.</p>
      ${pricingHtml ? `<div class="am-pro-side-note">
        <h3>${page.pricing.title}</h3>
        <ul>${pricingHtml}</ul>
      </div>` : ''}
    </div>
    <div class="am-card am-pro-form-card">
      <div class="am-card__body">
        <form class="am-form-stack am-lead-form am-pro-form" id="am-professional-application-form">
          <div class="am-pro-form__grid">
            <input type="text" name="name" placeholder="Full name *" required class="am-input">
            <input type="text" name="company" placeholder="Company / practice name *" required class="am-input">
            <input type="text" name="role" placeholder="Your role *" required class="am-input">
            <input type="email" name="email" placeholder="Business email *" required class="am-input">
            <input type="tel" name="phone" placeholder="Phone / WhatsApp *" required class="am-input">
            <input type="text" name="city" placeholder="City / state *" required class="am-input">
            <input type="url" name="website" placeholder="Website or portfolio link" class="am-input">
            <input type="text" name="gst_number" placeholder="GST / business registration no." class="am-input">
          </div>
          <div class="am-pro-form__grid am-pro-form__grid--2">
            <label class="am-pro-form__field">
              <span class="am-pro-form__label">Years in business</span>
              <select name="years_in_business" class="am-input am-input--select"><option value="">Select</option>${yearsOpts}</select>
            </label>
            <label class="am-pro-form__field">
              <span class="am-pro-form__label">Estimated project volume</span>
              <select name="budget" class="am-input am-input--select"><option value="">Select</option>${volumeOpts}</select>
            </label>
          </div>
          ${interestChecks ? `<fieldset class="am-pro-form__interests">
            <legend class="am-pro-form__label">Primary interest areas</legend>
            <div class="am-pro-form__checks">${interestChecks}</div>
          </fieldset>` : ''}
          <textarea name="message" placeholder="Current projects, typical specifications, timeline and how we can support your practice… *" required rows="5" class="am-input am-textarea"></textarea>
          <select name="preferred_contact" class="am-input am-input--select">
            <option value="">Preferred contact method</option>
            <option value="email">Email</option>
            <option value="phone">Phone</option>
            <option value="whatsapp">WhatsApp</option>
          </select>
          <button type="submit" class="am-btn am-btn--primary am-btn--full">Submit Application (Preview)</button>
          <p class="am-pro-form__note">By submitting, you agree to be contacted regarding partnership verification. We typically respond within 2–3 business days.</p>
        </form>
      </div>
    </div>
  </div>
</section>
${typesHtml ? `<section class="am-section am-section--dark">
  <div class="am-container">
    <h2 class="am-corten-section__title am-corten-section__title--center">${page.partnership_types.title}</h2>
    <div class="am-pro-types">${typesHtml}</div>
  </div>
</section>` : ''}
${dealerHtml ? `<section class="am-section am-section--white">
  <div class="am-container am-corten-split am-corten-split--reverse">
    <div>
      <h2 class="am-corten-section__title">${page.dealer_support.title}</h2>
      <ul class="am-corten-bullets">${dealerHtml}</ul>
    </div>
    <div class="am-corten-split__media"><img src="https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=900&q=80" alt="Showroom samples" loading="lazy"></div>
  </div>
</section>` : ''}
${whyHtml ? `<section class="am-section am-section--dark">
  <div class="am-container">
    <h2 class="am-corten-section__title am-corten-section__title--center">${page.why_partner.title}</h2>
    <div class="am-pro-why">${whyHtml}</div>
  </div>
</section>` : ''}
${projectsHtml ? `<section class="am-section am-section--white">
  <div class="am-container">
    <div class="am-section-head am-section-head--row">
      <div>
        <h2>${page.featured_projects.title || 'Professional Projects'}</h2>
        ${page.featured_projects.subtitle ? `<p>${page.featured_projects.subtitle}</p>` : ''}
      </div>
      <a href="/projects" class="am-section-head__link">View all projects →</a>
    </div>
    <div class="am-project-grid">${projectsHtml}</div>
  </div>
</section>` : ''}
${faqHtml ? `<section class="am-section am-section--dark">
  <div class="am-container am-corten-faq-wrap">
    <h2 class="am-corten-section__title am-corten-section__title--center">${page.faq.title}</h2>
    <div class="am-corten-faq am-corten-faq--light">${faqHtml}</div>
  </div>
</section>` : ''}
${cta.title ? `<section class="am-section am-section--white am-pro-final-cta">
  <div class="am-container am-pro-final-cta__inner">
    <div>
      <h2 class="am-corten-section__title">${cta.title}</h2>
      <p class="am-corten-section__lead">${cta.body || ''}</p>
      ${ctaHighlights ? `<ul class="am-pro-hero__highlights am-pro-hero__highlights--dark">${ctaHighlights}</ul>` : ''}
    </div>
    <a href="#professional-apply" class="am-btn am-btn--primary am-btn--lg">Register Now</a>
  </div>
</section>` : ''}`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function renderAccountAuthPreview(mode) {
    const titles = { login: 'Sign in', register: 'Create account', verify: 'Verify your number', forgot: 'Forgot access' };
    setTitle(titles[mode] || 'Account');

    const tab = (name, active) =>
      `<a href="/account/${name === 'login' ? 'login' : 'register'}" class="am-account-card__tab${active ? ' is-active' : ''}">${name === 'login' ? 'Sign in' : 'Create account'}</a>`;

    const submitBtn = (label) =>
      `<button type="button" class="am-account-card__submit" disabled><span>${label}</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>`;

    const field = (label, input) =>
      `<div class="am-account-card__field"><label>${label}</label>${input}</div>`;

    const phoneRow = `<div class="am-account-phone"><select class="am-input am-input--select" disabled><option>India (+91)</option></select><input type="tel" class="am-input" placeholder="Mobile number" disabled></div>`;

    const otpInputs = Array.from({ length: 6 }, (_, i) =>
      `<input type="text" class="am-account-otp__digit" maxlength="1" inputmode="numeric" aria-label="Digit ${i + 1} of 6" disabled>`
    ).join('');

    let cardInner = '';
    if (mode === 'verify') {
      cardInner = `
        <h1 class="am-account-card__title">Verify your number</h1>
        <p class="am-account-verify__hint">Enter the 6-digit verification code sent to your WhatsApp number.</p>
        <div class="am-account-otp" role="group" aria-label="6-digit verification code">${otpInputs}</div>
        ${submitBtn('Verify &amp; Continue')}
        <p class="am-account-verify__countdown">Resend available in preview only via Laravel.</p>`;
    } else if (mode === 'forgot') {
      cardInner = `
        <h1 class="am-account-card__title">Forgot access</h1>
        <p class="am-account-card__lead">We will send a WhatsApp OTP to your registered mobile number.</p>
        <p class="am-account-notice am-account-notice--warning" role="status">Preview mode — forms are disabled.</p>
        ${field('Mobile', phoneRow)}
        ${submitBtn('Send WhatsApp OTP')}
        <p class="am-account-card__footer-link"><a href="/account/login">Back to sign in</a></p>`;
    } else if (mode === 'register') {
      cardInner = `
        <nav class="am-account-card__tabs" aria-label="Account">${tab('login', false)}${tab('register', true)}</nav>
        <p class="am-account-notice am-account-notice--warning" role="status">Preview mode — forms are disabled. Run <code>php artisan serve</code> for live account flows.</p>
        ${field('Full name', '<input type="text" class="am-input" disabled>')}
        ${field('Email', '<input type="email" class="am-input" disabled>')}
        ${field('Password', '<input type="password" class="am-input" disabled>')}
        <div class="am-account-card__columns">
          ${field('Mobile', phoneRow)}
          ${field('WhatsApp', '<input type="tel" class="am-input" placeholder="Same as mobile if blank" disabled>')}
        </div>
        ${field('City', '<input type="text" class="am-input" disabled>')}
        ${field('Account type', '<select class="am-input am-input--select" disabled><option>Customer</option></select>')}
        ${submitBtn('Create account')}`;
    } else {
      cardInner = `
        <nav class="am-account-card__tabs" aria-label="Account">${tab('login', true)}${tab('register', false)}</nav>
        <p class="am-account-notice am-account-notice--warning" role="status">Preview mode — forms are disabled. Run <code>php artisan serve</code> for live account flows.</p>
        ${field('Email', '<input type="email" class="am-input" disabled>')}
        ${field('Password', '<input type="password" class="am-input" disabled>')}
        ${submitBtn('Sign in')}
        <p class="am-account-card__footer-link"><a href="/account/forgot">Forgot password or access?</a></p>`;
    }

    document.getElementById('am-main').innerHTML = `
<section class="am-page-body am-page-body--account-auth">
  <div class="am-container am-account-auth-layout">
    <div class="am-account-auth-card-wrap">
      <div class="am-account-card${mode === 'verify' ? ' am-account-card--verify' : ''}">
        ${cardInner}
      </div>
    </div>
  </div>
</section>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function renderAccount() {
    renderAccountAuthPreview('login');
  }

  function renderCheckoutSuccess() {
    setTitle('Order Confirmed');
    document.getElementById('am-main').innerHTML = `
${pageHero('Thank You', 'Order Confirmed', '')}
<section class="am-page-body">
  <div class="am-container am-checkout-flow am-checkout-flow--centered">
    ${checkoutStepsHtml(4)}
    <div class="am-checkout-success-card am-card"><div class="am-card__body">
      <div class="am-checkout-success-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/></svg></div>
      <h2 class="am-checkout-success-card__title">Thank you for your order</h2>
      <p class="am-checkout-success-card__text">Your order has been placed successfully. (Preview mode)</p>
      <p class="am-checkout-success-card__order">Order #PREVIEW-001</p>
      <p class="am-checkout-success-card__email">Confirmation would be emailed in the live store.</p>
      <div class="am-checkout-success-card__actions">
        <a href="/shop" class="am-btn am-btn--primary">Continue Shopping</a>
        ${contactStudioBtn('Contact Us', 'Order confirmation', 'am-btn am-btn--outline')}
      </div>
    </div></div>
  </div>
</section>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function renderSitemap() {
    setTitle('Sitemap');
    const links = [
      '/', '/shop', '/services', '/projects', '/blog', '/about', '/professionals', '/studio/railings', '/collections/mirror-frames', '/contact', '/custom-order',
      '/privacy-policy', '/terms-and-conditions', '/shipping-delivery-policy',
    ];
    (siteData.portfolio || []).forEach((p) => links.push('/projects/' + p.slug));
    (blogData?.posts || siteData.blog?.posts || []).forEach((p) => links.push('/blog/' + p.slug));
    collectProducts(siteData).forEach((p) => links.push('/shop/' + p.slug));
    (mirrorFramesData?.designs || []).forEach((d) => links.push('/collections/mirror-frames/' + d.slug));
    document.getElementById('am-main').innerHTML = `
<section class="am-page-body">
  <div class="am-container">
    <h1 class="am-page-hero__title" style="margin-bottom:1.5rem">Sitemap</h1>
    <ul class="am-sitemap-list">${[...new Set(links)].map((href) => `<li><a href="${href}">${href}</a></li>`).join('')}</ul>
    <p class="am-card__text" style="margin-top:2rem">Full XML sitemap available at <code>/sitemap.xml</code> on the Laravel site.</p>
  </div>
</section>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function renderTeam() {
    setTitle('Our Team');
    const team = siteData.team || [];
    document.getElementById('am-main').innerHTML = `
${pageHero('The Studio', 'Our Team', 'Fabricators, designers, and project leads united by precision metalwork.')}
<section class="am-page-body">
  <div class="am-container">
    <div class="am-team-grid">
      ${team.map((m) => `
      <article class="am-team-card">
        <img src="${m.image}" alt="${m.name}" loading="lazy">
        <h3>${m.name}</h3>
        <p>${m.role}</p>
      </article>`).join('')}
    </div>
    <div style="text-align:center;margin-top:4rem">
      ${contactStudioBtn('Work With Us →', 'Work with our team', 'am-btn am-btn--outline')}
    </div>
  </div>
</section>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function renderLeadForm(title, subtitle) {
    setTitle(title);
    document.getElementById('am-main').innerHTML = `
${pageHero('Quote', title, subtitle)}
<section class="am-page-body">
  <div class="am-container" style="max-width:36rem">
    <form class="am-form-stack" id="preview-lead-form">
      <input type="text" name="name" placeholder="Your name" required class="am-input">
      <input type="email" name="email" placeholder="Email" required class="am-input">
      <input type="tel" name="phone" placeholder="Phone / WhatsApp" required class="am-input">
      <textarea name="message" placeholder="Dimensions, finish, timeline…" required rows="4" class="am-input am-textarea"></textarea>
      <button type="submit" class="am-btn am-btn--primary am-btn--full">Submit Request (Preview)</button>
    </form>
  </div>
</section>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function renderNotFound(path) {
    setTitle('Not Found');
    document.getElementById('am-main').innerHTML = `
<section class="am-page-body">
  <div class="am-container am-empty">
    <h1>Page not found</h1>
    <p>No preview page for <code>${path}</code></p>
    <a href="/" class="am-btn am-btn--primary">Home</a>
    <a href="/shop" class="am-btn am-btn--outline">Shop</a>
  </div>
</section>`;
    document.dispatchEvent(new CustomEvent('am-content-ready'));
  }

  function navigate(pathname, search, push) {
    const path = pathname || '/';
    if (push) history.pushState({ path, search }, '', path + (search || ''));
    window.scrollTo(0, 0);
    renderRoute(path, search);
  }

  function onLinkClick(e) {
    const a = e.target.closest('a[href]');
    if (!a || a.target === '_blank' || a.hasAttribute('download')) return;
    const href = a.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
    if (href.startsWith('http') && !href.startsWith(location.origin)) return;
    const url = new URL(href, location.origin);
    e.preventDefault();
    navigate(url.pathname, url.search, true);
  }

  function onFormSubmit(e) {
    const form = e.target.closest('[data-preview-form]');
    if (form) {
      e.preventDefault();
      const fd = new FormData(form);
      const params = new URLSearchParams();
      fd.forEach((v, k) => { if (v) params.set(k, v); });
      navigate('/shop', '?' + params.toString(), true);
      return;
    }
    const shopForm = e.target.closest('form[action="/shop"], form[action$="/shop"]');
    if (shopForm) {
      e.preventDefault();
      const fd = new FormData(shopForm);
      const params = new URLSearchParams();
      fd.forEach((v, k) => { if (v) params.set(k, v); });
      navigate('/shop', '?' + params.toString(), true);
    }
  }

  document.addEventListener('click', (e) => {
    const buyNowBtn = e.target.closest('[data-buy-now]');
    if (buyNowBtn) {
      e.preventDefault();
      const slug = buyNowBtn.getAttribute('data-buy-now');
      const product = collectProducts(siteData).find((p) => p.slug === slug);
      const qty = Number(document.getElementById('pdp-qty')?.value) || 1;
      if (product) window.AmPreviewCart.add(product, qty);
      navigate('/checkout', '', true);
      return;
    }
    const addBtn = e.target.closest('[data-add-cart]');
    if (addBtn) {
      e.preventDefault();
      const slug = addBtn.getAttribute('data-add-cart');
      const product = collectProducts(siteData).find((p) => p.slug === slug);
      const qty = Number(document.getElementById('pdp-qty')?.value) || 1;
      if (product) window.AmPreviewCart.add(product, qty);
      document.getElementById('am-cart-drawer')?.classList.add('is-open');
      document.getElementById('am-overlay')?.classList.add('is-open');
      return;
    }
    const addLink = e.target.closest('[data-add-cart-link]');
    if (addLink) {
      e.preventDefault();
      const slug = addLink.getAttribute('data-add-cart-link');
      const product = collectProducts(siteData).find((p) => p.slug === slug);
      const qty = Number(document.getElementById('pdp-qty')?.value) || 1;
      if (product) window.AmPreviewCart.add(product, qty);
      navigate('/cart', '', true);
      return;
    }
    const qtyBtn = e.target.closest('[data-cart-qty]');
    if (qtyBtn) {
      e.preventDefault();
      const slug = qtyBtn.getAttribute('data-cart-qty');
      const delta = Number(qtyBtn.getAttribute('data-delta'));
      const item = window.AmPreviewCart.read().find((i) => i.slug === slug);
      if (item) window.AmPreviewCart.setQty(slug, item.qty + delta);
      renderCart();
      return;
    }
  });

  document.addEventListener('submit', (e) => {
    if (e.target.id === 'preview-checkout-form' || e.target.id === 'preview-contact-form' || e.target.id === 'preview-lead-form' || e.target.id === 'preview-service-lead-form' || e.target.id === 'va-order-form' || e.target.id === 'am-professional-application-form' || e.target.id === 'am-railings-quote-form') {
      e.preventDefault();
      alert('Preview mode: form saved locally only. Run full Laravel preview for real submissions.');
    }
  });

  document.addEventListener('click', onLinkClick);
  document.addEventListener('submit', onFormSubmit);
  window.addEventListener('popstate', () => {
    renderRoute(location.pathname, location.search);
  });

  function boot() {
    let pending = 9;
    function done() {
      pending -= 1;
      if (pending > 0) return;
      const path = location.pathname === '/preview.html' ? '/' : location.pathname;
      if (location.pathname === '/preview.html') {
        history.replaceState({}, '', path + location.search);
      }
      renderRoute(path, location.search);
    }

    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'data/site-content.json', true);
    xhr.onload = function () {
      if (xhr.status >= 200 && xhr.status < 300) {
        try {
          siteData = JSON.parse(xhr.responseText);
        } catch (err) {
          console.error(err);
        }
      }
      done();
    };
    xhr.onerror = done;
    xhr.send();

    const legalXhr = new XMLHttpRequest();
    legalXhr.open('GET', 'data/legal.json', true);
    legalXhr.onload = function () {
      if (legalXhr.status >= 200 && legalXhr.status < 300) {
        try {
          legalData = JSON.parse(legalXhr.responseText);
        } catch (err) {
          console.error(err);
        }
      }
      done();
    };
    legalXhr.onerror = done;
    legalXhr.send();

    const cortenXhr = new XMLHttpRequest();
    cortenXhr.open('GET', 'data/corten.json', true);
    cortenXhr.onload = function () {
      if (cortenXhr.status >= 200 && cortenXhr.status < 300) {
        try {
          cortenData = JSON.parse(cortenXhr.responseText);
        } catch (err) {
          console.error(err);
        }
      }
      done();
    };
    cortenXhr.onerror = done;
    cortenXhr.send();

    const proXhr = new XMLHttpRequest();
    proXhr.open('GET', 'data/professionals.json', true);
    proXhr.onload = function () {
      if (proXhr.status >= 200 && proXhr.status < 300) {
        try {
          professionalsData = JSON.parse(proXhr.responseText);
        } catch (err) {
          console.error(err);
        }
      }
      done();
    };
    proXhr.onerror = done;
    proXhr.send();

    const aboutXhr = new XMLHttpRequest();
    aboutXhr.open('GET', 'data/about.json', true);
    aboutXhr.onload = function () {
      if (aboutXhr.status >= 200 && aboutXhr.status < 300) {
        try {
          aboutData = JSON.parse(aboutXhr.responseText);
        } catch (err) {
          console.error(err);
        }
      }
      done();
    };
    aboutXhr.onerror = done;
    aboutXhr.send();

    const blogXhr = new XMLHttpRequest();
    blogXhr.open('GET', 'data/blog.json', true);
    blogXhr.onload = function () {
      if (blogXhr.status >= 200 && blogXhr.status < 300) {
        try {
          blogData = JSON.parse(blogXhr.responseText);
        } catch (err) {
          console.error(err);
        }
      }
      done();
    };
    blogXhr.onerror = done;
    blogXhr.send();

    const railXhr = new XMLHttpRequest();
    railXhr.open('GET', 'data/railings.json', true);
    railXhr.onload = function () {
      if (railXhr.status >= 200 && railXhr.status < 300) {
        try {
          railingsData = JSON.parse(railXhr.responseText);
        } catch (err) {
          console.error(err);
        }
      }
      done();
    };
    railXhr.onerror = done;
    railXhr.send();

    const mirrorXhr = new XMLHttpRequest();
    mirrorXhr.open('GET', 'data/mirror-frames.json', true);
    mirrorXhr.onload = function () {
      if (mirrorXhr.status >= 200 && mirrorXhr.status < 300) {
        try {
          mirrorFramesData = JSON.parse(mirrorXhr.responseText);
        } catch (err) {
          console.error(err);
        }
      }
      done();
    };
    mirrorXhr.onerror = done;
    mirrorXhr.send();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
