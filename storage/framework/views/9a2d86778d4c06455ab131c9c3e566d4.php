<?php
    /**
     * Standalone category catalogue for content pages (city delivery pages).
     * Same .catalogue-* styling as the homepage section, but self-contained
     * (no HomepageSection needed) so any page can route visitors to the
     * category pages. Cached list, active categories only.
     */
    $cityCategories = \Illuminate\Support\Facades\Cache::remember(
        'city-catalogue.v'.((int) \Illuminate\Support\Facades\Cache::get('pagecache.version', 1)),
        now()->addDay(),
        fn () => \App\Models\Category::active()
            ->withCount(['products' => fn ($q) => $q->where('status', 'published')->where('visibility', 'visible')])
            ->orderByDesc('products_count')
            ->take(8)
            ->get()
            ->map(fn ($c) => [
                'name' => $c->name,
                'url' => $c->url(),
                'image' => $c->imageUrl(),
                'alt' => $c->image_alt ?: $c->name,
                'count' => $c->products_count,
                'initial' => mb_substr($c->name, 0, 1),
            ])
            ->all()
    );
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($cityCategories)): ?>
    <section class="catalogue<?php echo e(($flush ?? false) ? ' catalogue--flush' : ''); ?>" aria-label="Shop TEREA by category">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($hideHead ?? false)): ?>
            <div class="catalogue-head">
                <h2 class="catalogue-title">Shop TEREA by Category</h2>
                <p class="catalogue-subtitle">Browse every genuine IQOS TEREA range we deliver, then order in a couple of taps.</p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div class="catalogue-grid cols-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $cityCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <a href="<?php echo e($category['url']); ?>" class="catalogue-card">
                    <div class="catalogue-media">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($category['image']): ?>
                            <img src="<?php echo e($category['image']); ?>" alt="<?php echo e($category['alt']); ?>" title="<?php echo e($category['alt']); ?>"
                                 loading="lazy" width="400" height="400">
                        <?php else: ?>
                            <span class="catalogue-media-empty"><?php echo e($category['initial']); ?></span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="catalogue-meta">
                        <span class="catalogue-name"><?php echo e($category['name']); ?></span>
                        <span class="catalogue-count"><?php echo e($category['count']); ?></span>
                    </div>
                </a>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    </section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/partials/city-catalogue.blade.php ENDPATH**/ ?>