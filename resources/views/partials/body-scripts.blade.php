{{-- Immediately after <body>: Google Tag Manager noscript fallback (required
     placement) + any custom body-open code. --}}
@php
    $gtmId = trim((string) setting('seo.google_tag_manager_id'));
    $customBody = trim((string) setting('seo.custom_body_code'));
@endphp
@if($gtmId !== '')
    {{-- Google Tag Manager (noscript) --}}
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    {{-- End Google Tag Manager (noscript) --}}
@endif
@if($customBody !== '')
    {!! $customBody !!}
@endif
