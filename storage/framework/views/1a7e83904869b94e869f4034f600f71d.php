<?php $btn = $block['button_text'] ?? 'Add to cart'; ?>
<?php if (isset($component)) { $__componentOriginal5c0843292bab0c5a1d8836f66560edf7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5c0843292bab0c5a1d8836f66560edf7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.pb-block','data' => ['data' => $block,'class' => 'mt-6']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('pb-block'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($block),'class' => 'mt-6']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($product->type === 'external'): ?>
        <a href="<?php echo e($product->external_url); ?>" rel="nofollow noopener" target="_blank"
           class="inline-block rounded-md bg-indigo-600 px-8 py-3 font-semibold text-white hover:bg-indigo-500">
            <?php echo e($product->external_button_text ?: 'Buy now'); ?>

        </a>
    <?php elseif($product->type === 'grouped'): ?>
        <div class="space-y-3 rounded-lg border border-gray-200 p-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $product->groupedChildren; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $child): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="flex items-center justify-between gap-4 text-sm">
                    <a href="<?php echo e($child->url()); ?>" class="font-medium hover:text-indigo-600"><?php echo e($child->name); ?></a>
                    <?php if (isset($component)) { $__componentOriginal5c7c50258000edf57abfef324d310474 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5c7c50258000edf57abfef324d310474 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.price','data' => ['product' => $child]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('price'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['product' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($child)]); ?>
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
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="flex items-center gap-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($block['show_quantity'] ?? true): ?>
                <div class="flex items-center overflow-hidden rounded-md border border-gray-300">
                    <button type="button"
                            class="px-3.5 py-2 text-lg leading-none text-gray-500 hover:bg-gray-100 hover:text-indigo-600 active:bg-gray-200"
                            @click="qty = Math.max(1, qty - 1)" aria-label="Decrease quantity">−</button>
                    <input type="number" x-model.number="qty" min="1" class="w-14 border-0 text-center text-sm focus:ring-0" aria-label="Quantity">
                    <button type="button"
                            class="px-3.5 py-2 text-lg leading-none text-gray-500 hover:bg-gray-100 hover:text-indigo-600 active:bg-gray-200"
                            @click="qty++" aria-label="Increase quantity">+</button>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <button type="button" @click="add()"
                    data-primary-add-to-cart
                    :disabled="requiresVariation && !variation"
                    class="flex-1 rounded-md bg-indigo-600 px-8 py-3 font-semibold text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50 sm:flex-none">
                <?php echo e($btn); ?>

            </button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($block['show_wishlist'] ?? true): ?>
                    <form action="<?php echo e(route('wishlist.toggle', $product)); ?>" method="POST">
                        <?php echo csrf_field(); ?>
                        
                        <button class="rounded-md border border-gray-300 p-3 hover:border-red-300 hover:bg-red-50 hover:text-red-500" aria-label="Add to wishlist">♥</button>
                    </form>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <p class="mt-2 text-sm text-indigo-600" x-text="message" x-show="message" style="display:none"></p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/partials/product/add_to_cart.blade.php ENDPATH**/ ?>