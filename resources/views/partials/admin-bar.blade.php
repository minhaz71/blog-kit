@php
    /**
     * Staff-only admin bar. Detects what page is being viewed from the
     * current route and offers direct edit links for it (product +
     * template, category, post, page, homepage sections). Customers and
     * guests never see this — and logged-in responses are excluded from
     * the public page cache, so it can never leak into cached HTML.
     * Styles: resources/css/admin-bar.css (external, no JS needed).
     */
    $route = request()->route();
    $routeName = (string) $route?->getName();
    $links = [];

    if ($routeName === 'product.show' && ($product = $route->parameter('product')) instanceof \App\Models\Product) {
        $links[] = ['Edit product', \App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $product])];

        $template = $product->resolvedTemplate();
        if ($template->exists) {
            $links[] = ['Edit template', \App\Filament\Resources\ProductTemplateResource::getUrl('edit', ['record' => $template])];
        }
    } elseif ($routeName === 'category.show' && ($category = $route->parameter('category')) instanceof \App\Models\Category) {
        $links[] = ['Edit category', \App\Filament\Resources\CategoryResource::getUrl('edit', ['record' => $category])];
    } elseif ($routeName === 'blog.show' && ($post = $route->parameter('post')) instanceof \App\Models\Post) {
        $links[] = ['Edit post', \App\Filament\Resources\PostResource::getUrl('edit', ['record' => $post])];
    } elseif ($routeName === 'blog.category' && ($postCategory = $route->parameter('postCategory')) instanceof \App\Models\PostCategory) {
        $links[] = ['Edit blog category', \App\Filament\Resources\PostCategoryResource::getUrl('edit', ['record' => $postCategory])];
    } elseif ($routeName === 'page.show' && ($slug = (string) $route->parameter('slug')) !== '') {
        // The root catch-all can serve a page OR (when its base is cleared) a
        // root-level product/category — resolve exactly as PermalinkController.
        if (\App\Support\Permalinks::base('product') === '' && $product = \App\Models\Product::where('slug', $slug)->first()) {
            $links[] = ['Edit product', \App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $product])];

            $template = $product->resolvedTemplate();
            if ($template->exists) {
                $links[] = ['Edit template', \App\Filament\Resources\ProductTemplateResource::getUrl('edit', ['record' => $template])];
            }
        } elseif (\App\Support\Permalinks::base('category') === '' && $category = \App\Models\Category::where('slug', $slug)->first()) {
            $links[] = ['Edit category', \App\Filament\Resources\CategoryResource::getUrl('edit', ['record' => $category])];
        } elseif ($page = \App\Models\Page::where('slug', $slug)->first()) {
            $links[] = ['Edit page', \App\Filament\Resources\PageResource::getUrl('edit', ['record' => $page])];
        }
    } elseif ($routeName === 'home') {
        $links[] = ['Edit homepage sections', \App\Filament\Resources\HomepageSectionResource::getUrl('index')];
    } elseif (str_starts_with($routeName, 'blog.')) {
        $links[] = ['Manage posts', \App\Filament\Resources\PostResource::getUrl('index')];
    }
@endphp
<div class="adminbar" role="navigation" aria-label="Admin toolbar">
    <a href="{{ url('/admin') }}" class="adminbar-brand">
        <span class="adminbar-dot"></span> Admin
    </a>
    <span class="adminbar-sep"></span>

    @if($links !== [])
        <span class="adminbar-context">This page:</span>
        @foreach($links as [$label, $url])
            <a href="{{ $url }}" class="adminbar-link primary">✎ {{ $label }}</a>
        @endforeach
        <span class="adminbar-sep"></span>
    @endif

    <a href="{{ \App\Filament\Pages\LinkAgent::getUrl() }}" class="adminbar-link">Link agent</a>
    <a href="{{ \App\Filament\Pages\SeoEditor::getUrl() }}" class="adminbar-link">SEO editor</a>
    @if(ecommerce_enabled())
        <a href="{{ \App\Filament\Resources\OrderResource::getUrl('index') }}" class="adminbar-link">Orders</a>
    @endif

    <span class="adminbar-spacer"></span>
    <span class="adminbar-user">{{ auth()->user()->name }}</span>
</div>
