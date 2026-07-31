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
        .abc { display: flex; flex-direction: column; gap: 1.5rem; font-variant-numeric: tabular-nums; }
        .abc-note { border-radius: .75rem; padding: .8rem 1rem; font-size: .85rem; font-weight: 600; }
        .abc-note.on { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .abc-note.off { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .dark .abc-note.on { background: rgba(16,185,129,.12); color: #6ee7b7; border-color: rgba(16,185,129,.25); }
        .dark .abc-note.off { background: rgba(239,68,68,.12); color: #fca5a5; border-color: rgba(239,68,68,.25); }
        .abc-note a { color: inherit; text-decoration: underline; }

        .abc-cards { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
        @media (min-width: 1024px) { .abc-cards { grid-template-columns: repeat(4, 1fr); } }
        .abc-card { border-radius: 1rem; padding: 1.25rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); }
        .dark .abc-card { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .abc-card-label { margin: 0; font-size: .8125rem; font-weight: 500; color: #6b7280; }
        .abc-card-value { margin: .4rem 0 0; font-size: 1.75rem; line-height: 1.1; font-weight: 800; letter-spacing: -.02em; }
        .abc-card-value.warn { color: #b45309; }
        .abc-card-value.good { color: #047857; }
        .abc-card-sub { margin: .25rem 0 0; font-size: .75rem; color: #9ca3af; }

        .abc-panel { border-radius: 1rem; overflow: hidden; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); }
        .dark .abc-panel { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .abc-panel-head { padding: 1rem 1.25rem; border-bottom: 1px solid rgba(0,0,0,.05); display: flex; align-items: baseline; justify-content: space-between; gap: .5rem; }
        .dark .abc-panel-head { border-color: rgba(255,255,255,.08); }
        .abc-panel-head h2 { margin: 0; font-size: 1rem; font-weight: 600; }
        .abc-panel-head span { font-size: .75rem; color: #9ca3af; }

        .abc-flow { display: flex; flex-wrap: wrap; gap: .5rem; padding: 1rem 1.25rem; }
        .abc-step { display: flex; align-items: center; gap: .5rem; }
        .abc-chip { border-radius: 999px; padding: .3rem .75rem; font-size: .8rem; font-weight: 700; background: var(--brand, #0f766e); color: #fff; }
        .abc-arrow { color: #cbd5e1; font-weight: 700; }

        .abc-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .abc-table th { text-align: left; padding: .6rem 1.25rem; font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; color: #9ca3af; border-bottom: 1px solid rgba(0,0,0,.06); }
        .abc-table td { padding: .65rem 1.25rem; border-top: 1px solid rgba(0,0,0,.04); }
        .dark .abc-table td { border-color: rgba(255,255,255,.05); }
        .abc-badge { display: inline-block; border-radius: 999px; padding: .1rem .5rem; font-size: .7rem; font-weight: 700; }
        .abc-badge.guest { background: #fef3c7; color: #92400e; }
        .abc-badge.member { background: #e0e7ff; color: #3730a3; }
        .dark .abc-badge.guest { background: rgba(245,158,11,.15); color: #fcd34d; }
        .dark .abc-badge.member { background: rgba(99,102,241,.15); color: #a5b4fc; }
        .abc-stage { font-weight: 700; }
        .abc-empty { padding: 2.5rem 1.25rem; text-align: center; color: #9ca3af; }
    </style>

    <div class="abc">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($enabled): ?>
            <div class="abc-note on">
                ✓ Abandoned cart recovery is ON — reminders run automatically every 15 minutes via the scheduler.
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($settingsUrl): ?> <a href="<?php echo e($settingsUrl); ?>">Edit the flow →</a> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="abc-note off">
                ✕ Abandoned cart recovery is OFF. No reminders are being sent.
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($settingsUrl): ?> <a href="<?php echo e($settingsUrl); ?>">Turn it on →</a> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="abc-cards">
            <div class="abc-card">
                <p class="abc-card-label">Open abandoned carts</p>
                <p class="abc-card-value warn"><?php echo e($stats['open']); ?></p>
                <p class="abc-card-sub"><?php echo e($stats['value_at_risk']); ?> at risk</p>
            </div>
            <div class="abc-card">
                <p class="abc-card-label">Reminders sent</p>
                <p class="abc-card-value"><?php echo e($stats['reminders_sent']); ?></p>
                <p class="abc-card-sub">to <?php echo e($stats['emailed']); ?> shopper(s)</p>
            </div>
            <div class="abc-card">
                <p class="abc-card-label">Recovered orders</p>
                <p class="abc-card-value good"><?php echo e($stats['recovered']); ?></p>
                <p class="abc-card-sub"><?php echo e($stats['recovery_rate']); ?>% recovery rate</p>
            </div>
            <div class="abc-card">
                <p class="abc-card-label">Recovered revenue</p>
                <p class="abc-card-value good"><?php echo e($stats['recovered_revenue']); ?></p>
                <p class="abc-card-sub">from recovered carts</p>
            </div>
        </div>

        <div class="abc-panel">
            <div class="abc-panel-head"><h2>Reminder sequence</h2><span><?php echo e(count($stages)); ?> stage(s)</span></div>
            <div class="abc-flow">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $stages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $stage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="abc-step">
                        <span class="abc-chip"><?php echo e($stage['label']); ?></span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($i < count($stages) - 1): ?><span class="abc-arrow">→</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <span style="color:#9ca3af; font-size:.85rem;">No stages configured.</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($stages)): ?><div class="abc-step"><span class="abc-arrow">→</span><span style="color:#9ca3af; font-size:.8rem; font-weight:600;">exit</span></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <div class="abc-panel">
            <div class="abc-panel-head"><h2>Open abandoned carts</h2><span>Newest first · up to 100</span></div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($rows)): ?>
                <table class="abc-table">
                    <thead>
                        <tr>
                            <th>Customer</th><th>Items</th><th>Value</th><th>Stage</th><th>Idle</th><th>Last reminder</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr>
                                <td>
                                    <?php echo e($row['email'] ?? '—'); ?>

                                    <span class="abc-badge <?php echo e($row['guest'] ? 'guest' : 'member'); ?>"><?php echo e($row['guest'] ? 'Guest' : 'Member'); ?></span>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['name'] !== '—'): ?><div style="color:#9ca3af; font-size:.75rem;"><?php echo e($row['name']); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td><?php echo e($row['items']); ?></td>
                                <td><?php echo e($row['value']); ?></td>
                                <td class="abc-stage"><?php echo e($row['stage']); ?> / <?php echo e($row['stage_count']); ?></td>
                                <td><?php echo e($row['last_active']); ?></td>
                                <td><?php echo e($row['last_reminder']); ?></td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="abc-empty">No abandoned carts right now. 🎉</div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/filament/pages/abandoned-carts.blade.php ENDPATH**/ ?>