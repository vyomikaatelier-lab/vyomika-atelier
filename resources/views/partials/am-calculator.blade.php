@props([
    'rate' => 1800,
    'serviceSlug' => 'partitions',
    'designSlug' => '',
    'serviceName' => 'PVD Partitions',
    'calcTitle' => 'Estimate your partition',
])

<div class="am-calculator va-calculator" data-rate="{{ $rate }}">
    <p class="am-calculator__label">Sq Ft Calculator</p>
    <h3 class="am-calculator__title">{{ $calcTitle }}</h3>
    <p class="am-calculator__hint va-rate-hint">Length × Height × ₹{{ number_format($rate, 0) }}/sq ft</p>

    <div class="am-calculator__units">
        <button type="button" class="am-calculator__unit va-unit-btn active" data-unit="ft-in">Ft-In</button>
        <button type="button" class="am-calculator__unit va-unit-btn" data-unit="mm">MM</button>
        <button type="button" class="am-calculator__unit va-unit-btn" data-unit="cm">CM</button>
    </div>

    <div class="am-calculator__dims">
        <div class="am-calculator__field">
            <label>Length</label>
            <div class="va-dim-ft-in va-dim-group">
                <div class="am-calculator__dim-row">
                    <input type="number" min="0" class="am-input va-len-ft" placeholder="Ft" value="10">
                    <input type="number" min="0" max="11" class="am-input va-len-in" placeholder="In" value="0">
                </div>
            </div>
            <div class="va-dim-mm va-dim-group hidden">
                <input type="number" min="0" class="am-input va-len-mm" placeholder="mm" value="3048">
            </div>
            <div class="va-dim-cm va-dim-group hidden">
                <input type="number" min="0" class="am-input va-len-cm" placeholder="cm" value="304.8">
            </div>
        </div>
        <div class="am-calculator__field">
            <label>Height</label>
            <div class="va-dim-ft-in va-dim-group">
                <div class="am-calculator__dim-row">
                    <input type="number" min="0" class="am-input va-hgt-ft" placeholder="Ft" value="8">
                    <input type="number" min="0" max="11" class="am-input va-hgt-in" placeholder="In" value="0">
                </div>
            </div>
            <div class="va-dim-mm va-dim-group hidden">
                <input type="number" min="0" class="am-input va-hgt-mm" placeholder="mm" value="2438">
            </div>
            <div class="va-dim-cm va-dim-group hidden">
                <input type="number" min="0" class="am-input va-hgt-cm" placeholder="cm" value="243.8">
            </div>
        </div>
    </div>

    <div class="am-calculator__result">
        <div class="am-calculator__result-row">
            <span>Area</span>
            <span class="va-area-display">80.00 sq ft</span>
        </div>
        <div class="am-calculator__result-row am-calculator__result-row--price">
            <span>Estimated</span>
            <span class="va-price-display">₹1,44,000</span>
        </div>
    </div>

    <button type="button"
        class="am-btn am-btn--primary am-btn--full va-order-btn"
        data-service-slug="{{ $serviceSlug }}"
        data-design-slug="{{ $designSlug }}"
        data-service-name="{{ $serviceName }}">
        Order Now
    </button>
</div>
