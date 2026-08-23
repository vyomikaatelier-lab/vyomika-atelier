<?php

/**
 * Blog content library manifest — global + regional articles for blog:import-content.
 *
 * Body HTML lives in database/content/blog/articles/{slug}.php
 * Regional entries (locale set) require --regional flag; default import is global-only.
 *
 * Published status in manifest applies only to NEW records. On apply, BlogContentImporter
 * preserves existing DB status and published_at for matching slugs (stripPreservedFieldsFromUpdate).
 *
 * @return list<array<string, mixed>>
 */
return array (
  0 => 
  array (
    'slug' => 'glass-partitions-open-plan',
    'title' => 'Glass Partitions: Open Plan Without Compromise',
    'category' => 'PVD Partitions',
    'cluster' => 'PVD PARTITIONS',
    'primary_keyword' => 'glass partitions',
    'status' => 'published',
    'published_at' => '2026-06-15',
    'is_featured' => true,
    'image' => '/images/blog/heroes/glass-partitions-open-plan-hero.jpg',
    'hero_image_alt' => 'Gold-finished metal and glass partition installed in a Vyomika Atelier living-room project',
    'og_image' => '/images/blog/heroes/glass-partitions-open-plan-hero-card.jpg',
    'meta_title' => 'Glass Partitions: Open Plan Without Compromise | Vyomika Atelier',
    'meta_description' => 'Plan glass and metal partitions that keep open-plan light and flow while defining zones — materials, privacy levels and coordination for Indian homes and offices.',
    'related_service_slugs' => 
    array (
      0 => 'partitions',
    ),
    'related_product_slugs' => 
    array (
      0 => 'champagne-wave-partition',
      1 => 'rose-gold-room-divider',
      2 => 'veil-fluted-panel',
    ),
    'related_project_slugs' => 
    array (
      0 => 'champagne-wave-office-lobby',
      1 => 'penthouse-living-divider',
    ),
    'related_article_slugs' => 
    array (
      0 => 'pvd-partitions-materials-finishes-applications-cost-factors',
      1 => 'how-to-select-metal-partition-for-living-room',
      2 => 'pvd-partitions-vs-powder-coated-metal-partitions',
    ),
    'excerpt' => 'Zone open-plan Indian homes and offices with glass and PVD metal partitions that keep daylight, flow and privacy in balance — materials, fixings and maintenance.',
    'import_eligible' => true,
  ),
  1 => 
  array (
    'slug' => 'pvd-coating-explained',
    'title' => 'PVD Coating Explained: Durable Metal Finishes',
    'category' => 'PVD Finishes',
    'cluster' => 'PVD HARDWARE/FURNITURE',
    'primary_keyword' => 'PVD coating',
    'status' => 'published',
    'published_at' => '2026-06-10',
    'image' => '/images/blog/heroes/pvd-coating-explained-hero.jpg',
    'hero_image_alt' => 'Close-up of a brushed gold PVD-coated metal plaque stencilled LED PROFILE PVD PARTITION, reflected on a glossy surface',
    'og_image' => '/images/blog/heroes/pvd-coating-explained-hero-card.jpg',
    'meta_title' => 'PVD Coating Explained: Durable Metal Finishes | Vyomika Atelier',
    'meta_description' => 'What PVD coating is, how it differs from plating and powder coat, and how architects specify durable gold, champagne and black finishes on stainless metalwork.',
    'related_service_slugs' => 
    array (
      0 => 'partitions',
      1 => 'main-entrance-pvd-doors',
    ),
    'related_product_slugs' => 
    array (
      0 => 'pvd-door-pull-handle',
      1 => 'champagne-wave-partition',
    ),
    'related_article_slugs' => 
    array (
      0 => 'pvd-finish-selection-guide-gold-rose-gold-champagne-black',
      1 => 'pvd-partitions-vs-powder-coated-metal-partitions',
      2 => 'pvd-door-handles-finishes-sizes-selection-guide',
    ),
    'excerpt' => 'What PVD coating is on stainless metalwork, how it differs from powder coat and plating, and how architects specify durable champagne, gold and black finishes.',
    'import_eligible' => true,
  ),
  2 => 
  array (
    'slug' => 'corten-steel-modern-facades',
    'title' => 'Why Corten Steel Is Perfect for Modern Façades',
    'category' => 'Corten Steel',
    'cluster' => 'CORTEN STEEL',
    'primary_keyword' => 'Corten steel facade',
    'status' => 'published',
    'published_at' => '2026-06-05',
    'image' => '/images/blog/heroes/corten-steel-modern-facades-hero.jpg',
    'hero_image_alt' => 'Representative contemporary Indian building visualised with weathered Corten steel façade panels and perforated screens',
    'og_image' => '/images/blog/heroes/corten-steel-modern-facades-hero.jpg',
    'meta_title' => 'Why Corten Steel Suits Modern Façades | Vyomika Atelier',
    'meta_description' => 'How weathering steel brings warmth and depth to modern Indian façades — patina behaviour, design applications and practical specification notes for architects.',
    'related_project_slugs' => 
    array (
      0 => 'corten-entrance-screen',
    ),
    'related_article_slugs' => 
    array (
      0 => 'what-is-corten-steel-and-how-does-it-weather',
      1 => 'corten-steel-facades-design-drainage-weathering',
      2 => 'corten-steel-cladding-vs-conventional-painted-steel',
    ),
    'excerpt' => 'Why weathering steel suits modern Indian facades: patina character, design uses, drainage detailing and specification notes for architects and developers.',
    'import_eligible' => true,
  ),
  3 => 
  array (
    'slug' => 'pvd-partitions-materials-finishes-applications-cost-factors',
    'title' => 'PVD Partitions: Materials, Finishes, Applications and Cost Factors',
    'category' => 'PVD Partitions',
    'cluster' => 'PVD PARTITIONS',
    'primary_keyword' => 'PVD partition',
    'status' => 'draft',
    'published_at' => '2026-08-26 09:00:00',
    'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/722414.jpeg',
    'hero_image_alt' => 'Fluted PVD metal partition panel in a contemporary interior',
    'meta_title' => 'PVD Partitions Guide: Materials, Finishes & Cost Factors | Vyomika Atelier',
    'meta_description' => 'Understand PVD partition materials, finish options, living-room and office applications, and the factors that shape a project quotation in India.',
    'related_service_slugs' => 
    array (
      0 => 'partitions',
    ),
    'related_product_slugs' => 
    array (
      0 => 'veil-fluted-panel',
      1 => 'laser-cut-partition',
    ),
    'related_article_slugs' => 
    array (
      0 => 'glass-partitions-open-plan',
      1 => 'pvd-partition-price-in-india-what-determines-final-cost',
      2 => 'pvd-partitions-vs-powder-coated-metal-partitions',
    ),
    'excerpt' => 'Specify PVD metal partitions with confidence: materials, finish options, living-room and office applications, and factors that shape a project quotation in India.',
    'import_eligible' => true,
  ),
  4 => 
  array (
    'slug' => 'pvd-partition-price-in-india-what-determines-final-cost',
    'title' => 'PVD Partition Price in India: What Determines the Final Cost?',
    'category' => 'PVD Partitions',
    'cluster' => 'PVD PARTITIONS',
    'primary_keyword' => 'PVD partition price in India',
    'status' => 'draft',
    'published_at' => '2026-08-29 09:00:00',
    'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
    'hero_image_alt' => 'Custom PVD partition being measured on site for quotation',
    'meta_title' => 'PVD Partition Price in India: Cost Factors Explained | Vyomika Atelier',
    'meta_description' => 'Learn which factors determine PVD partition pricing in India — size, finish, glass, hardware and site conditions — without relying on misleading list prices.',
    'related_service_slugs' => 
    array (
      0 => 'partitions',
    ),
    'related_product_slugs' => 
    array (
      0 => 'champagne-wave-partition',
    ),
    'related_article_slugs' => 
    array (
      0 => 'pvd-partitions-materials-finishes-applications-cost-factors',
      1 => 'how-to-select-metal-partition-for-living-room',
      2 => 'pvd-coating-explained',
    ),
    'excerpt' => 'Understand PVD partition pricing in India — size, glass, hardware, finish and site conditions — without misleading online list prices for bespoke metalwork.',
    'import_eligible' => true,
  ),
  5 => 
  array (
    'slug' => 'pvd-partitions-vs-powder-coated-metal-partitions',
    'title' => 'PVD Partitions vs Powder-Coated Metal Partitions',
    'category' => 'PVD Partitions',
    'cluster' => 'PVD PARTITIONS',
    'primary_keyword' => 'PVD partition vs powder coating',
    'status' => 'draft',
    'image' => 'https://www.vyomikaatelier.com/assets/campaign-partitions.jpeg',
    'hero_image_alt' => 'Matte black PVD partition beside a powder-coated metal screen for finish comparison',
    'meta_title' => 'PVD vs Powder-Coated Metal Partitions | Vyomika Atelier',
    'meta_description' => 'Compare PVD and powder-coated metal partitions for appearance, touch durability and maintenance — a practical guide for Indian interior specifications.',
    'related_service_slugs' => 
    array (
      0 => 'partitions',
    ),
    'related_product_slugs' => 
    array (
      0 => 'matte-black-pvd-partition',
    ),
    'related_article_slugs' => 
    array (
      0 => 'pvd-coating-explained',
      1 => 'glass-partitions-open-plan',
      2 => 'pvd-finish-selection-guide-gold-rose-gold-champagne-black',
    ),
    'excerpt' => 'Compare PVD and powder-coated metal partitions for appearance, touch durability and maintenance when specifying interior metalwork for Indian homes and offices.',
    'import_eligible' => true,
  ),
  6 => 
  array (
    'slug' => 'how-to-select-metal-partition-for-living-room',
    'title' => 'How to Select a Metal Partition for a Living Room',
    'category' => 'Interior Metalwork',
    'cluster' => 'PVD PARTITIONS',
    'primary_keyword' => 'metal partition for living room',
    'status' => 'draft',
    'published_at' => '2026-09-05 09:00:00',
    'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/722414.jpeg',
    'hero_image_alt' => 'Rose gold PVD room divider separating a living and dining zone in an Indian apartment',
    'meta_title' => 'Metal Partition for Living Room: Selection Guide | Vyomika Atelier',
    'meta_description' => 'Plan a living-room metal partition for privacy, light and proportion — glass choices, PVD finishes and circulation clearances for Indian homes.',
    'related_service_slugs' => 
    array (
      0 => 'partitions',
    ),
    'related_product_slugs' => 
    array (
      0 => 'rose-gold-room-divider',
    ),
    'related_project_slugs' => 
    array (
      0 => 'penthouse-living-divider',
    ),
    'related_article_slugs' => 
    array (
      0 => 'glass-partitions-open-plan',
      1 => 'pvd-partitions-materials-finishes-applications-cost-factors',
      2 => 'pvd-partition-price-in-india-what-determines-final-cost',
    ),
    'excerpt' => 'Plan a living-room metal partition for privacy, light and proportion — glass choices, PVD finishes and circulation clearances for Indian apartments and villas.',
    'import_eligible' => true,
  ),
  7 => 
  array (
    'slug' => 'slim-profile-doors-hinged-sliding-telescopic-compared',
    'title' => 'Slim Profile Doors: Hinged, Sliding and Telescopic Systems Compared',
    'category' => 'Slim Profile Doors',
    'cluster' => 'SLIM PROFILE/ENTRANCE DOORS',
    'primary_keyword' => 'slim profile doors',
    'status' => 'draft',
    'image' => '/images/shop-heroes/slim-profile-doors-hero.png',
    'hero_image_alt' => 'Slim profile sliding door with minimal frame connecting a living room to a terrace',
    'meta_title' => 'Slim Profile Doors Compared: Hinged, Sliding & Telescopic | Vyomika Atelier',
    'meta_description' => 'Compare slim profile hinged, sliding and telescopic door systems for Indian homes — space planning, glass options and hardware considerations.',
    'related_service_slugs' => 
    array (
      0 => 'slim-profile-door-system',
    ),
    'related_product_slugs' => 
    array (
      0 => 'slim-profile-door',
    ),
    'related_article_slugs' => 
    array (
      0 => 'fluted-glass-slim-profile-doors-design-privacy-guide',
      1 => 'how-to-choose-luxury-main-entrance-door',
      2 => 'stainless-steel-glass-etched-entrance-doors-compared',
    ),
    'excerpt' => 'Compare slim profile hinged, sliding and telescopic door systems for Indian homes — space planning, glass options and hardware selection for interior openings.',
    'import_eligible' => true,
  ),
  8 => 
  array (
    'slug' => 'fluted-glass-slim-profile-doors-design-privacy-guide',
    'title' => 'Fluted Glass for Slim Profile Doors: Design and Privacy Guide',
    'category' => 'Slim Profile Doors',
    'cluster' => 'SLIM PROFILE/ENTRANCE DOORS',
    'primary_keyword' => 'fluted glass slim profile doors',
    'status' => 'draft',
    'image' => '/images/shop-heroes/slim-profile-doors-hero.png',
    'hero_image_alt' => 'Fluted glass panel in a slim profile door diffusing light in a bathroom suite',
    'meta_title' => 'Fluted Glass Slim Profile Doors: Design & Privacy | Vyomika Atelier',
    'meta_description' => 'How fluted and reeded glass works with slim profile doors for privacy, light diffusion and contemporary Indian interiors.',
    'related_service_slugs' => 
    array (
      0 => 'slim-profile-door-system',
    ),
    'related_article_slugs' => 
    array (
      0 => 'slim-profile-doors-hinged-sliding-telescopic-compared',
      1 => 'glass-partitions-open-plan',
      2 => 'how-to-choose-luxury-main-entrance-door',
    ),
    'excerpt' => 'Use fluted and reeded glass with slim profile doors for privacy and light diffusion in bathrooms, bedrooms and office cabins across Indian interior projects.',
    'import_eligible' => true,
  ),
  9 => 
  array (
    'slug' => 'how-to-choose-luxury-main-entrance-door',
    'title' => 'How to Choose a Luxury Main Entrance Door',
    'category' => 'Entrance Doors',
    'cluster' => 'SLIM PROFILE/ENTRANCE DOORS',
    'primary_keyword' => 'luxury main entrance door',
    'status' => 'draft',
    'image' => '/images/shop-heroes/slim-profile-doors-hero.png',
    'hero_image_alt' => 'Luxury main entrance door with PVD stainless frame and fluted glass infill',
    'meta_title' => 'How to Choose a Luxury Main Entrance Door | Vyomika Atelier',
    'meta_description' => 'A selection framework for luxury main entrance doors — proportion, PVD finishes, security hardware and coordination with façade design in India.',
    'related_service_slugs' => 
    array (
      0 => 'main-entrance-pvd-doors',
    ),
    'related_product_slugs' => 
    array (
      0 => 'slim-profile-door',
      1 => 'pvd-door-pull-handle',
    ),
    'related_project_slugs' => 
    array (
      0 => 'villa-entrance-pvd-doors',
    ),
    'related_article_slugs' => 
    array (
      0 => 'stainless-steel-glass-etched-entrance-doors-compared',
      1 => 'select-pull-handle-length-for-main-door',
      2 => 'pvd-coating-explained',
    ),
    'excerpt' => 'Choose a luxury main entrance door for Indian residences — proportion, PVD finishes, security hardware and coordination with the building facade and landscape.',
    'import_eligible' => true,
  ),
  10 => 
  array (
    'slug' => 'stainless-steel-glass-etched-entrance-doors-compared',
    'title' => 'Stainless Steel, Glass and Etched Entrance Doors Compared',
    'category' => 'Entrance Doors',
    'cluster' => 'SLIM PROFILE/ENTRANCE DOORS',
    'primary_keyword' => 'designer stainless steel doors',
    'status' => 'draft',
    'image' => '/images/shop-heroes/slim-profile-doors-hero.png',
    'hero_image_alt' => 'Designer stainless steel entrance door with etched glass pattern and PVD frame',
    'meta_title' => 'Stainless Steel & Glass Entrance Doors Compared | Vyomika Atelier',
    'meta_description' => 'Compare stainless, glass-forward and etched designer entrance doors for Indian homes — visual weight, privacy and maintenance considerations.',
    'related_service_slugs' => 
    array (
      0 => 'main-entrance-pvd-doors',
    ),
    'related_article_slugs' => 
    array (
      0 => 'how-to-choose-luxury-main-entrance-door',
      1 => 'fluted-glass-slim-profile-doors-design-privacy-guide',
      2 => 'slim-profile-doors-hinged-sliding-telescopic-compared',
    ),
    'excerpt' => 'Compare stainless, glass-forward and etched entrance doors for Indian homes — visual weight, privacy levels and long-term maintenance in varied climates.',
    'import_eligible' => true,
  ),
  11 => 
  array (
    'slug' => 'stainless-steel-railings-types-finishes-selection-guide',
    'title' => 'Stainless Steel Railings: Types, Finishes and Selection Guide',
    'category' => 'Railings',
    'cluster' => 'RAILINGS',
    'primary_keyword' => 'stainless steel railings',
    'status' => 'draft',
    'image' => '/images/shop-heroes/railings-hero.png',
    'hero_image_alt' => 'Stainless steel staircase railing with PVD handrail in a contemporary Indian home',
    'meta_title' => 'Stainless Steel Railings Guide: Types & Finishes | Vyomika Atelier',
    'meta_description' => 'Select stainless steel railing systems for staircases and balconies — glass, bar and panel options with finish guidance for Indian projects.',
    'related_article_slugs' => 
    array (
      0 => 'glass-railings-staircases-balconies-planning-checklist',
      1 => 'interior-vs-exterior-railings-material-finish',
      2 => 'pvd-coating-explained',
    ),
    'excerpt' => 'Select stainless steel railings for staircases and balconies — glass, bar and panel systems with finish guidance for Indian residential and commercial projects.',
    'import_eligible' => true,
  ),
  12 => 
  array (
    'slug' => 'glass-railings-staircases-balconies-planning-checklist',
    'title' => 'Glass Railings for Staircases and Balconies: Planning Checklist',
    'category' => 'Railings',
    'cluster' => 'RAILINGS',
    'primary_keyword' => 'glass railings',
    'status' => 'draft',
    'image' => '/images/shop-heroes/railings-hero.png',
    'hero_image_alt' => 'Frameless glass balcony railing with stainless top rail overlooking an urban terrace',
    'meta_title' => 'Glass Railings Planning Checklist | Vyomika Atelier',
    'meta_description' => 'A site-ready checklist for glass staircase and balcony railings — measurements, posts, glass type and installation sequencing for Indian homes.',
    'related_article_slugs' => 
    array (
      0 => 'stainless-steel-railings-types-finishes-selection-guide',
      1 => 'interior-vs-exterior-railings-material-finish',
      2 => 'how-to-choose-luxury-main-entrance-door',
    ),
    'excerpt' => 'Plan glass staircase and balcony railings with a practical checklist — site measurements, posts, glass type and installation sequencing for Indian homes.',
    'import_eligible' => true,
  ),
  13 => 
  array (
    'slug' => 'interior-vs-exterior-railings-material-finish',
    'title' => 'Interior vs Exterior Railings: Material and Finish Considerations',
    'category' => 'Railings',
    'cluster' => 'RAILINGS',
    'primary_keyword' => 'exterior stainless steel railing',
    'status' => 'draft',
    'image' => '/images/shop-heroes/railings-hero.png',
    'hero_image_alt' => 'Exterior stainless steel balcony railing with brushed finish in a coastal climate',
    'meta_title' => 'Interior vs Exterior Stainless Railings | Vyomika Atelier',
    'meta_description' => 'How interior and exterior railing environments differ for stainless grades, coatings and detailing in Indian climates.',
    'related_article_slugs' => 
    array (
      0 => 'stainless-steel-railings-types-finishes-selection-guide',
      1 => 'glass-railings-staircases-balconies-planning-checklist',
      2 => 'pvd-partitions-vs-powder-coated-metal-partitions',
    ),
    'excerpt' => 'Interior and exterior railings face different exposure in Indian climates — grades, coatings and detailing compared for a coherent specification on one project.',
    'import_eligible' => true,
  ),
  14 => 
  array (
    'slug' => 'what-is-corten-steel-and-how-does-it-weather',
    'title' => 'What Is Corten Steel and How Does It Weather?',
    'category' => 'Corten Steel',
    'cluster' => 'CORTEN STEEL',
    'primary_keyword' => 'what is Corten steel',
    'status' => 'draft',
    'image' => 'https://www.vyomikaatelier.com/assets/campaign-partitions.jpeg',
    'hero_image_alt' => 'Corten steel panel showing early and stabilised rust patina stages side by side',
    'meta_title' => 'What Is Corten Steel? Weathering Explained | Vyomika Atelier',
    'meta_description' => 'A clear explanation of Corten weathering steel, how the protective patina forms, and what Indian projects should expect during the first seasons outdoors.',
    'related_project_slugs' => 
    array (
      0 => 'corten-entrance-screen',
    ),
    'related_article_slugs' => 
    array (
      0 => 'corten-steel-modern-facades',
      1 => 'corten-steel-facades-design-drainage-weathering',
      2 => 'reduce-rust-run-off-staining-around-corten-steel',
    ),
    'excerpt' => 'Learn what Corten weathering steel is, how its protective patina forms, and what to expect during the first seasons on Indian architectural and landscape projects.',
    'import_eligible' => true,
  ),
  15 => 
  array (
    'slug' => 'corten-steel-facades-design-drainage-weathering',
    'title' => 'Corten Steel Façades: Design, Drainage and Weathering Considerations',
    'category' => 'Corten Steel',
    'cluster' => 'CORTEN STEEL',
    'primary_keyword' => 'Corten steel facade design',
    'status' => 'draft',
    'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
    'hero_image_alt' => 'Corten steel facade cladding with drip edge detail above a stone plinth',
    'meta_title' => 'Corten Steel Facade Design: Drainage & Weathering | Vyomika Atelier',
    'meta_description' => 'Design notes for Corten steel façades in India — drainage, staining risk, panel joints and coordination with adjacent materials.',
    'related_article_slugs' => 
    array (
      0 => 'what-is-corten-steel-and-how-does-it-weather',
      1 => 'corten-steel-modern-facades',
      2 => 'corten-steel-cladding-vs-conventional-painted-steel',
    ),
    'excerpt' => 'Design Corten steel facades for Indian sites with correct drainage, panel joints and runoff control — practical notes for architects, contractors and developers.',
    'import_eligible' => true,
  ),
  16 => 
  array (
    'slug' => 'corten-steel-cladding-vs-conventional-painted-steel',
    'title' => 'Corten Steel Cladding vs Conventional Painted Steel',
    'category' => 'Corten Steel',
    'cluster' => 'CORTEN STEEL',
    'primary_keyword' => 'Corten steel cladding',
    'status' => 'draft',
    'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/722414.jpeg',
    'hero_image_alt' => 'Corten steel cladding panel beside a painted steel section on a mock-up wall',
    'meta_title' => 'Corten Cladding vs Painted Steel | Vyomika Atelier',
    'meta_description' => 'Compare weathering steel cladding with conventional painted steel for appearance, upkeep and runoff detailing on architectural projects in India.',
    'related_article_slugs' => 
    array (
      0 => 'corten-steel-modern-facades',
      1 => 'corten-steel-facades-design-drainage-weathering',
      2 => 'pvd-partitions-vs-powder-coated-metal-partitions',
    ),
    'excerpt' => 'Corten cladding versus painted steel — compare appearance, upkeep, runoff behaviour and when each suits Indian architectural metalwork on facades and features.',
    'import_eligible' => true,
  ),
  17 => 
  array (
    'slug' => 'reduce-rust-run-off-staining-around-corten-steel',
    'title' => 'How to Reduce Rust Run-Off Staining Around Corten Steel',
    'category' => 'Corten Steel',
    'cluster' => 'CORTEN STEEL',
    'primary_keyword' => 'Corten steel rust staining',
    'status' => 'draft',
    'image' => 'https://www.vyomikaatelier.com/assets/campaign-partitions.jpeg',
    'hero_image_alt' => 'Corten steel screen with gravel drip strip preventing runoff staining on paving',
    'meta_title' => 'Reduce Corten Steel Rust Run-Off Staining | Vyomika Atelier',
    'meta_description' => 'Practical detailing ideas to manage early weathering runoff from Corten steel near stone, concrete and paving on Indian sites.',
    'related_article_slugs' => 
    array (
      0 => 'what-is-corten-steel-and-how-does-it-weather',
      1 => 'corten-steel-facades-design-drainage-weathering',
      2 => 'corten-steel-modern-facades',
    ),
    'excerpt' => 'Reduce early rust run-off staining near Corten steel with drip edges, landscape buffers and temporary protection on stone, paving and adjacent finishes.',
    'import_eligible' => true,
  ),
  18 => 
  array (
    'slug' => 'pvd-door-handles-finishes-sizes-selection-guide',
    'title' => 'PVD Door Handles: Finishes, Sizes and Selection Guide',
    'category' => 'Door Handles',
    'cluster' => 'PVD HARDWARE/FURNITURE',
    'primary_keyword' => 'PVD door handles',
    'status' => 'draft',
    'image' => '/images/shop-heroes/door-handles-hero.png',
    'hero_image_alt' => 'PVD-coated door pull handles in champagne and matte black on a sample board',
    'meta_title' => 'PVD Door Handles: Finishes, Sizes & Selection | Vyomika Atelier',
    'meta_description' => 'Choose PVD door handles for main doors and interiors — finish colours, pull lengths and coordination with entrance hardware.',
    'related_product_slugs' => 
    array (
      0 => 'pvd-door-pull-handle',
    ),
    'related_article_slugs' => 
    array (
      0 => 'select-pull-handle-length-for-main-door',
      1 => 'pvd-coating-explained',
      2 => 'pvd-finish-selection-guide-gold-rose-gold-champagne-black',
    ),
    'excerpt' => 'Select PVD door handles for main doors and interiors — finish colours, pull lengths, backplate options and daily maintenance in Indian homes and offices.',
    'import_eligible' => true,
  ),
  19 => 
  array (
    'slug' => 'select-pull-handle-length-for-main-door',
    'title' => 'How to Select Pull Handle Length for a Main Door',
    'category' => 'Door Handles',
    'cluster' => 'PVD HARDWARE/FURNITURE',
    'primary_keyword' => 'main door pull handle',
    'status' => 'draft',
    'image' => '/images/shop-heroes/door-handles-hero.png',
    'hero_image_alt' => 'Long PVD pull handle proportioned to a tall main entrance door',
    'meta_title' => 'Main Door Pull Handle Length Guide | Vyomika Atelier',
    'meta_description' => 'Guidance on pull handle length for main entrance doors — door height, visual balance and practical grip zones for Indian residences.',
    'related_product_slugs' => 
    array (
      0 => 'pvd-door-pull-handle',
      1 => 'brass-entrance-pull',
    ),
    'related_article_slugs' => 
    array (
      0 => 'pvd-door-handles-finishes-sizes-selection-guide',
      1 => 'how-to-choose-luxury-main-entrance-door',
      2 => 'pvd-finish-selection-guide-gold-rose-gold-champagne-black',
    ),
    'excerpt' => 'Match pull handle length to main door height and visual balance — ergonomic grip zones and proportion rules for Indian entrance doors and villa gates.',
    'import_eligible' => true,
  ),
  20 => 
  array (
    'slug' => 'pvd-furniture-care-finishes-customization',
    'title' => 'PVD Furniture: Care, Finishes and Customisation Options',
    'category' => 'Metal Furniture',
    'cluster' => 'PVD HARDWARE/FURNITURE',
    'primary_keyword' => 'PVD furniture',
    'status' => 'draft',
    'image' => '/images/shop-heroes/bespoke-metal-furniture-hero.png',
    'hero_image_alt' => 'PVD metal console table with champagne finish in a luxury foyer',
    'meta_title' => 'PVD Furniture Care, Finishes & Custom Options | Vyomika Atelier',
    'meta_description' => 'How to care for PVD metal furniture, choose finishes and brief custom console, corner and coffee tables for Indian interiors.',
    'related_product_slugs' => 
    array (
      0 => 'gold-fluted-console',
      1 => 'brushed-brass-coffee-table',
    ),
    'related_article_slugs' => 
    array (
      0 => 'choosing-metal-coffee-table-luxury-interior',
      1 => 'pvd-coating-explained',
      2 => 'pvd-finish-selection-guide-gold-rose-gold-champagne-black',
    ),
    'excerpt' => 'Care for PVD metal furniture and brief custom consoles and tables — finish handling, cleaning and customisation options from our Delhi manufacturing studio.',
    'import_eligible' => true,
  ),
  21 => 
  array (
    'slug' => 'choosing-metal-coffee-table-luxury-interior',
    'title' => 'Choosing a Metal Coffee Table for a Luxury Interior',
    'category' => 'Metal Furniture',
    'cluster' => 'PVD HARDWARE/FURNITURE',
    'primary_keyword' => 'luxury metal coffee table',
    'status' => 'draft',
    'image' => '/images/shop-heroes/coffee-tables-hero.png',
    'hero_image_alt' => 'PVD metal coffee table with glass top in a luxury living room seating arrangement',
    'meta_title' => 'Luxury Metal Coffee Table Selection Guide | Vyomika Atelier',
    'meta_description' => 'Proportion, finish and glass options for metal coffee tables in luxury living rooms — with customisation notes for Indian homes.',
    'related_product_slugs' => 
    array (
      0 => 'brushed-brass-coffee-table',
      1 => 'rose-gold-glass-side-table',
    ),
    'related_article_slugs' => 
    array (
      0 => 'pvd-furniture-care-finishes-customization',
      1 => 'how-to-select-metal-partition-for-living-room',
      2 => 'pvd-finish-selection-guide-gold-rose-gold-champagne-black',
    ),
    'excerpt' => 'Choose a metal coffee table for luxury living rooms — scale, PVD finish, glass tops and circulation clearances in Indian homes and hospitality lounges.',
    'import_eligible' => true,
  ),
  22 => 
  array (
    'slug' => 'pvd-finish-selection-guide-gold-rose-gold-champagne-black',
    'title' => 'PVD Finish Selection Guide: Gold, Rose Gold, Champagne and Black',
    'category' => 'PVD Finishes',
    'cluster' => 'PVD HARDWARE/FURNITURE',
    'primary_keyword' => 'PVD finish colours',
    'status' => 'draft',
    'image' => 'https://www.vyomikaatelier.com/assets/campaign-partitions.jpeg',
    'hero_image_alt' => 'PVD finish sample board showing gold, rose gold, champagne and matte black on stainless steel',
    'meta_title' => 'PVD Finish Colours: Gold, Rose Gold, Champagne & Black | Vyomika Atelier',
    'meta_description' => 'Compare popular PVD finish colours for partitions, doors and furniture — and how to keep finishes consistent across a project in India.',
    'related_service_slugs' => 
    array (
      0 => 'partitions',
    ),
    'related_product_slugs' => 
    array (
      0 => 'champagne-wave-partition',
      1 => 'matte-black-pvd-partition',
    ),
    'related_article_slugs' => 
    array (
      0 => 'pvd-coating-explained',
      1 => 'pvd-partitions-vs-powder-coated-metal-partitions',
      2 => 'pvd-door-handles-finishes-sizes-selection-guide',
    ),
    'excerpt' => 'Compare gold, rose gold, champagne and black PVD finishes for partitions, doors and furniture — keep colour consistent across one Indian interior project.',
    'import_eligible' => true,
  ),
  23 => 
  array (
    'slug' => 'architects-specify-custom-architectural-metalwork',
    'title' => 'How Architects Can Specify Custom Architectural Metalwork',
    'category' => 'Professional Resources',
    'cluster' => 'ARCHITECTURAL METALWORK',
    'primary_keyword' => 'architectural metalwork India',
    'status' => 'draft',
    'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
    'hero_image_alt' => 'Architect reviewing shop drawings for custom metal partition fabrication',
    'meta_title' => 'Specifying Custom Architectural Metalwork in India | Vyomika Atelier',
    'meta_description' => 'A practical specification workflow for architects briefing custom metal fabrication — drawings, finishes, tolerances and site coordination.',
    'related_article_slugs' => 
    array (
      0 => 'drawing-to-installation-custom-metal-fabrication-process',
      1 => 'pvd-partitions-materials-finishes-applications-cost-factors',
      2 => 'corten-steel-modern-facades',
    ),
    'excerpt' => 'A specification workflow for architects briefing custom metal fabrication in India — drawings, finishes, tolerances, site coordination and handover documentation.',
    'import_eligible' => true,
  ),
  24 => 
  array (
    'slug' => 'drawing-to-installation-custom-metal-fabrication-process',
    'title' => 'From Drawing to Installation: The Custom Metal Fabrication Process',
    'category' => 'Professional Resources',
    'cluster' => 'ARCHITECTURAL METALWORK',
    'primary_keyword' => 'custom metal fabrication process',
    'status' => 'draft',
    'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/722414.jpeg',
    'hero_image_alt' => 'Custom metal partition panels packaged for delivery from the Vyomika Atelier Delhi studio',
    'meta_title' => 'Custom Metal Fabrication Process | Vyomika Atelier',
    'meta_description' => 'Follow the typical path from drawings and measurements to fabrication, QC, delivery and installation for custom metalwork projects in India.',
    'related_article_slugs' => 
    array (
      0 => 'architects-specify-custom-architectural-metalwork',
      1 => 'pvd-partition-price-in-india-what-determines-final-cost',
      2 => 'glass-partitions-open-plan',
    ),
    'excerpt' => 'From approved drawings to site installation — how custom metal packages move through measurement, fabrication, QC and delivery from our Delhi studio.',
    'import_eligible' => true,
  ),
  25 => 
  array (
    'slug' => 'india-pvd-partition-prices-materials-size-installation',
    'title' => 'PVD Partition Prices in India: Materials, Size and Installation Factors',
    'category' => 'PVD Partitions',
    'cluster' => 'PVD PARTITIONS',
    'primary_keyword' => 'PVD partition price in India',
    'locale' => 'en-IN',
    'status' => 'draft',
    'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
    'hero_image_alt' => 'PVD Partition Prices in India: Materials, Size and Installation Factors — Vyomika Atelier',
    'meta_title' => 'PVD Partition Prices in India: Cost Factors | Vyomika Atelier',
    'meta_description' => 'What drives PVD partition pricing in India — materials, dimensions, glass, finishes and installation — without misleading list prices.',
    'related_service_slugs' => 
    array (
      0 => 'partitions',
    ),
    'related_article_slugs' => 
    array (
      0 => 'pvd-partition-price-in-india-what-determines-final-cost',
      1 => 'pvd-partitions-materials-finishes-applications-cost-factors',
    ),
    'excerpt' => 'Understand PVD partition pricing in India: materials, panel size, glass specification, PVD finish and installation factors that shape a fair project quotation.',
    'robots_index' => false,
    'import_eligible' => false,
  ),
  26 => 
  array (
    'slug' => 'india-glass-railing-price-quotation-factors',
    'title' => 'Glass Railing Price in India: What Affects a Project Quotation?',
    'category' => 'Railings',
    'cluster' => 'RAILINGS',
    'primary_keyword' => 'glass railing price in India',
    'locale' => 'en-IN',
    'status' => 'draft',
    'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
    'hero_image_alt' => 'Glass Railing Price in India: What Affects a Project Quotation? — Vyomika Atelier',
    'meta_title' => 'Glass Railing Price in India: Quotation Factors | Vyomika Atelier',
    'meta_description' => 'Learn what affects glass railing quotations in India — run length, glass type, posts, finishes and site conditions — without generic price tables.',
    'related_service_slugs' => NULL,
    'related_article_slugs' => 
    array (
      0 => 'glass-railings-staircases-balconies-planning-checklist',
      1 => 'stainless-steel-railings-types-finishes-selection-guide',
    ),
    'excerpt' => 'Glass railing prices in India depend on run length, glass type, post system, PVD finish and site access — factors to prepare before requesting a project quote.',
    'robots_index' => false,
    'import_eligible' => false,
  ),
  27 => 
  array (
    'slug' => 'india-choosing-metal-partitions-homes-apartments',
    'title' => 'Choosing Metal Partitions for Indian Homes and Apartments',
    'category' => 'Interior Metalwork',
    'cluster' => 'PVD PARTITIONS',
    'primary_keyword' => 'metal partition for living room',
    'locale' => 'en-IN',
    'status' => 'draft',
    'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
    'hero_image_alt' => 'Choosing Metal Partitions for Indian Homes and Apartments — Vyomika Atelier',
    'meta_title' => 'Metal Partitions for Indian Homes & Apartments | Vyomika Atelier',
    'meta_description' => 'Choose metal and PVD partitions for Indian apartments and villas — privacy, Vastu-friendly zoning, light and proportions.',
    'related_service_slugs' => 
    array (
      0 => 'partitions',
    ),
    'related_article_slugs' => 
    array (
      0 => 'how-to-select-metal-partition-for-living-room',
      1 => 'glass-partitions-open-plan',
    ),
    'excerpt' => 'Select metal partitions for Indian homes and apartments: open-plan zoning, PVD finishes, glass privacy and circulation clearances that suit local apartment layouts.',
    'robots_index' => false,
    'import_eligible' => false,
  ),
  28 => 
  array (
    'slug' => 'uk-metal-room-dividers-interiors-specification-guide',
    'title' => 'Metal Room Dividers for UK Interiors: Design and Specification Guide',
    'category' => 'PVD Partitions',
    'cluster' => 'PVD PARTITIONS',
    'primary_keyword' => 'metal room dividers UK',
    'locale' => 'en-GB',
    'status' => 'draft',
    'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
    'hero_image_alt' => 'Metal Room Dividers for UK Interiors: Design and Specification Guide — Vyomika Atelier',
    'meta_title' => 'Metal Room Dividers for UK Interiors | Vyomika Atelier',
    'meta_description' => 'Specify metal and glass room dividers for UK homes and offices — slimline profiles, PVD finishes and export enquiry guidance from an India-based fabricator.',
    'related_service_slugs' => 
    array (
      0 => 'partitions',
    ),
    'related_article_slugs' => 
    array (
      0 => 'glass-partitions-open-plan',
      1 => 'pvd-coating-explained',
    ),
    'excerpt' => 'Design and specify metal room dividers for UK interiors — glazed partition walls, PVD finishes and coordination notes for India-manufactured bespoke metalwork.',
    'robots_index' => false,
    'import_eligible' => false,
  ),
  29 => 
  array (
    'slug' => 'uk-slimline-internal-glass-doors-hinged-sliding-fixed',
    'title' => 'Slimline Internal Glass Doors: Hinged, Sliding or Fixed?',
    'category' => 'Slim Profile Doors',
    'cluster' => 'SLIM PROFILE/ENTRANCE DOORS',
    'primary_keyword' => 'slimline internal glass doors',
    'locale' => 'en-GB',
    'status' => 'draft',
    'image' => '/images/shop-heroes/slim-profile-doors-hero.png',
    'hero_image_alt' => 'Slimline Internal Glass Doors: Hinged, Sliding or Fixed? — Vyomika Atelier',
    'meta_title' => 'Slimline Internal Glass Doors Compared | Vyomika Atelier',
    'meta_description' => 'Compare slimline internal glass doors for UK projects — hinged, sliding and fixed panels — with PVD metal frames manufactured in India.',
    'related_service_slugs' => 
    array (
      0 => 'slim-profile-door-system',
    ),
    'related_article_slugs' => 
    array (
      0 => 'slim-profile-doors-hinged-sliding-telescopic-compared',
      1 => 'fluted-glass-slim-profile-doors-design-privacy-guide',
    ),
    'excerpt' => 'Compare slimline internal glass doors for UK homes — hinged, sliding and fixed glazed panels with minimal PVD metal frames from an India-based fabricator.',
    'robots_index' => false,
    'import_eligible' => false,
  ),
  30 => 
  array (
    'slug' => 'uk-corten-steel-cladding-weathering-drainage-detailing',
    'title' => 'Corten Steel Cladding in the UK: Weathering, Drainage and Detailing',
    'category' => 'Corten Steel',
    'cluster' => 'CORTEN STEEL',
    'primary_keyword' => 'Corten steel cladding UK',
    'locale' => 'en-GB',
    'status' => 'draft',
    'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
    'hero_image_alt' => 'Corten Steel Cladding in the UK: Weathering, Drainage and Detailing — Vyomika Atelier',
    'meta_title' => 'Corten Steel Cladding UK: Detailing Guide | Vyomika Atelier',
    'meta_description' => 'Corten steel cladding notes for UK projects — weathering behaviour, runoff control and detailing for India-manufactured panels.',
    'related_service_slugs' => 
    array (
      0 => 'corten-steel',
    ),
    'related_article_slugs' => 
    array (
      0 => 'what-is-corten-steel-and-how-does-it-weather',
      1 => 'corten-steel-facades-design-drainage-weathering',
    ),
    'excerpt' => 'Corten steel cladding for UK projects: patina development, drainage detailing and runoff control — notes from an India-based fabricator, subject to export review.',
    'robots_index' => false,
    'import_eligible' => false,
  ),
  31 => 
  array (
    'slug' => 'uae-pvd-stainless-steel-interiors-finishes-applications',
    'title' => 'PVD Stainless Steel for UAE Interiors: Finishes and Applications',
    'category' => 'PVD Finishes',
    'cluster' => 'PVD HARDWARE/FURNITURE',
    'primary_keyword' => 'PVD stainless steel UAE',
    'locale' => 'en-AE',
    'status' => 'draft',
    'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
    'hero_image_alt' => 'PVD Stainless Steel for UAE Interiors: Finishes and Applications — Vyomika Atelier',
    'meta_title' => 'PVD Stainless Steel for UAE Interiors | Vyomika Atelier',
    'meta_description' => 'PVD stainless steel finishes for UAE villas and offices — champagne, black and rose gold applications; India-manufactured architectural metalwork.',
    'related_service_slugs' => 
    array (
      0 => 'partitions',
      1 => 'main-entrance-pvd-doors',
    ),
    'related_article_slugs' => 
    array (
      0 => 'pvd-coating-explained',
      1 => 'pvd-finish-selection-guide-gold-rose-gold-champagne-black',
    ),
    'excerpt' => 'PVD stainless steel for UAE interiors: finish options, high-touch applications and specification notes for India-manufactured metalwork, subject to project review.',
    'robots_index' => false,
    'import_eligible' => false,
  ),
  32 => 
  array (
    'slug' => 'uae-glass-metal-partitions-dubai-offices-villas',
    'title' => 'Glass and Metal Partitions for Dubai Offices and Villas',
    'category' => 'PVD Partitions',
    'cluster' => 'PVD PARTITIONS',
    'primary_keyword' => 'office glass partitions Dubai',
    'locale' => 'en-AE',
    'status' => 'draft',
    'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
    'hero_image_alt' => 'Glass and Metal Partitions for Dubai Offices and Villas — Vyomika Atelier',
    'meta_title' => 'Glass & Metal Partitions for Dubai | Vyomika Atelier',
    'meta_description' => 'Plan glass and PVD metal partitions for Dubai offices and villas — zoning, finishes and export supply from India.',
    'related_service_slugs' => 
    array (
      0 => 'partitions',
    ),
    'related_article_slugs' => 
    array (
      0 => 'glass-partitions-open-plan',
      1 => 'uae-pvd-stainless-steel-interiors-finishes-applications',
    ),
    'excerpt' => 'Glass and metal partitions for Dubai offices and villas — open-plan zoning, PVD frames and export supply considerations from an India-based manufacturer.',
    'robots_index' => false,
    'import_eligible' => false,
  ),
  33 => 
  array (
    'slug' => 'uae-corten-steel-heat-humidity-coastal-considerations',
    'title' => 'Corten Steel in UAE Conditions: Heat, Humidity and Coastal Considerations',
    'category' => 'Corten Steel',
    'cluster' => 'CORTEN STEEL',
    'primary_keyword' => 'Corten steel cladding UAE',
    'locale' => 'en-AE',
    'status' => 'draft',
    'image' => 'https://www.delhiduniya.com/vyomika/images/shop/product/big/372645.jpeg',
    'hero_image_alt' => 'Corten Steel in UAE Conditions: Heat, Humidity and Coastal Considerations — Vyomika Atelier',
    'meta_title' => 'Corten Steel in UAE Conditions | Vyomika Atelier',
    'meta_description' => 'Corten steel in UAE heat, humidity and coastal air — patina behaviour, detailing and export supply notes.',
    'related_service_slugs' => 
    array (
      0 => 'corten-steel',
    ),
    'related_article_slugs' => 
    array (
      0 => 'what-is-corten-steel-and-how-does-it-weather',
      1 => 'corten-steel-facades-design-drainage-weathering',
    ),
    'excerpt' => 'Specify Corten steel for UAE projects with realistic expectations for heat, humidity, coastal salt and patina — subject to project review and export terms.',
    'robots_index' => false,
    'import_eligible' => false,
  ),
);
