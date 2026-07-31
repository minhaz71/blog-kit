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
        .sc { display: flex; flex-direction: column; gap: 1.5rem; font-variant-numeric: tabular-nums; }
        .sc-top { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 1024px) { .sc-top { grid-template-columns: 280px 1fr; } }

        .sc-panel { border-radius: 1rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); overflow: hidden; }
        .dark .sc-panel { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .sc-panel-head { padding: .875rem 1.25rem; border-bottom: 1px solid rgba(0,0,0,.05); font-weight: 600; font-size: .95rem; }
        .dark .sc-panel-head { border-color: rgba(255,255,255,.08); }

        /* Score ring */
        .sc-score { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.5rem; text-align: center; }
        .sc-ring { position: relative; width: 150px; height: 150px; border-radius: 50%; display: grid; place-items: center; }
        .sc-ring::before { content: ''; position: absolute; inset: 12px; border-radius: 50%; background: #fff; }
        .dark .sc-ring::before { background: #1a1a1e; }
        .sc-ring-inner { position: relative; text-align: center; }
        .sc-ring-num { font-size: 2.75rem; font-weight: 800; line-height: 1; }
        .sc-ring-grade { font-size: .8rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #6b7280; margin-top: .25rem; }
        .sc-score-sub { margin-top: 1rem; font-size: .8rem; color: #6b7280; }

        /* Stat cards */
        .sc-cards { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
        @media (min-width: 640px) { .sc-cards { grid-template-columns: repeat(5, 1fr); } }
        .sc-card { border-radius: 1rem; padding: 1.1rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); }
        .dark .sc-card { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .sc-card-num { font-size: 1.75rem; font-weight: 800; letter-spacing: -.02em; }
        .sc-card-label { font-size: .72rem; color: #6b7280; margin-top: .2rem; text-transform: uppercase; letter-spacing: .04em; }
        .dark .sc-card-label { color: #9ca3af; }

        .sc-grid2 { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
        @media (min-width: 1024px) { .sc-grid2 { grid-template-columns: 1fr 1fr; } }

        /* Audit checklist */
        .sc-check { display: flex; align-items: flex-start; gap: .625rem; padding: .625rem 1.25rem; border-top: 1px solid rgba(0,0,0,.04); }
        .dark .sc-check { border-color: rgba(255,255,255,.05); }
        .sc-check:first-child { border-top: 0; }
        .sc-tick { flex-shrink: 0; width: 1.1rem; height: 1.1rem; border-radius: 50%; display: grid; place-items: center; font-size: .7rem; font-weight: 700; color: #fff; margin-top: .1rem; }
        .sc-tick.pass { background: #10b981; }
        .sc-tick.fail { background: #ef4444; }
        .sc-check-body { min-width: 0; }
        .sc-check-label { font-size: .875rem; font-weight: 500; }
        .sc-check-fix { font-size: .78rem; color: #b45309; margin-top: .15rem; }
        .dark .sc-check-fix { color: #fcd34d; }
        .sc-sev { font-size: .6rem; font-weight: 700; text-transform: uppercase; padding: .05rem .35rem; border-radius: .25rem; margin-left: .4rem; vertical-align: middle; }
        .sc-sev.critical { background: #fee2e2; color: #b91c1c; }
        .sc-sev.high { background: #ffedd5; color: #c2410c; }
        .sc-sev.medium { background: #fef9c3; color: #a16207; }

        .sc-row { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .5rem 1.25rem; border-top: 1px solid rgba(0,0,0,.04); font-size: .875rem; }
        .dark .sc-row { border-color: rgba(255,255,255,.05); }
        .sc-row:first-child { border-top: 0; }
        .sc-mono { font-family: ui-monospace, monospace; font-size: .82rem; }
        .sc-badge { font-size: .7rem; font-weight: 700; padding: .1rem .5rem; border-radius: 9999px; background: #f3f4f6; color: #4b5563; }
        .dark .sc-badge { background: rgba(255,255,255,.08); color: #d1d5db; }

        .sc-event { display: flex; gap: .625rem; padding: .5rem 1.25rem; border-top: 1px solid rgba(0,0,0,.04); font-size: .85rem; }
        .dark .sc-event { border-color: rgba(255,255,255,.05); }
        .sc-dot { flex-shrink: 0; width: .55rem; height: .55rem; border-radius: 50%; margin-top: .4rem; }
        .sc-dot.critical, .sc-dot.high { background: #ef4444; }
        .sc-dot.warning { background: #f59e0b; }
        .sc-dot.info { background: #6b7280; }
        .sc-empty { padding: 1.5rem 1.25rem; text-align: center; color: #6b7280; font-size: .85rem; }
        .sc-time { color: #9ca3af; font-size: .75rem; white-space: nowrap; }
    </style>

    <?php
        $score = $audit['score'];
        $ringColor = $score >= 80 ? '#10b981' : ($score >= 50 ? '#f59e0b' : '#ef4444');
        $failed = collect($audit['checks'])->reject(fn ($c) => $c['passed'])->sortBy(fn ($c) => array_search($c['severity'], ['critical','high','medium','info']));
        $passed = collect($audit['checks'])->filter(fn ($c) => $c['passed']);
    ?>

    <div class="sc" wire:poll.30s>
        
        <div class="sc-top">
            <div class="sc-panel sc-score">
                <div class="sc-ring" style="background: conic-gradient(<?php echo e($ringColor); ?> <?php echo e($score * 3.6); ?>deg, rgba(0,0,0,.08) 0deg);">
                    <div class="sc-ring-inner">
                        <div class="sc-ring-num" style="color: <?php echo e($ringColor); ?>"><?php echo e($score); ?></div>
                        <div class="sc-ring-grade">Grade <?php echo e($audit['grade']); ?></div>
                    </div>
                </div>
                <div class="sc-score-sub"><?php echo e($audit['passed']); ?>/<?php echo e($audit['total']); ?> hardening checks passed</div>
            </div>

            <div class="sc-cards">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                    ['n' => $stats['attacks_24h'], 'l' => 'Attacks blocked (24h)', 'c' => '#ef4444'],
                    ['n' => $stats['attacks_7d'], 'l' => 'Blocked (7 days)', 'c' => '#f97316'],
                    ['n' => number_format($stats['threat_ips']), 'l' => 'Threat IPs in blocklist', 'c' => '#0d9488'],
                    ['n' => $stats['active_bans'], 'l' => 'Active IP bans', 'c' => '#8b5cf6'],
                    ['n' => $stats['failed_logins_24h'], 'l' => 'Failed logins (24h)', 'c' => '#0891b2'],
                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="sc-card">
                        <div class="sc-card-num" style="color: <?php echo e($card['c']); ?>"><?php echo e($card['n']); ?></div>
                        <div class="sc-card-label"><?php echo e($card['l']); ?></div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </div>

        <div class="sc-grid2">
            
            <div class="sc-panel">
                <div class="sc-panel-head">Hardening audit — action items first</div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $failed; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="sc-check">
                        <span class="sc-tick fail">✕</span>
                        <div class="sc-check-body">
                            <div class="sc-check-label"><?php echo e($c['label']); ?><span class="sc-sev <?php echo e($c['severity']); ?>"><?php echo e($c['severity']); ?></span></div>
                            <div class="sc-check-fix"><?php echo e($c['fix']); ?></div>
                        </div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="sc-empty">✅ Every hardening check passed. Excellent.</div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $passed; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="sc-check">
                        <span class="sc-tick pass">✓</span>
                        <div class="sc-check-body"><div class="sc-check-label" style="color:#6b7280"><?php echo e($c['label']); ?></div></div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>

            <div style="display:flex; flex-direction:column; gap:1.5rem;">
                
                <div class="sc-panel">
                    <div class="sc-panel-head">Top attacking IPs (7 days)</div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $topIps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="sc-row"><span class="sc-mono"><?php echo e($row->ip_address); ?></span><span class="sc-badge"><?php echo e(number_format($row->hits)); ?> hits</span></div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <div class="sc-empty">No blocked traffic in the last 7 days.</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                
                <div class="sc-panel">
                    <div class="sc-panel-head">Attack types (7 days)</div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $topRules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="sc-row"><span><?php echo e(str_replace('_', ' ', $row->rule)); ?></span><span class="sc-badge"><?php echo e(number_format($row->hits)); ?></span></div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <div class="sc-empty">Nothing blocked recently.</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="sc-grid2">
            
            <div class="sc-panel">
                <div class="sc-panel-head">Recent security events</div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="sc-event">
                        <span class="sc-dot <?php echo e($e->severity); ?>"></span>
                        <div style="min-width:0; flex:1;">
                            <div style="font-weight:500;"><?php echo e($e->title); ?></div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($e->description): ?><div style="color:#6b7280; font-size:.78rem; margin-top:.1rem;"><?php echo e(\Illuminate\Support\Str::limit($e->description, 120)); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <span class="sc-time"><?php echo e($e->created_at?->diffForHumans()); ?></span>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="sc-empty">No security events recorded yet — that's good.</div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div style="display:flex; flex-direction:column; gap:1.5rem;">
                
                <div class="sc-panel">
                    <div class="sc-panel-head">Real-time threat blocklist</div>
                    <div class="sc-row"><span>Threat IPs loaded</span><span class="sc-badge"><?php echo e(number_format($stats['threat_ips'])); ?></span></div>
                    <div class="sc-row"><span>Last updated</span><span class="sc-time"><?php echo e($threatUpdatedAt ? \Illuminate\Support\Carbon::parse($threatUpdatedAt)->diffForHumans() : 'never — run “Update threat blocklist”'); ?></span></div>
                </div>

                
                <div class="sc-panel">
                    <div class="sc-panel-head">Dependency vulnerabilities</div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dependencies && $dependencies['ran']): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dependencies['advisories'] === 0): ?>
                            <div class="sc-row" style="color:#059669;"><span>✅ No known CVEs in dependencies</span><span class="sc-time"><?php echo e(\Illuminate\Support\Carbon::parse($dependencies['checked_at'])->diffForHumans()); ?></span></div>
                        <?php else: ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $dependencies['packages']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <div class="sc-row"><span class="sc-mono"><?php echo e($p['package']); ?></span><span class="sc-sev high"><?php echo e($p['severity']); ?></span></div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php else: ?>
                        <div class="sc-empty">Not scanned yet — click “Scan dependencies” above.</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
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
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/filament/pages/security-center.blade.php ENDPATH**/ ?>