<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['product']));

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

foreach (array_filter((['product']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php [$min, $max] = $product->priceRange(); ?>
<p <?php echo e($attributes->merge(['class' => 'text-sm font-semibold'])); ?>>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->type === 'variable' && $min !== $max): ?>
        <?php echo e(price_format($min)); ?> – <?php echo e(price_format($max)); ?>

    <?php elseif($product->isOnSale()): ?>
        <span class="text-red-600"><?php echo e(price_format($product->currentPrice())); ?></span>
        <s class="ml-1 font-normal text-gray-400"><?php echo e(price_format($product->price)); ?></s>
    <?php else: ?>
        <?php echo e(price_format($product->currentPrice())); ?>

    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</p>
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/components/price.blade.php ENDPATH**/ ?>