@if(is_array(session('enquiry_receipt')))
    @php
        $receipt = session('enquiry_receipt');
        $lines = is_array($receipt['lines'] ?? null) ? $receipt['lines'] : [];
    @endphp
    <div class="am-card am-checkout-panel" role="status">
        <div class="am-card__body">
            <h2 class="am-checkout-panel__title">Shipping enquiry</h2>
            <p>{{ \App\Support\IndiaDelivery::SHIPPING_QUOTED }}. {{ \App\Support\IndiaDelivery::MERCHANDISE_SUBTOTAL }}: ₹{{ number_format((float) ($receipt['subtotal'] ?? 0), 0) }}</p>
            @if(filled($receipt['destination'] ?? null))
                <p>Destination: {{ $receipt['destination'] }}</p>
            @endif
            @if($lines !== [])
                <ul>
                    @foreach($lines as $line)
                        <li>{{ $line['name'] ?? 'Item' }} × {{ (int) ($line['quantity'] ?? 0) }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endif
