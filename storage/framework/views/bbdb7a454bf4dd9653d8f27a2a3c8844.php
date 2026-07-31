<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['product', 'eager' => false]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['product', 'eager' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>


<?php $product->loadMissing(['brand', 'images']); ?>
<article class="group relative flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white transition hover:shadow-md">
    <a href="<?php echo e($product->url()); ?>" class="aspect-square overflow-hidden bg-gray-100">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($image = $product->featuredImageWebpUrl()): ?>
            <img src="<?php echo e($image); ?>" alt="<?php echo e($product->featuredImageRecord()?->altText() ?: $product->name); ?>"
                 title="<?php echo e($product->featuredImageRecord()?->titleText() ?: $product->name); ?>"
                 <?php if($eager): ?> loading="eager" fetchpriority="high" <?php else: ?> loading="lazy" <?php endif; ?>
                 width="400" height="400"
                 class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        <?php else: ?>
            <div class="flex h-full w-full items-center justify-center text-gray-300">
                <svg class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </a>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->isOnSale() && $product->discountPercent()): ?>
        <span class="absolute left-2 top-2 rounded bg-red-600 px-2 py-0.5 text-xs font-bold text-white">-<?php echo e($product->discountPercent()); ?>%</span>
    <?php elseif($product->is_new_arrival): ?>
        <span class="absolute left-2 top-2 rounded bg-indigo-600 px-2 py-0.5 text-xs font-bold text-white">New</span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="flex flex-1 flex-col p-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->brand): ?>
            <p class="text-xs uppercase tracking-wide text-gray-400"><?php echo e($product->brand->name); ?></p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <h3 class="mt-1 text-sm font-medium leading-snug">
            <a href="<?php echo e($product->url()); ?>" class="hover:text-indigo-600"><?php echo e($product->name); ?></a>
        </h3>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->reviews_count > 0): ?>
            <?php if (isset($component)) { $__componentOriginal077a61d60611f096a94f8e1725d6bb16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal077a61d60611f096a94f8e1725d6bb16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.rating-stars','data' => ['rating' => $product->avg_rating,'count' => $product->reviews_count,'class' => 'mt-1']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('rating-stars'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['rating' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($product->avg_rating),'count' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($product->reviews_count),'class' => 'mt-1']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal077a61d60611f096a94f8e1725d6bb16)): ?>
<?php $attributes = $__attributesOriginal077a61d60611f096a94f8e1725d6bb16; ?>
<?php unset($__attributesOriginal077a61d60611f096a94f8e1725d6bb16); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal077a61d60611f096a94f8e1725d6bb16)): ?>
<?php $component = $__componentOriginal077a61d60611f096a94f8e1725d6bb16; ?>
<?php unset($__componentOriginal077a61d60611f096a94f8e1725d6bb16); ?>
<?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="mt-auto pt-2">
            <?php if (isset($component)) { $__componentOriginal5c7c50258000edf57abfef324d310474 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5c7c50258000edf57abfef324d310474 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.price','data' => ['product' => $product]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('price'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['product' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($product)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5c7c50258000edf57abfef324d310474)): ?>
<?php $attributes = $__attributesOriginal5c7c50258000edf57abfef324d310474; ?>
<?php unset($__attributesOriginal5c7c50258000edf57abfef324d310474); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5c7c50258000edf57abfef324d310474)): ?>
<?php $component = $__componentOriginal5c7c50258000edf57abfef324d310474; ?>
<?php unset($__componentOriginal5c7c50258000edf57abfef324d310474); ?>
<?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($product->isInStock())): ?>
                <p class="mt-1 text-xs font-medium text-red-600">Out of stock</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(setting('appearance.card_add_to_cart', true)): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->isInStock() && $product->type === 'simple'): ?>
                
                <div class="mt-2 space-y-1.5" x-data="{ qty: 1, busy: false, added: false }">
                    <div class="flex w-full items-center justify-between rounded border border-gray-300">
                        <button type="button"
                                class="flex h-9 w-10 shrink-0 items-center justify-center text-gray-600 hover:bg-gray-100 hover:text-indigo-600 active:bg-gray-200"
                                aria-label="Decrease quantity"
                                @click="qty = Math.max(1, qty - 1)">&minus;</button>
                        <input type="number" x-model.number="qty" min="1"
                               aria-label="Quantity"
                               class="h-9 w-full min-w-0 border-0 p-0 text-center text-sm [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none focus:ring-0">
                        <button type="button"
                                class="flex h-9 w-10 shrink-0 items-center justify-center text-gray-600 hover:bg-gray-100 hover:text-indigo-600 active:bg-gray-200"
                                aria-label="Increase quantity"
                                @click="qty++">+</button>
                    </div>
                    <button type="button"
                            class="h-10 w-full rounded bg-indigo-600 px-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-50"
                            :disabled="busy"
                            @click="busy = true;
                                    shopkit.addToCart(<?php echo e($product->id); ?>, Math.max(1, qty || 1))
                                        .then(() => { added = true; setTimeout(() => added = false, 1500) })
                                        .catch((e) => alert(e.message))
                                        .finally(() => busy = false)">
                        <span x-show="!added">Add to cart</span>
                        <span x-show="added" x-cloak>Added ✓</span>
                    </button>
                </div>
            <?php elseif($product->isInStock()): ?>
                <a href="<?php echo e($product->url()); ?>"
                   class="mt-2 flex h-9 items-center justify-center rounded border border-indigo-600 px-2 text-xs font-semibold text-indigo-600 transition hover:bg-indigo-600 hover:text-white">
                    Select options
                </a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</article>
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/components/product-card.blade.php ENDPATH**/ ?>