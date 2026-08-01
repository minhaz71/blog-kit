<?php
    $bg = $section->setting('image');
    $mobileBg = $section->setting('mobile_image');
    $opacity = (int) $section->setting('overlay_opacity', 40);
    $badge = $section->setting('badge');
?>
<section class="relative mt-6 overflow-hidden rounded-2xl bg-gray-900 text-white">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bg): ?>
        
        <picture>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mobileBg): ?>
                <source media="(max-width: 640px)" srcset="<?php echo e(asset('storage/'.$mobileBg)); ?>">
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <img src="<?php echo e(asset('storage/'.$bg)); ?>" alt=""
                 loading="eager" fetchpriority="high"
                 class="absolute inset-0 h-full w-full object-cover">
        </picture>
        <div class="absolute inset-0 bg-black" style="opacity: <?php echo e(max(0, min(100, $opacity)) / 100); ?>"></div>
    <?php else: ?>
        <div class="absolute inset-0 bg-gradient-to-r from-indigo-600 to-violet-600"></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="relative px-6 py-16 sm:px-12 sm:py-24">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($badge): ?>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide ring-1 ring-white/25 backdrop-blur-sm">
                <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M14.615 1.595a.75.75 0 01.359.852L12.982 9.75h7.268a.75.75 0 01.548 1.262l-10.5 11.25a.75.75 0 01-1.272-.71l1.992-7.302H3.75a.75.75 0 01-.548-1.262l10.5-11.25a.75.75 0 011.913-.143z"/></svg>
                <?php echo e($badge); ?>

            </span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <h1 class="mt-4 max-w-2xl text-3xl font-extrabold tracking-tight sm:text-5xl text-balance">
            <?php echo e($section->title ?? setting('seo.homepage_title', config('app.name'))); ?>

        </h1>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($section->subtitle || $section->setting('description')): ?>
            <p class="mt-4 max-w-xl text-lg text-white/85">
                <?php echo e($section->subtitle ?? $section->setting('description')); ?>

            </p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($section->setting('button_text') || $section->setting('button2_text')): ?>
            <div class="mt-8 flex flex-wrap items-center gap-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($section->setting('button_text') && $section->setting('button_url')): ?>
                    <a href="<?php echo e($section->setting('button_url')); ?>"
                       class="inline-block rounded-full bg-white px-6 py-3 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">
                        <?php echo e($section->setting('button_text')); ?>

                    </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($section->setting('button2_text') && $section->setting('button2_url')): ?>
                    <a href="<?php echo e($section->setting('button2_url')); ?>"
                       class="inline-block rounded-full px-6 py-3 text-sm font-semibold text-white ring-1 ring-white/50 hover:bg-white/10">
                        <?php echo e($section->setting('button2_text')); ?>

                    </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</section>
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/partials/homepage/hero.blade.php ENDPATH**/ ?>