<aside class="lg:w-64 shrink-0" x-data="{ show: false }">
    <button class="mb-4 w-full rounded-md border border-gray-300 px-4 py-2 text-sm font-medium lg:hidden" @click="show = !show">
        Filters
    </button>

    <form method="GET" class="space-y-6" :class="{ 'hidden lg:block': !show }" x-bind:class="show ? '' : 'hidden lg:block'">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(request('sort')): ?> <input type="hidden" name="sort" value="<?php echo e(request('sort')); ?>"> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div>
            <p class="text-sm font-semibold">Price</p>
            <div class="mt-2 flex items-center gap-2">
                <input type="number" name="min_price" value="<?php echo e(request('min_price')); ?>" min="0" placeholder="Min" class="w-full rounded-md border-gray-300 text-sm" aria-label="Minimum price">
                <span class="text-gray-400">–</span>
                <input type="number" name="max_price" value="<?php echo e(request('max_price')); ?>" min="0" placeholder="Max" class="w-full rounded-md border-gray-300 text-sm" aria-label="Maximum price">
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($filterData['brands']->isNotEmpty()): ?>
            <div>
                <p class="text-sm font-semibold">Brand</p>
                <div class="mt-2 space-y-1">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filterData['brands']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $brand): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="brand[]" value="<?php echo e($brand->slug); ?>" <?php if(in_array($brand->slug, (array) request('brand', []))): echo 'checked'; endif; ?> class="rounded border-gray-300">
                            <?php echo e($brand->name); ?>

                        </label>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filterData['attributes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attribute): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($attribute->values->isNotEmpty()): ?>
                <div>
                    <p class="text-sm font-semibold"><?php echo e($attribute->name); ?></p>
                    <?php $selected = array_filter(explode(',', (string) request("attr.{$attribute->slug}", ''))); ?>
                    <div class="mt-2 flex flex-wrap gap-1.5" x-data>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $attribute->values; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <label class="cursor-pointer rounded-full border px-3 py-1 text-xs <?php echo e(in_array($value->slug, $selected) ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-gray-300'); ?>">
                                <input type="checkbox" class="sr-only" name="attr_raw[<?php echo e($attribute->slug); ?>][]" value="<?php echo e($value->slug); ?>" <?php if(in_array($value->slug, $selected)): echo 'checked'; endif; ?> onchange="this.form.requestSubmit()">
                                <?php echo e($value->value); ?>

                            </label>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

        <div class="space-y-1 text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="in_stock" value="1" <?php if(request('in_stock')): echo 'checked'; endif; ?> class="rounded border-gray-300"> In stock only</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="on_sale" value="1" <?php if(request('on_sale')): echo 'checked'; endif; ?> class="rounded border-gray-300"> On sale</label>
        </div>

        <div>
            <p class="text-sm font-semibold">Minimum rating</p>
            <select name="rating" class="mt-2 w-full rounded-md border-gray-300 text-sm">
                <option value="">Any</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [4, 3, 2]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stars): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <option value="<?php echo e($stars); ?>" <?php if(request('rating') == $stars): echo 'selected'; endif; ?>><?php echo e($stars); ?>★ &amp; up</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>
        </div>

        <div class="flex gap-2">
            <button class="flex-1 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Apply</button>
            <a href="<?php echo e(url()->current()); ?>" class="rounded-md border border-gray-300 px-4 py-2 text-sm">Reset</a>
        </div>
    </form>

    <script>
        // Convert attr_raw[slug][] checkboxes to attr[slug]=a,b before submit.
        document.currentScript.previousElementSibling.addEventListener('formdata', (e) => {
            const grouped = {};
            for (const [key, value] of [...e.formData.entries()]) {
                const match = key.match(/^attr_raw\[(.+)\]\[\]$/);
                if (match) {
                    (grouped[match[1]] ??= []).push(value);
                    e.formData.delete(key);
                }
            }
            for (const [slug, values] of Object.entries(grouped)) {
                e.formData.set(`attr[${slug}]`, values.join(','));
            }
        });
    </script>
</aside>
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/shop/partials/filters.blade.php ENDPATH**/ ?>