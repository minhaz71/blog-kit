<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
    <h1 class="text-3xl font-bold">Your cart</h1>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$cart || $cart->items->isEmpty()): ?>
        <div class="mt-12 text-center">
            <p class="text-gray-500">Your cart is empty.</p>
            <a href="<?php echo e(route('shop')); ?>" class="mt-4 inline-block rounded-md bg-indigo-600 px-6 py-3 text-sm font-semibold text-white hover:bg-indigo-500">Continue shopping</a>
        </div>
    <?php else: ?>
        <div class="mt-6 grid gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <ul class="divide-y divide-gray-200 rounded-lg border border-gray-200">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $cart->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <li class="flex gap-4 p-4">
                            <a href="<?php echo e($item->product->url()); ?>" class="h-20 w-20 shrink-0 overflow-hidden rounded-md bg-gray-100">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($image = $item->variation?->imageUrl() ?? $item->product->featuredImageUrl()): ?>
                                    <img src="<?php echo e($image); ?>" alt="<?php echo e($item->product->name); ?>" width="80" height="80" class="h-full w-full object-cover">
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </a>
                            <div class="flex flex-1 flex-col">
                                <div class="flex justify-between gap-2">
                                    <div>
                                        <a href="<?php echo e($item->product->url()); ?>" class="font-medium hover:text-indigo-600"><?php echo e($item->product->name); ?></a>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->variation): ?>
                                            <p class="text-xs text-gray-500"><?php echo e($item->variation->label()); ?></p>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                    <p class="font-semibold"><?php echo e(price_format($item->lineTotal())); ?></p>
                                </div>
                                <div class="mt-auto flex items-center justify-between pt-2">
                                    <div class="flex items-center gap-2">
                                        <form action="<?php echo e(route('cart.update', $item->id)); ?>" method="POST"
                                              class="flex items-center"
                                              x-data="{ qty: <?php echo e($item->qty); ?>, busy: false,
                                                        change(next) {
                                                            next = Math.max(1, Math.min(999, next));
                                                            if (next === this.qty || this.busy) return;
                                                            this.busy = true; this.qty = next;
                                                            shopkit.setQty(<?php echo e($item->id); ?>, next)
                                                                .then(() => window.location.reload())
                                                                .catch(e => { alert(e.message); this.busy = false; });
                                                        } }">
                                            <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                                            <label class="sr-only" for="qty-<?php echo e($item->id); ?>">Quantity for <?php echo e($item->product->name); ?></label>
                                            <div class="flex items-center rounded-md border border-gray-300" :class="busy && 'opacity-50'">
                                                <button type="button"
                                                        class="flex h-9 w-9 items-center justify-center text-lg text-gray-600 hover:bg-gray-100 active:bg-gray-200 disabled:opacity-40"
                                                        aria-label="Decrease quantity"
                                                        :disabled="busy || qty <= 1"
                                                        @click="change(qty - 1)">&minus;</button>
                                                <input id="qty-<?php echo e($item->id); ?>" type="number" name="qty" x-model.number="qty"
                                                       min="1" max="999" aria-label="Quantity"
                                                       class="h-9 w-12 border-0 p-0 text-center text-sm [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none focus:ring-0"
                                                       @change="change(qty)">
                                                <button type="button"
                                                        class="flex h-9 w-9 items-center justify-center text-lg text-gray-600 hover:bg-gray-100 active:bg-gray-200 disabled:opacity-40"
                                                        aria-label="Increase quantity"
                                                        :disabled="busy"
                                                        @click="change(qty + 1)">+</button>
                                            </div>
                                        </form>
                                        <span class="text-xs text-gray-400">× <?php echo e(price_format($item->unitPrice())); ?></span>
                                    </div>
                                    <form action="<?php echo e(route('cart.remove', $item->id)); ?>" method="POST" x-data="{ busy: false }">
                                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                                        <button type="button" :disabled="busy"
                                                class="text-sm text-red-500 hover:underline disabled:opacity-40"
                                                @click="busy = true; shopkit.removeItem(<?php echo e($item->id); ?>).then(() => window.location.reload()).catch(e => { alert(e.message); busy = false })">Remove</button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </ul>
            </div>

            <div>
                <div class="rounded-lg border border-gray-200 p-4">
                    <h2 class="font-semibold">Summary</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between"><dt>Subtotal</dt><dd class="font-medium"><?php echo e(price_format($cart->subtotal())); ?></dd></div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($discount > 0): ?>
                            <div class="flex justify-between text-green-600">
                                <dt>Coupon (<?php echo e($cart->coupon->code); ?>)</dt>
                                <dd>−<?php echo e(price_format($discount)); ?></dd>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-bold">
                            <dt>Total</dt><dd><?php echo e(price_format(max(0, $cart->subtotal() - $discount))); ?></dd>
                        </div>
                    </dl>
                    <p class="mt-1 text-xs text-gray-400">Shipping and tax calculated at checkout.</p>
                    <a href="<?php echo e(route('checkout.index')); ?>" class="mt-4 block rounded-md bg-indigo-600 px-6 py-3 text-center font-semibold text-white hover:bg-indigo-500">Checkout</a>
                </div>

                <div class="mt-4 rounded-lg border border-gray-200 p-4">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cart->coupon): ?>
                        <form action="<?php echo e(route('cart.coupon.remove')); ?>" method="POST" class="flex items-center justify-between text-sm">
                            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                            <span>Coupon <strong><?php echo e($cart->coupon->code); ?></strong> applied</span>
                            <button class="text-red-500 hover:underline">Remove</button>
                        </form>
                    <?php else: ?>
                        <form action="<?php echo e(route('cart.coupon')); ?>" method="POST" class="flex gap-2">
                            <?php echo csrf_field(); ?>
                            <input type="text" name="code" required placeholder="Coupon code" class="w-full rounded-md border-gray-300 text-sm uppercase" aria-label="Coupon code">
                            <button class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50">Apply</button>
                        </form>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/cart/index.blade.php ENDPATH**/ ?>