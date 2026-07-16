<section {{ $attributes->merge(['class' => 'am-page-body am-page-body--account-auth']) }}>
    <div class="am-container am-account-auth-layout">
        <div class="am-account-auth-card-wrap">
            {{ $slot }}
        </div>
    </div>
</section>
