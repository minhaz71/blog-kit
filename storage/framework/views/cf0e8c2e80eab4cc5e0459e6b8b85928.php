<?php
    // Normalise specifications (supports both {"Flavor":"Menthol"} maps and
    // [{"name":..,"value":..}] lists) into label/value pairs.
    $facts = [];
    if (($block['use_specifications'] ?? true)) {
        // Taxonomy attributes lead (canonical facets, mirrors the
        // additionalProperty schema), legacy free-form specs follow.
        foreach ($product->attributeFacts() as $label => $value) {
            $facts[] = [$label, $value];
        }
        if (is_array($product->specifications)) {
            foreach ($product->specifications as $key => $spec) {
                if (is_array($spec)) {
                    $facts[] = [$spec['name'] ?? $spec['label'] ?? '', $spec['value'] ?? ''];
                } elseif (! is_int($key)) {
                    $facts[] = [$key, $spec];
                }
            }
        }
    }
    foreach ((array) ($block['items'] ?? []) as $item) {
        if (! empty($item['label'])) {
            $facts[] = [$item['label'], $item['value'] ?? ''];
        }
    }
    $facts = array_filter($facts, fn ($f) => trim((string) $f[0]) !== '');
    $facts = collect($facts)->unique(fn ($f) => mb_strtolower(trim((string) $f[0])))->values()->all();
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($facts !== []): ?>
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

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($block['heading'])): ?>
            <p class="mb-2 font-semibold" style="color: var(--pb-heading, inherit)"><?php echo e($block['heading']); ?></p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <ul class="list-disc space-y-1 pl-5 text-sm text-gray-600">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $facts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $value]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <li><span class="font-medium text-gray-800"><?php echo e($label); ?>:</span> <?php echo e($value); ?></li>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </ul>
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
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/partials/product/key_facts.blade.php ENDPATH**/ ?>