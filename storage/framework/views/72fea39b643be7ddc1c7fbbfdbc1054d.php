<?php
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
?>
<div class="adminbar" role="navigation" aria-label="Admin toolbar">
    <a href="<?php echo e(url('/admin')); ?>" class="adminbar-brand">
        <span class="adminbar-dot"></span> Admin
    </a>
    <span class="adminbar-sep"></span>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($links !== []): ?>
        <span class="adminbar-context">This page:</span>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $links; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $url]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <a href="<?php echo e($url); ?>" class="adminbar-link primary">✎ <?php echo e($label); ?></a>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <span class="adminbar-sep"></span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <a href="<?php echo e(\App\Filament\Pages\LinkAgent::getUrl()); ?>" class="adminbar-link">Link agent</a>
    <a href="<?php echo e(\App\Filament\Pages\SeoEditor::getUrl()); ?>" class="adminbar-link">SEO editor</a>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(ecommerce_enabled()): ?>
        <a href="<?php echo e(\App\Filament\Resources\OrderResource::getUrl('index')); ?>" class="adminbar-link">Orders</a>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <span class="adminbar-spacer"></span>
    <span class="adminbar-user"><?php echo e(auth()->user()->name); ?></span>
</div>
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/partials/admin-bar.blade.php ENDPATH**/ ?>