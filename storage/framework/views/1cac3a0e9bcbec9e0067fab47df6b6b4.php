<?php
    // Taxonomy attributes first (canonical, matches the additionalProperty
    // schema), then legacy free-form specifications — deduped by label so
    // an old "Flavor: Menthol" spec never doubles a taxonomy row.
    $specs = [];
    foreach ($product->attributeFacts() as $label => $value) {
        $specs[] = [$label, $value];
    }
    if (is_array($product->specifications)) {
        foreach ($product->specifications as $key => $spec) {
            if (is_array($spec)) {
                $specs[] = [$spec['name'] ?? $spec['label'] ?? '', $spec['value'] ?? ''];
            } elseif (! is_int($key)) {
                $specs[] = [$key, $spec];
            }
        }
    }
    $specs = array_filter($specs, fn ($s) => trim((string) $s[0]) !== '');
    $specs = collect($specs)->unique(fn ($s) => mb_strtolower(trim((string) $s[0])))->values()->all();
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($specs !== []): ?>
    <?php if (isset($component)) { $__componentOriginal5c0843292bab0c5a1d8836f66560edf7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5c0843292bab0c5a1d8836f66560edf7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.pb-block','data' => ['data' => $block,'class' => 'mt-12']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('pb-block'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($block),'class' => 'mt-12']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

        <section aria-labelledby="specs-heading">
            <h2 id="specs-heading" class="text-xl font-bold" style="color: var(--pb-heading, inherit)"><?php echo e($block['heading'] ?? 'Specifications'); ?></h2>
            <dl class="mt-3 divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200 text-sm">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $specs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $value]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="flex justify-between gap-4 p-3 odd:bg-gray-50">
                        <dt class="font-medium text-gray-500"><?php echo e($label); ?></dt>
                        <dd class="text-right text-gray-800"><?php echo e($value); ?></dd>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </dl>
        </section>
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
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/partials/product/specifications.blade.php ENDPATH**/ ?>