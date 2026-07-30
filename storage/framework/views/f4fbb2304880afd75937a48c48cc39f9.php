<?php if (isset($component)) { $__componentOriginal166a02a7c5ef5a9331faf66fa665c256 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal166a02a7c5ef5a9331faf66fa665c256 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament-panels::components.page.index','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament-panels::page'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <style>
        .sup-grid { display: grid; gap: 1rem; }
        @media (min-width: 1024px) { .sup-grid { grid-template-columns: 1fr 1fr; } }
        .sup-card { background: #fff; border: 1px solid #e5e7eb; border-radius: .9rem; padding: 1.15rem 1.3rem; }
        .dark .sup-card { background: #18181b; border-color: #27272a; }
        .sup-card h3 { font-size: .74rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: #6b7280; margin: 0 0 .8rem; }
        .sup-ver { display: flex; align-items: baseline; gap: .6rem; }
        .sup-ver b { font-size: 2rem; font-weight: 800; color: #0f766e; line-height: 1; }
        .dark .sup-ver b { color: #5eead4; }
        .sup-ver span { font-size: .8rem; color: #6b7280; }
        .sup-meta { margin-top: .7rem; font-size: .82rem; color: #6b7280; line-height: 1.6; }
        .sup-meta code { background: #f1f5f9; padding: .05rem .35rem; border-radius: .3rem; font-size: .78rem; }
        .dark .sup-meta code { background: #27272a; }
        .sup-badge { display: inline-block; padding: .15rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 700; }
        .sup-badge--ok { background: #d1fae5; color: #065f46; }
        .sup-badge--new { background: #fef3c7; color: #92400e; }
        .dark .sup-badge--ok { background: #06402955; color: #6ee7b7; }
        .dark .sup-badge--new { background: #78350f55; color: #fcd34d; }
        .sup-tools { width: 100%; border-collapse: collapse; font-size: .86rem; }
        .sup-tools td { padding: .5rem .3rem; border-bottom: 1px solid #f1f5f9; }
        .dark .sup-tools td { border-color: #27272a; }
        .sup-tools td:last-child { text-align: right; font-variant-numeric: tabular-nums; color: #0f766e; font-weight: 700; }
        .dark .sup-tools td:last-child { color: #5eead4; }
        .sup-check { display: flex; align-items: flex-start; gap: .5rem; padding: .4rem 0; font-size: .84rem; border-bottom: 1px solid #f8fafc; }
        .dark .sup-check { border-color: #27272a; }
        .sup-check__ic { flex: none; margin-top: .1rem; }
        .sup-check__ic--ok { color: #16a34a; } .sup-check__ic--crit { color: #dc2626; } .sup-check__ic--warn { color: #d97706; }
        .sup-check small { display: block; color: #6b7280; margin-top: .1rem; }
        .sup-changelog { font-size: .85rem; line-height: 1.7; white-space: pre-wrap; color: #374151; max-height: 22rem; overflow-y: auto; }
        .dark .sup-changelog { color: #d4d4d8; }
    </style>

    <?php ($crit = collect($preflight['checks'])->where('ok', false)->where('severity', 'critical')); ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($behind): ?>
        <div class="sup-card" style="border-color:#f59e0b">
            <strong>⬆ <?php echo e($behind); ?> update(s) available.</strong>
            Click <em>Update ShopKit</em> above. A full backup is taken first and the update rolls back automatically if anything fails.
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="sup-grid">
        
        <div class="sup-card">
            <h3>ShopKit version</h3>
            <div class="sup-ver">
                <b><?php echo e($core); ?></b>
                <span><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($behind === 0): ?><span class="sup-badge sup-badge--ok">latest</span><?php elseif($behind): ?><span class="sup-badge sup-badge--new"><?php echo e($behind); ?> behind</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span>
            </div>
            <div class="sup-meta">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($released): ?>Released <?php echo e($released); ?><br><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isGit): ?>
                    Branch <code><?php echo e($branch ?? '?'); ?></code> · commit <code><?php echo e($commit ?? '?'); ?></code><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($committedAt): ?> · <?php echo e($committedAt); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php else: ?>
                    <span style="color:#d97706">Not a git checkout — deploy via git to enable one-click updates (see docs/DEPLOYMENT.md).</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        
        <div class="sup-card">
            <h3>Installed tools</h3>
            <table class="sup-tools">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $components; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slug => $version): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <tr><td><?php echo e($labels[$slug] ?? $slug); ?></td><td>v<?php echo e($version); ?></td></tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </table>
        </div>
    </div>

    <div class="sup-grid">
        
        <div class="sup-card">
            <h3>Production readiness
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preflight['ok']): ?><span class="sup-badge sup-badge--ok">ready</span>
                <?php else: ?><span class="sup-badge sup-badge--new"><?php echo e($crit->count()); ?> to fix</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </h3>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $preflight['checks']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="sup-check">
                    <span class="sup-check__ic sup-check__ic--<?php echo e($c['ok'] ? 'ok' : ($c['severity'] === 'critical' ? 'crit' : 'warn')); ?>">
                        <?php echo e($c['ok'] ? '✓' : ($c['severity'] === 'critical' ? '✕' : '!')); ?>

                    </span>
                    <span>
                        <?php echo e($c['label']); ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($c['ok'])): ?><small><?php echo e($c['detail']); ?></small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </span>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>

        
        <div class="sup-card">
            <h3>What's new</h3>
            <div class="sup-changelog"><?php echo e($changelog ?: 'No changelog found.'); ?></div>
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal166a02a7c5ef5a9331faf66fa665c256)): ?>
<?php $attributes = $__attributesOriginal166a02a7c5ef5a9331faf66fa665c256; ?>
<?php unset($__attributesOriginal166a02a7c5ef5a9331faf66fa665c256); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal166a02a7c5ef5a9331faf66fa665c256)): ?>
<?php $component = $__componentOriginal166a02a7c5ef5a9331faf66fa665c256; ?>
<?php unset($__componentOriginal166a02a7c5ef5a9331faf66fa665c256); ?>
<?php endif; ?>
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/filament/pages/system-updates.blade.php ENDPATH**/ ?>