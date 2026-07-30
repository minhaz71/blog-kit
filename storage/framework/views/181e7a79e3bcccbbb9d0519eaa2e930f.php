<?php if (isset($component)) { $__componentOriginal5c0843292bab0c5a1d8836f66560edf7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5c0843292bab0c5a1d8836f66560edf7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.pb-block','data' => ['data' => $block,'class' => 'mt-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('pb-block'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($block),'class' => 'mt-4']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <div class="font-bold" style="font-size: inherit">
        <template x-if="variation">
            <span>
                <span x-text="variation.price"></span>
                <s class="ml-2 text-base font-normal text-gray-400" x-show="variation.regular" x-text="variation.regular"></s>
            </span>
        </template>
        <template x-if="!variation">
            <span>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->isOnSale()): ?>
                    <span class="text-red-600"><?php echo e(price_format($product->currentPrice())); ?></span>
                    <s class="ml-2 text-base font-normal text-gray-400"><?php echo e(price_format($product->price)); ?></s>
                <?php elseif($product->type === 'variable'): ?>
                    <?php [$min, $max] = $product->priceRange(); ?>
                    <?php echo e($min === $max ? price_format($min) : price_format($min).' – '.price_format($max)); ?>

                <?php else: ?>
                    <?php echo e(price_format($product->currentPrice())); ?>

                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </span>
        </template>
    </div>
    <p class="mt-2 text-sm font-medium"
       :class="(variation ? variation.in_stock : <?php echo e($product->isInStock() ? 'true' : 'false'); ?>) ? 'text-green-600' : 'text-red-600'"
       x-text="(variation ? variation.in_stock : <?php echo e($product->isInStock() ? 'true' : 'false'); ?>) ? 'In stock' : 'Out of stock'"></p>
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
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/partials/product/price.blade.php ENDPATH**/ ?>