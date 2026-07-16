@props([
    'title',
    'descriptionHtml' => '',
    'careItems' => [],
    'careHeading' => 'Composition, Material & Care Guidelines',
    'related' => null,
    'product' => null,
])

<section class="am-pdp-tabs-wrap">
    <div class="am-pdp-tabs" data-am-tabs>
        <div class="am-pdp-tabs__nav" role="tablist">
            <button type="button" class="am-pdp-tabs__tab is-active" data-am-tab="description" role="tab" aria-selected="true">Description</button>
            <button type="button" class="am-pdp-tabs__tab" data-am-tab="specifications" role="tab" aria-selected="false">Specifications</button>
            <button type="button" class="am-pdp-tabs__tab" data-am-tab="packaging" role="tab" aria-selected="false">Packaging</button>
            <button type="button" class="am-pdp-tabs__tab" data-am-tab="shipping" role="tab" aria-selected="false">Shipping</button>
        </div>

        <div class="am-pdp-tabs__panel is-active" data-am-panel="description" role="tabpanel">
            <div class="am-pdp-tabs__desc-grid">
                <div class="am-pdp-tabs__desc-main">
                    <h2 class="am-pdp-tabs__desc-title">{{ $title }}</h2>
                    <div class="am-prose am-pdp-tabs__prose">
                        {!! $descriptionHtml ?: '<p>Precision PVD metal fabrication engineered for modern Indian interiors.</p>' !!}
                    </div>
                </div>
                @if(count($careItems))
                <div class="am-pdp-tabs__desc-aside">
                    <h3 class="am-pdp-tabs__care-title">{{ $careHeading }}</h3>
                    <ul class="am-pdp-tabs__care-list">
                        @foreach($careItems as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>
        </div>

        <div class="am-pdp-tabs__panel" data-am-panel="specifications" role="tabpanel" hidden>
            <div class="am-prose am-pdp-tabs__prose">
                <h3>Product Specifications</h3>
                <dl class="am-pdp-spec-table">
                    @if($product)
                        @if($product->category)<div><dt>Category</dt><dd>{{ $product->category->name }}</dd></div>@endif
                        @if($product->sku)<div><dt>SKU</dt><dd>{{ $product->sku }}</dd></div>@endif
                        <div><dt>Material</dt><dd>Grade 304/316 stainless steel with PVD coating</dd></div>
                        <div><dt>Finish options</dt><dd>Gold Mirror, Gold Brush, Rose Gold Mirror, Rose Gold Brush, Champagne Mirror, Champagne Brush, Black Mirror (+30%), Black Brush (+30%)</dd></div>
                        <div><dt>Price</dt><dd>{{ $product->formattedPrice() }}</dd></div>
                        <div><dt>Availability</dt><dd>{{ $product->inStock() ? 'In stock' : 'Made to order' }}</dd></div>
                    @else
                        <div><dt>Material</dt><dd>Grade 304/316 stainless steel with PVD coating</dd></div>
                        <div><dt>Finish options</dt><dd>8 PVD finishes available</dd></div>
                        <div><dt>Fabrication</dt><dd>Custom dimensions from Mumbai studio</dd></div>
                    @endif
                    <div><dt>Delivery</dt><dd>3–4 weeks — Pan-India from Mumbai studio</dd></div>
                </dl>
            </div>
        </div>

        <div class="am-pdp-tabs__panel" data-am-panel="packaging" role="tabpanel" hidden>
            <div class="am-prose am-pdp-tabs__prose">
                <h3>Packaging &amp; Handling</h3>
                <p>Every Vyomika Atelier LLP piece is wrapped in protective foam and corner guards, then crated in plywood for transit. PVD surfaces are film-wrapped to prevent scratches during Pan-India shipping.</p>
                <ul class="am-pdp-tabs__care-list">
                    <li>Individual partition panels — vertical crate with foam spacers</li>
                    <li>Door systems — reinforced frame crate with glass protection</li>
                    <li>Furniture &amp; racks — flat-pack or assembled crate per product</li>
                    <li>Hardware kits — sealed boxes with installation guide</li>
                </ul>
                <p>Unpack within 48 hours of delivery and inspect for transit damage. Report issues with photos for prompt resolution.</p>
            </div>
        </div>

        <div class="am-pdp-tabs__panel" data-am-panel="shipping" role="tabpanel" hidden>
            <div class="am-prose am-pdp-tabs__prose">
                <h3>Shipping</h3>
                <p>Fabrication from our Mumbai studio with secure packaging and delivery to major cities across India.</p>
                <ul class="am-pdp-tabs__care-list">
                    <li><strong>Lead time:</strong> 3–4 weeks from order confirmation</li>
                    <li><strong>Metro cities:</strong> Door delivery with installation support on request</li>
                    <li><strong>Other locations:</strong> Pan-India courier or freight partner</li>
                    <li><strong>Made to order:</strong> All items are custom fabricated — no returns on bespoke metalwork</li>
                </ul>
                <p><a href="{{ route('legal.shipping') }}">Full shipping policy →</a></p>
            </div>
        </div>
    </div>

    @if($related && $related->isNotEmpty())
    <div class="am-pdp-related-block">
        <div class="am-pdp-tabs__nav am-pdp-tabs__nav--sub">
            <span class="am-pdp-tabs__tab is-active">Related Products</span>
        </div>
        <div class="am-product-grid am-product-grid--4">
            @foreach($related as $item)
                @include('partials.am-product-card', ['product' => $item])
            @endforeach
        </div>
    </div>
    @endif
</section>
