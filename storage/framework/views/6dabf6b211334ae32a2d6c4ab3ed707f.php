<?php
    // Legal / policy pages get a "Last updated" line; marketing pages (contact,
    // about) don't need one.
    $legal = in_array($page->slug, ['terms-and-conditions', 'privacy-policy', 'refund-policy', 'shipping-policy'], true);
    // City delivery pages lead with the category catalogue so a local visitor
    // lands straight on the product categories, then reads the copy below.
    $isCity = str_starts_with($page->slug, 'terea-delivery-');
    // Contact page always shows the contact cards + message form, even if the
    // admin never added the {{contact_info}} / {{contact_form}} shortcodes to
    // the page body. Guarded so we never render them twice.
    $isContact = in_array($page->slug, ['contact-us', 'contact'], true);
    $bodyHasForm = str_contains((string) $page->content, 'contact_form');
    $bodyHasInfo = str_contains((string) $page->content, 'contact_info');
?>

<?php $__env->startSection('content'); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($isPreview)): ?>
    <div class="bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-white">
        Draft preview — not visible to visitors.
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isCity): ?>
    <?php
        // "TEREA Delivery in Abu Dhabi" → "Abu Dhabi" for the lead line.
        $cityName = \Illuminate\Support\Str::of($page->title)->after(' in ')->trim()->value();
    ?>
    
    <div class="mx-auto max-w-7xl px-4 pt-8 pb-10 sm:px-6">
        <?php if (isset($component)) { $__componentOriginal360d002b1b676b6f84d43220f22129e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal360d002b1b676b6f84d43220f22129e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.breadcrumbs','data' => ['crumbs' => $seo->breadcrumbs]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('breadcrumbs'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['crumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($seo->breadcrumbs)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal360d002b1b676b6f84d43220f22129e2)): ?>
<?php $attributes = $__attributesOriginal360d002b1b676b6f84d43220f22129e2; ?>
<?php unset($__attributesOriginal360d002b1b676b6f84d43220f22129e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal360d002b1b676b6f84d43220f22129e2)): ?>
<?php $component = $__componentOriginal360d002b1b676b6f84d43220f22129e2; ?>
<?php unset($__componentOriginal360d002b1b676b6f84d43220f22129e2); ?>
<?php endif; ?>
        <header class="mb-8 mt-4">
            <h1 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl"><?php echo e($page->title); ?></h1>
            <p class="mt-3 max-w-2xl text-base text-gray-600">
                Genuine IQOS TEREA<?php echo e($cityName ? ', delivered across '.$cityName : ''); ?> — browse a category below and order in a couple of taps.
            </p>
        </header>
        <?php echo $__env->make('partials.city-catalogue', ['flush' => true, 'hideHead' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>

    <div class="mx-auto max-w-3xl px-4 pb-10 sm:px-6">
        <div class="pd-content"><?php echo parse_shortcodes($page->content); ?></div>
        <?php if (isset($component)) { $__componentOriginald509f1dd991e98b5837bfe6e90a061dc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald509f1dd991e98b5837bfe6e90a061dc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.faq-section','data' => ['faqs' => $page->faqs]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('faq-section'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['faqs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($page->faqs)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald509f1dd991e98b5837bfe6e90a061dc)): ?>
<?php $attributes = $__attributesOriginald509f1dd991e98b5837bfe6e90a061dc; ?>
<?php unset($__attributesOriginald509f1dd991e98b5837bfe6e90a061dc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald509f1dd991e98b5837bfe6e90a061dc)): ?>
<?php $component = $__componentOriginald509f1dd991e98b5837bfe6e90a061dc; ?>
<?php unset($__componentOriginald509f1dd991e98b5837bfe6e90a061dc); ?>
<?php endif; ?>
    </div>
<?php else: ?>
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <?php if (isset($component)) { $__componentOriginal360d002b1b676b6f84d43220f22129e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal360d002b1b676b6f84d43220f22129e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.breadcrumbs','data' => ['crumbs' => $seo->breadcrumbs]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('breadcrumbs'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['crumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($seo->breadcrumbs)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal360d002b1b676b6f84d43220f22129e2)): ?>
<?php $attributes = $__attributesOriginal360d002b1b676b6f84d43220f22129e2; ?>
<?php unset($__attributesOriginal360d002b1b676b6f84d43220f22129e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal360d002b1b676b6f84d43220f22129e2)): ?>
<?php $component = $__componentOriginal360d002b1b676b6f84d43220f22129e2; ?>
<?php unset($__componentOriginal360d002b1b676b6f84d43220f22129e2); ?>
<?php endif; ?>

        
        <header class="mt-5 border-b border-gray-200 pb-6">
            <h1 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl"><?php echo e($page->title); ?></h1>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($legal): ?>
                <p class="mt-2 flex items-center gap-1.5 text-sm text-gray-500">
                    <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                    Last updated <?php echo e($page->updated_at->format('F j, Y')); ?>

                </p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </header>

        
        <div class="pd-content mt-8"><?php echo parse_shortcodes($page->content); ?></div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isContact): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($bodyHasInfo)): ?> <?php echo $__env->make('partials.contact-info', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($bodyHasForm)): ?> <?php echo $__env->make('partials.contact-form', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if (isset($component)) { $__componentOriginald509f1dd991e98b5837bfe6e90a061dc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald509f1dd991e98b5837bfe6e90a061dc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.faq-section','data' => ['faqs' => $page->faqs]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('faq-section'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['faqs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($page->faqs)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald509f1dd991e98b5837bfe6e90a061dc)): ?>
<?php $attributes = $__attributesOriginald509f1dd991e98b5837bfe6e90a061dc; ?>
<?php unset($__attributesOriginald509f1dd991e98b5837bfe6e90a061dc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald509f1dd991e98b5837bfe6e90a061dc)): ?>
<?php $component = $__componentOriginald509f1dd991e98b5837bfe6e90a061dc; ?>
<?php unset($__componentOriginald509f1dd991e98b5837bfe6e90a061dc); ?>
<?php endif; ?>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php echo $__env->make('partials.custom-code', ['model' => $page], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/pages/show.blade.php ENDPATH**/ ?>