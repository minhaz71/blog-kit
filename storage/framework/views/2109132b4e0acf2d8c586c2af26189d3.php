
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$cart || $cart->items->isEmpty()): ?>
    <div class="flex flex-1 flex-col items-center justify-center gap-3 p-8 text-center">
        <svg class="h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 4.6a1 1 0 00.9 1.4H19M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
        <p class="font-medium text-gray-700">Your cart is empty</p>
        <a href="<?php echo e(route('shop')); ?>" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Start shopping</a>
    </div>
<?php else: ?>
    <ul class="flex-1 divide-y divide-gray-100 overflow-y-auto px-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $cart->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <li class="flex gap-3 py-4">
                <a href="<?php echo e($item->product->url()); ?>" class="h-16 w-16 shrink-0 overflow-hidden rounded-md bg-gray-100">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($image = $item->variation?->imageUrl() ?? $item->product->featuredImageUrl()): ?>
                        <img src="<?php echo e($image); ?>" alt="<?php echo e($item->product->name); ?>" width="64" height="64" class="h-full w-full object-cover">
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </a>
                <div class="flex min-w-0 flex-1 flex-col" x-data="{ busy: false }">
                    <div class="flex items-start justify-between gap-2">
                        <a href="<?php echo e($item->product->url()); ?>" class="truncate text-sm font-medium hover:text-indigo-600"><?php echo e($item->product->name); ?></a>
                        <button type="button"
                                class="shrink-0 text-gray-400 hover:text-red-600 disabled:opacity-40"
                                aria-label="Remove <?php echo e($item->product->name); ?>"
                                :disabled="busy"
                                @click="busy = true; shopkit.removeItem(<?php echo e($item->id); ?>).catch(e => { alert(e.message); busy = false })">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->variation): ?>
                        <p class="text-xs text-gray-500"><?php echo e($item->variation->label()); ?></p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <div class="mt-auto flex items-center justify-between pt-1.5">
                        <div class="flex items-center rounded border border-gray-300" :class="busy && 'opacity-50'">
                            <button type="button"
                                    class="flex h-7 w-7 items-center justify-center text-gray-600 hover:bg-gray-100 disabled:opacity-40"
                                    aria-label="Decrease quantity" :disabled="busy || <?php echo e($item->qty); ?> <= 1"
                                    @click="busy = true; shopkit.setQty(<?php echo e($item->id); ?>, <?php echo e($item->qty - 1); ?>).catch(e => { alert(e.message); busy = false })">&minus;</button>
                            <span class="w-8 text-center text-sm tabular-nums"><?php echo e($item->qty); ?></span>
                            <button type="button"
                                    class="flex h-7 w-7 items-center justify-center text-gray-600 hover:bg-gray-100 disabled:opacity-40"
                                    aria-label="Increase quantity" :disabled="busy"
                                    @click="busy = true; shopkit.setQty(<?php echo e($item->id); ?>, <?php echo e($item->qty + 1); ?>).catch(e => { alert(e.message); busy = false })">+</button>
                        </div>
                        <span class="text-sm font-semibold"><?php echo e(price_format($item->lineTotal())); ?></span>
                    </div>
                </div>
            </li>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </ul>
    <div class="border-t border-gray-200 p-4">
        <div class="flex items-center justify-between text-sm">
            <span class="text-gray-600">Subtotal</span>
            <span class="text-base font-bold"><?php echo e(price_format($cart->subtotal())); ?></span>
        </div>
        <p class="mt-1 text-xs text-gray-500">Shipping and taxes calculated at checkout.</p>
        <div class="mt-3 grid grid-cols-2 gap-2">
            <a href="<?php echo e(route('cart.index')); ?>" class="rounded-md border border-gray-300 px-4 py-2.5 text-center text-sm font-semibold hover:bg-gray-50">View cart</a>
            <a href="<?php echo e(route('checkout.index')); ?>" class="rounded-md bg-indigo-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-indigo-700">Checkout</a>
        </div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/partials/cart-drawer-content.blade.php ENDPATH**/ ?>