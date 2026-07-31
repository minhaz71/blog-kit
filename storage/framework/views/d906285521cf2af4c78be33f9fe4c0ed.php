<?php
    $slugs = collect(array_keys((array) $section->setting('category_slugs', [])))->filter()->values();
    $overrides = (array) $section->setting('category_slugs', []);
    if ($slugs->isNotEmpty()) {
        // Admin-curated: exactly these categories, in this order.
        $categories = \App\Models\Category::active()->whereIn('slug', $slugs)->get();
        $categories = $categories->sortBy(fn ($c) => $slugs->search($c->slug))->values();
    } else {
        // No curation → show ALL active categories, newest first (capped so a
        // large catalogue doesn't produce an endless homepage; raise "limit").
        $limit = (int) $section->setting('limit', 12);
        $categories = \App\Models\Category::active()
            ->orderByDesc('id')
            ->when($limit > 0, fn ($q) => $q->take($limit))
            ->get();
    }

    // Real-image fallback: a category with no collage yet still shows a genuine
    // product photo (never an empty box / animated placeholder).
    $imageFor = function (\App\Models\Category $category): ?string {
        if ($category->image && ! str_ends_with(strtolower($category->image), '.svg')) {
            return $category->imageUrl();
        }
        $product = \App\Models\Product::query()
            ->whereHas('categories', fn ($q) => $q->whereKey($category->id))
            ->where('status', 'published')
            ->whereNotNull('featured_image')
            ->first(['id', 'featured_image']);

        return $product?->featuredImageUrl();
    };
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($categories->isNotEmpty()): ?>
    <section class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm" aria-label="<?php echo e($section->title ?? 'Shop by category'); ?>">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
            <div class="min-w-0">
                <h2 class="flex items-center gap-2 truncate text-base font-bold text-gray-900 sm:text-lg">
                    <span class="h-5 w-1.5 shrink-0 rounded-full bg-teal-600"></span><?php echo e($section->title ?? 'Shop by category'); ?>

                </h2>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($section->subtitle): ?><p class="mt-0.5 truncate text-xs text-gray-500"><?php echo e($section->subtitle); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
        <div class="grid grid-cols-3 gap-3 p-4 sm:grid-cols-4 lg:grid-cols-6">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <a href="<?php echo e($category->url()); ?>" class="group">
                    <div class="aspect-square overflow-hidden rounded-lg border border-gray-100 bg-gray-50">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($src = $imageFor($category)): ?>
                            <img src="<?php echo e($src); ?>" alt="<?php echo e($category->image_alt ?: $category->name); ?>" loading="lazy" width="400" height="400" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                        <?php else: ?>
                            <div class="flex h-full w-full items-center justify-center text-2xl font-bold text-gray-300"><?php echo e(mb_substr($category->name, 0, 1)); ?></div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <p class="mt-1.5 truncate text-center text-xs font-semibold text-gray-800 group-hover:text-teal-700 sm:text-sm">
                        <?php echo e($overrides[$category->slug] ?? $category->name); ?>

                    </p>
                </a>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    </section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/partials/homepage/category_grid.blade.php ENDPATH**/ ?>