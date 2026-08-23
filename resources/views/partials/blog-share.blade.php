@php
    $encodedUrl = urlencode($shareUrl);
    $encodedTitle = urlencode($shareTitle);
@endphp
<div class="am-blog-share" aria-label="Share this article">
    <span class="am-blog-share__label">Share</span>
    @if($whatsappShare ?? null)
    <a href="{{ $whatsappShare }}" class="am-blog-share__btn" target="_blank" rel="noopener noreferrer">WhatsApp</a>
    @endif
    <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ $encodedUrl }}" class="am-blog-share__btn" target="_blank" rel="noopener noreferrer">LinkedIn</a>
    <a href="https://www.facebook.com/sharer/sharer.php?u={{ $encodedUrl }}" class="am-blog-share__btn" target="_blank" rel="noopener noreferrer">Facebook</a>
    <button type="button" class="am-blog-share__btn am-blog-share__btn--copy" data-copy-link="{{ $shareUrl }}">Copy link</button>
</div>
<script>
(function () {
    var btn = document.querySelector('[data-copy-link="{{ $shareUrl }}"]');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var url = btn.getAttribute('data-copy-link');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function () {
                btn.textContent = 'Copied!';
                setTimeout(function () { btn.textContent = 'Copy link'; }, 2000);
            });
        }
    });
})();
</script>
