<?php $__env->startSection('content'); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($isPreview)): ?>
    <div class="bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-white">
        Draft preview — this product is not visible to customers.
        <a href="<?php echo e(url('/admin/products/'.$product->id.'/edit')); ?>" class="underline">Back to editor</a>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php
    $template = $product->resolvedTemplate();
    $blocks = $template->resolvedBlocks();

    // Shared Alpine state for gallery ↔ variations ↔ price ↔ add-to-cart.
    $variationData = $product->activeVariations->map(fn ($v) => [
        'id' => $v->id,
        'options' => $v->optionMap(),
        'price' => price_format($v->currentPrice()),
        'regular' => $v->isOnSale() ? price_format($v->price) : null,
        'in_stock' => $v->isInStock(),
        'image' => $v->imageUrl(),
        'sku' => $v->sku,
    ])->values();
    $variationAttributes = $product->attributes->filter(fn ($a) => $a->pivot->is_variation);

    // Position the 2-column hero at the first left/right block; full-width
    // blocks before it render on top (breadcrumbs), the rest below.
    $heroIndexes = collect($blocks)->filter(fn ($b) => in_array($b['data']['column'] ?? 'full', ['left', 'right'], true))->keys();
    $firstHero = $heroIndexes->min();
    $lastHero = $heroIndexes->max();
?>

<div class="pb-product mx-auto <?php echo e($template->containerClass()); ?> px-4 py-8 sm:px-6"
     x-data='{
        qty: 1,
        selections: {},
        variations: <?php echo json_encode($variationData, 15, 512) ?>,
        variation: null,
        message: "",
        requiresVariation: <?php echo e($variationAttributes->isNotEmpty() ? 'true' : 'false'); ?>,
        select(attr, value) {
            this.selections[attr] = value;
            this.variation = this.variations.find(v =>
                Object.entries(v.options).every(([a, val]) => this.selections[a] === val)
            ) ?? null;
        },
        async add() {
            this.message = "";
            try {
                await window.shopkit.addToCart(<?php echo e($product->id); ?>, this.qty, this.variation?.id ?? null);
                this.message = "Added to cart ✓";
            } catch (e) { this.message = e.message; }
        }
     }'>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $blocks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $block): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($block['data']['column'] ?? 'full') === 'full' && ($firstHero === null || $i < $firstHero)): ?>
            <?php if ($__env->exists('partials.product.'.$block['type'], ['block' => $block['data'] ?? []])) echo $__env->make('partials.product.'.$block['type'], ['block' => $block['data'] ?? []], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($firstHero !== null): ?>
        <div class="mt-6 grid gap-10 lg:grid-cols-2">
            <div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $blocks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $block): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($block['data']['column'] ?? 'full') === 'left'): ?>
                        <?php if ($__env->exists('partials.product.'.$block['type'], ['block' => $block['data'] ?? []])) echo $__env->make('partials.product.'.$block['type'], ['block' => $block['data'] ?? []], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
            <div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $blocks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $block): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($block['data']['column'] ?? 'full') === 'right'): ?>
                        <?php if ($__env->exists('partials.product.'.$block['type'], ['block' => $block['data'] ?? []])) echo $__env->make('partials.product.'.$block['type'], ['block' => $block['data'] ?? []], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <!--critical-fold-->

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $blocks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $block): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($block['data']['column'] ?? 'full') === 'full' && $firstHero !== null && $i > $lastHero): ?>
            <?php if ($__env->exists('partials.product.'.$block['type'], ['block' => $block['data'] ?? []])) echo $__env->make('partials.product.'.$block['type'], ['block' => $block['data'] ?? []], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->type !== 'external' && $product->type !== 'grouped'): ?>
        <div
            x-data="{ shown: false }"
            x-init="window.addEventListener('scroll', () => { shown = window.scrollY > 480; }, { passive: true })"
            x-show="shown"
            x-transition.opacity
            x-cloak
            class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 px-4 py-3 shadow-lg backdrop-blur lg:hidden"
            style="padding-bottom: calc(env(safe-area-inset-bottom) + 12px); display:none;"
        >
            <div class="flex items-center gap-2.5">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium"><?php echo e($product->name); ?></p>
                    <p class="text-sm text-gray-600"><?php echo e(price_format($product->currentPrice())); ?></p>
                </div>
                
                <div class="flex shrink-0 items-center overflow-hidden rounded-full border border-gray-300 bg-white">
                    <button type="button"
                            class="flex h-10 w-10 items-center justify-center text-lg leading-none text-gray-600 active:bg-gray-200"
                            @click="qty = Math.max(1, qty - 1)" aria-label="Decrease quantity">−</button>
                    <input type="number" x-model.number="qty" min="1" aria-label="Quantity"
                           class="h-10 w-10 border-0 p-0 text-center text-sm font-semibold [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none focus:ring-0">
                    <button type="button"
                            class="flex h-10 w-10 items-center justify-center text-lg leading-none text-gray-600 active:bg-gray-200"
                            @click="qty++" aria-label="Increase quantity">+</button>
                </div>
                <button type="button" @click="add()"
                        :disabled="requiresVariation && !variation"
                        class="shrink-0 rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">
                    Add to cart
                </button>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php echo $__env->make('partials.custom-code', ['model' => $product], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/shop/product.blade.php ENDPATH**/ ?>