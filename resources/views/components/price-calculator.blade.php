@props([
    'serviceSlug' => '',
    'designSlug' => '',
    'rate' => 1800,
    'serviceName' => '',
])

<div class="va-calculator bg-white border border-brand-200 p-6 md:p-8" data-rate="{{ $rate }}">
    <p class="va-label mb-2">Price Calculator</p>
    <h3 class="font-serif text-2xl text-brand-900 mb-6">Estimate your project</h3>
    <p class="text-sm text-brand-500 mb-6">Length × Height = sq ft × ₹{{ number_format($rate, 0) }} base rate</p>

    <div class="mb-6">
        <p class="text-[10px] uppercase tracking-[0.2em] text-brand-400 mb-3">Unit</p>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="va-unit-btn active" data-unit="ft-in">Feet-Inches</button>
            <button type="button" class="va-unit-btn" data-unit="mm">MM</button>
            <button type="button" class="va-unit-btn" data-unit="cm">CM</button>
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-4 mb-6">
        <div>
            <label class="text-[10px] uppercase tracking-[0.2em] text-brand-400 block mb-2">Length</label>
            <div class="va-dim-ft-in va-dim-group">
                <div class="flex gap-2">
                    <input type="number" min="0" class="va-input va-len-ft" placeholder="Ft" value="10">
                    <input type="number" min="0" max="11" class="va-input va-len-in" placeholder="In" value="0">
                </div>
            </div>
            <div class="va-dim-mm va-dim-group hidden">
                <input type="number" min="0" class="va-input va-len-mm" placeholder="Length (mm)" value="3048">
            </div>
            <div class="va-dim-cm va-dim-group hidden">
                <input type="number" min="0" class="va-input va-len-cm" placeholder="Length (cm)" value="304.8">
            </div>
        </div>
        <div>
            <label class="text-[10px] uppercase tracking-[0.2em] text-brand-400 block mb-2">Height</label>
            <div class="va-dim-ft-in va-dim-group">
                <div class="flex gap-2">
                    <input type="number" min="0" class="va-input va-hgt-ft" placeholder="Ft" value="8">
                    <input type="number" min="0" max="11" class="va-input va-hgt-in" placeholder="In" value="0">
                </div>
            </div>
            <div class="va-dim-mm va-dim-group hidden">
                <input type="number" min="0" class="va-input va-hgt-mm" placeholder="Height (mm)" value="2438">
            </div>
            <div class="va-dim-cm va-dim-group hidden">
                <input type="number" min="0" class="va-input va-hgt-cm" placeholder="Height (cm)" value="243.8">
            </div>
        </div>
    </div>

    <div class="bg-brand-50 border border-brand-200 p-5 mb-6">
        <div class="flex justify-between text-sm text-brand-500 mb-1">
            <span>Area</span>
            <span class="va-area-display">80.00 sq ft</span>
        </div>
        <div class="flex justify-between items-baseline">
            <span class="font-serif text-lg text-brand-900">Estimated Price</span>
            <span class="font-serif text-2xl text-brand-900 va-price-display">₹1,44,000</span>
        </div>
    </div>

    <button type="button"
        class="va-btn-primary w-full text-center va-order-btn"
        data-service-slug="{{ $serviceSlug }}"
        data-design-slug="{{ $designSlug }}"
        data-service-name="{{ $serviceName }}">
        Order Now
    </button>
</div>
