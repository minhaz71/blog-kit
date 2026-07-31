<?php if (isset($component)) { $__componentOriginal5c0843292bab0c5a1d8836f66560edf7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5c0843292bab0c5a1d8836f66560edf7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.pb-block','data' => ['data' => $block,'class' => 'mt-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('pb-block'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($block),'class' => 'mt-2']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <div class="flex items-center gap-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->reviews_count > 0): ?>
            <?php if (isset($component)) { $__componentOriginal077a61d60611f096a94f8e1725d6bb16 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal077a61d60611f096a94f8e1725d6bb16 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.rating-stars','data' => ['rating' => $product->avg_rating,'count' => $product->reviews_count]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('rating-stars'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['rating' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($product->avg_rating),'count' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($product->reviews_count)]); ?>
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
        <?php else: ?>
            <span class="text-xs text-gray-400">No reviews yet</span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->sku): ?>
            <span class="text-xs text-gray-400" x-text="variation?.sku ? 'SKU: ' + variation.sku : 'SKU: <?php echo e($product->sku); ?>'">SKU: <?php echo e($product->sku); ?></span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5c0843292bab0c5a1d8836f66560edf7)): ?>
<?php $attributes = $__attributesOriginal5c0843292bab0c5a1d8836f66560edf7; ?>
<?php unset($__attributesOriginal5c0843292bab0c5a1d8836f66560edf7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5c0843292bab0c5a1d8836f66560edf7)): ?>
<?php $component = $__componentOriginal5c0843292bab0c5a1d8836f66560edf7; ?>
<?php unset($__componentOriginal5c0843292bab0c5a1d8836f66560edf7); ?>
<?php endif; ?>
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/partials/product/rating.blade.php ENDPATH**/ ?>