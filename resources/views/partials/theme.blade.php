{{-- Admin theme (Appearance settings), compiled to a content-hashed static
     CSS file — no inline <style> in the head. See theme_css_href(). --}}
@if($themeCss = theme_css_href())
    <link rel="stylesheet" href="{{ $themeCss }}">
@endif
