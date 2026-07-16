@if(session('success'))
<p class="am-account-notice am-account-notice--success" role="status">{{ session('success') }}</p>
@endif
@if(session('status'))
<p class="am-account-notice am-account-notice--info" role="status">{{ session('status') }}</p>
@endif
@if(session('info'))
<p class="am-account-notice am-account-notice--info" role="status">{{ session('info') }}</p>
@endif
@if($errors->any())
<div class="am-account-notice am-account-notice--error" role="alert">
    <p>{{ $errors->first() }}</p>
</div>
@endif
