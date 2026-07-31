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
        .ilr { display: flex; flex-direction: column; gap: 1.5rem; font-variant-numeric: tabular-nums; }

        .ilr-cards { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
        .ilr-card { position: relative; overflow: hidden; border-radius: 1rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); padding: 1.25rem; }
        .dark .ilr-card { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .ilr-card-bar { position: absolute; inset: 0 0 auto 0; height: 4px; }
        .ilr-card-label { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; }
        .ilr-card-value { margin-top: .25rem; font-size: 1.9rem; font-weight: 800; letter-spacing: -.02em; color: #111827; }
        .dark .ilr-card-value { color: #f9fafb; }
        .ilr-card-sub { margin-top: .15rem; font-size: .72rem; color: #9ca3af; }

        .ilr-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; border-radius: 1rem; background: #fff; border: 1px solid rgba(0,0,0,.06); padding: .9rem 1.25rem; box-shadow: 0 4px 16px rgba(15,23,42,.05); }
        .dark .ilr-toolbar { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .ilr-search { position: relative; flex: 1 1 260px; max-width: 24rem; }
        .ilr-search svg { position: absolute; left: .75rem; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: #9ca3af; pointer-events: none; }
        .ilr-search input { width: 100%; border-radius: .6rem; border: 1px solid #d1d5db; padding: .5rem .75rem .5rem 2.25rem; font-size: .875rem; background: #fff; }
        .dark .ilr-search input { background: rgba(255,255,255,.05); border-color: rgba(255,255,255,.12); color: #f9fafb; }
        .ilr-meta { font-size: .72rem; color: #9ca3af; }

        .ilr-panel { border-radius: 1rem; overflow: hidden; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); }
        .dark .ilr-panel { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .ilr-panel-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: 1rem 1.5rem; border-bottom: 1px solid rgba(0,0,0,.05); }
        .dark .ilr-panel-head { border-color: rgba(255,255,255,.08); }
        .ilr-panel-title { font-weight: 700; color: #111827; }
        .dark .ilr-panel-title { color: #f9fafb; }
        .ilr-panel-title small { font-weight: 400; color: #9ca3af; font-size: .8rem; margin-left: .35rem; }

        .ilr-table-wrap { overflow-x: auto; }
        .ilr-table { width: 100%; font-size: .875rem; border-collapse: collapse; }
        .ilr-table thead tr { background: #f9fafb; text-align: left; }
        .dark .ilr-table thead tr { background: rgba(255,255,255,.04); }
        .ilr-table th { padding: .65rem 1.5rem; font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; white-space: nowrap; }
        .ilr-table th.ilr-center, .ilr-table td.ilr-center { text-align: center; }
        .ilr-table th.ilr-right, .ilr-table td.ilr-right { text-align: right; }
        .ilr-table td { padding: .7rem 1.5rem; border-top: 1px solid rgba(0,0,0,.04); color: #374151; }
        .dark .ilr-table td { border-color: rgba(255,255,255,.05); color: #d1d5db; }
        .ilr-table tbody tr:hover { background: rgba(0,0,0,.02); }
        .dark .ilr-table tbody tr:hover { background: rgba(255,255,255,.03); }
        .ilr-table tbody tr.ilr-active { background: #f0fdfa; }
        .dark .ilr-table tbody tr.ilr-active { background: rgba(99,102,241,.12); }

        .ilr-name { background: none; border: 0; padding: 0; cursor: pointer; font-weight: 600; color: #1f2937; font-size: .875rem; text-align: left; }
        .ilr-name:hover { color: #0f766e; }
        .dark .ilr-name { color: #e5e7eb; }

        .ilr-badge { display: inline-block; min-width: 2.4rem; text-align: center; border-radius: 999px; padding: .15rem .6rem; font-size: .72rem; font-weight: 700; }
        .ilr-badge-red { background: #fee2e2; color: #b91c1c; }
        .ilr-badge-amber { background: #fef3c7; color: #b45309; }
        .ilr-badge-green { background: #dcfce7; color: #15803d; }
        .dark .ilr-badge-red { background: rgba(239,68,68,.15); color: #f87171; }
        .dark .ilr-badge-amber { background: rgba(245,158,11,.15); color: #fbbf24; }
        .dark .ilr-badge-green { background: rgba(34,197,94,.15); color: #4ade80; }

        .ilr-pill { display: inline-block; margin-left: .5rem; border-radius: 999px; background: #fee2e2; color: #b91c1c; font-size: .58rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; padding: .12rem .5rem; vertical-align: middle; }
        .dark .ilr-pill { background: rgba(239,68,68,.15); color: #f87171; }

        .ilr-actions { font-size: .75rem; white-space: nowrap; }
        .ilr-actions a, .ilr-actions button { background: none; border: 0; padding: 0; cursor: pointer; color: #6b7280; font-size: .75rem; text-decoration: none; }
        .ilr-actions .ilr-primary { color: #0f766e; font-weight: 600; }
        .ilr-actions a:hover, .ilr-actions button:hover { text-decoration: underline; }
        .ilr-actions .ilr-dot { color: #d1d5db; margin: 0 .35rem; }

        .ilr-detail { border: 2px solid #5eead4; border-radius: 1rem; overflow: hidden; background: #fff; box-shadow: 0 12px 32px rgba(79,70,229,.12); }
        .dark .ilr-detail { background: #111827; border-color: rgba(129,140,248,.4); }
        .ilr-detail-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: 1rem 1.5rem; border-bottom: 1px solid rgba(0,0,0,.05); }
        .dark .ilr-detail-head { border-color: rgba(255,255,255,.08); }
        .ilr-kind { display: inline-block; border-radius: .35rem; background: #f3f4f6; color: #6b7280; font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; padding: .2rem .5rem; }
        .dark .ilr-kind { background: rgba(255,255,255,.08); color: #9ca3af; }
        .ilr-detail-title { margin-left: .5rem; font-size: 1.05rem; font-weight: 800; color: #0f766e; text-decoration: none; }
        .ilr-detail-title:hover { text-decoration: underline; }
        .ilr-close { background: none; border: 0; cursor: pointer; border-radius: .5rem; padding: .4rem; color: #9ca3af; line-height: 0; }
        .ilr-close:hover { background: rgba(0,0,0,.05); color: #4b5563; }
        .ilr-close svg { width: 18px; height: 18px; }

        .ilr-detail-grid { display: grid; }
        @media (min-width: 768px) { .ilr-detail-grid { grid-template-columns: 1fr 1fr; } .ilr-detail-grid > div + div { border-left: 1px solid rgba(0,0,0,.05); } .dark .ilr-detail-grid > div + div { border-color: rgba(255,255,255,.08); } }
        .ilr-detail-col { padding: 1.25rem 1.5rem; }
        .ilr-col-title { display: flex; align-items: center; gap: .5rem; font-size: .85rem; font-weight: 700; color: #374151; }
        .dark .ilr-col-title { color: #e5e7eb; }
        .ilr-col-icon { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 999px; font-size: .8rem; }
        .ilr-col-icon.in { background: #dcfce7; color: #16a34a; }
        .ilr-col-icon.out { background: #dbeafe; color: #2563eb; }
        .ilr-col-sub { margin-top: .15rem; font-size: .72rem; color: #9ca3af; }
        .ilr-links { margin-top: .75rem; display: flex; flex-direction: column; gap: .5rem; list-style: none; padding: 0; }
        .ilr-link { border-radius: .6rem; background: #f9fafb; padding: .55rem .8rem; font-size: .85rem; }
        .dark .ilr-link { background: rgba(255,255,255,.04); }
        .ilr-link a { color: #0f766e; font-weight: 600; text-decoration: none; }
        .ilr-link a:hover { text-decoration: underline; }
        .ilr-link .ilr-kind { margin-left: .4rem; }
        .ilr-anchor { margin-top: .15rem; font-size: .72rem; color: #6b7280; }
        .ilr-empty-in { border: 1px dashed #fca5a5; background: #fef2f2; color: #dc2626; border-radius: .6rem; padding: .8rem; font-size: .85rem; }
        .dark .ilr-empty-in { background: rgba(239,68,68,.08); border-color: rgba(239,68,68,.4); color: #f87171; }
        .ilr-empty-out { border: 1px dashed #e5e7eb; color: #9ca3af; border-radius: .6rem; padding: .8rem; font-size: .85rem; }
        .dark .ilr-empty-out { border-color: rgba(255,255,255,.12); }

        .ilr-note { font-size: .72rem; color: #9ca3af; }
        .ilr-empty-row { padding: 2.5rem 1.5rem; text-align: center; color: #9ca3af; }
    </style>

    <?php
        $badge = fn (int $n): string => match (true) {
            $n === 0 => 'ilr-badge ilr-badge-red',
            $n <= 2 => 'ilr-badge ilr-badge-amber',
            default => 'ilr-badge ilr-badge-green',
        };
    ?>

    <div class="ilr">
        
        <div class="ilr-cards">
            <div class="ilr-card">
                <div class="ilr-card-bar" style="background: linear-gradient(90deg,#0d9488,#8b5cf6)"></div>
                <p class="ilr-card-label">Internal links</p>
                <p class="ilr-card-value"><?php echo e(number_format($totalLinks)); ?></p>
                <p class="ilr-card-sub">from product, post &amp; category content</p>
            </div>
            <div class="ilr-card">
                <div class="ilr-card-bar" style="background: linear-gradient(90deg,#ef4444,#f43f5e)"></div>
                <p class="ilr-card-label">Orphan pages</p>
                <p class="ilr-card-value" style="color: <?php echo e($distribution['orphans'] > 0 ? '#dc2626' : '#16a34a'); ?>"><?php echo e($distribution['orphans']); ?></p>
                <p class="ilr-card-sub">no internal links point to them</p>
            </div>
            <div class="ilr-card">
                <div class="ilr-card-bar" style="background: linear-gradient(90deg,#f59e0b,#f97316)"></div>
                <p class="ilr-card-label">Weak (1–2 links)</p>
                <p class="ilr-card-value" style="color:#d97706"><?php echo e($distribution['weak']); ?></p>
                <p class="ilr-card-sub">could use more links</p>
            </div>
            <div class="ilr-card">
                <div class="ilr-card-bar" style="background: linear-gradient(90deg,#22c55e,#10b981)"></div>
                <p class="ilr-card-label">Healthy (3+ links)</p>
                <p class="ilr-card-value" style="color:#16a34a"><?php echo e($distribution['healthy']); ?></p>
                <p class="ilr-card-sub">well linked internally</p>
            </div>
        </div>

        
        <div class="ilr-toolbar">
            <div class="ilr-search">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.2-5.2M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg>
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search products, posts & categories…">
            </div>
            <div style="display:flex; align-items:center; gap:.9rem">
                <span class="ilr-meta">
                    Last sync: <?php echo e($scannedAt ? \Carbon\Carbon::parse($scannedAt)->diffForHumans() : 'never'); ?>

                    · weekly auto + on every content edit
                </span>
                <?php if (isset($component)) { $__componentOriginal6330f08526bbb3ce2a0da37da512a11f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6330f08526bbb3ce2a0da37da512a11f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament::components.button.index','data' => ['wire:click' => 'syncNow','wire:loading.attr' => 'disabled','icon' => 'heroicon-o-arrow-path']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:click' => 'syncNow','wire:loading.attr' => 'disabled','icon' => 'heroicon-o-arrow-path']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                    <span wire:loading.remove wire:target="syncNow">Sync now</span>
                    <span wire:loading wire:target="syncNow">Scanning…</span>
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6330f08526bbb3ce2a0da37da512a11f)): ?>
<?php $attributes = $__attributesOriginal6330f08526bbb3ce2a0da37da512a11f; ?>
<?php unset($__attributesOriginal6330f08526bbb3ce2a0da37da512a11f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6330f08526bbb3ce2a0da37da512a11f)): ?>
<?php $component = $__componentOriginal6330f08526bbb3ce2a0da37da512a11f; ?>
<?php unset($__componentOriginal6330f08526bbb3ce2a0da37da512a11f); ?>
<?php endif; ?>
            </div>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($detailData): ?>
            <div class="ilr-detail">
                <div class="ilr-detail-head">
                    <div>
                        <span class="ilr-kind"><?php echo e($detailData->kind); ?></span>
                        <a href="<?php echo e($detailData->url); ?>" target="_blank" class="ilr-detail-title"><?php echo e($detailData->name); ?> ↗</a>
                    </div>
                    <button type="button" wire:click="closeDetail" class="ilr-close" title="Close">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="ilr-detail-grid">
                    <div class="ilr-detail-col">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem">
                            <h4 class="ilr-col-title"><span class="ilr-col-icon in">↓</span> Linked FROM (<?php echo e($detailData->inbound->count()); ?>)</h4>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($detailData->inbound->isNotEmpty()): ?>
                                <button type="button"
                                        wire:click="unlinkAllInbound"
                                        wire:loading.attr="disabled"
                                        wire:confirm="Remove ALL inbound internal links to this <?php echo e(strtolower($detailData->kind)); ?>? The anchor text stays; only the links are removed."
                                        style="font-size:.72rem;font-weight:600;color:#b91c1c;background:#fef2f2;border:1px solid #fecaca;border-radius:.4rem;padding:.2rem .5rem;cursor:pointer">
                                    Unlink all
                                </button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <p class="ilr-col-sub">pages whose content links to this <?php echo e(strtolower($detailData->kind)); ?></p>
                        <ul class="ilr-links">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $detailData->inbound; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <li class="ilr-link" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'in-'.e($link->id).''; ?>wire:key="in-<?php echo e($link->id); ?>">
                                    <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem">
                                        <div style="min-width:0">
                                            <a href="<?php echo e($link->url); ?>" target="_blank"><?php echo e($link->name); ?></a>
                                            <span class="ilr-kind"><?php echo e($link->kind); ?></span>
                                        </div>
                                        <button type="button"
                                                wire:click="unlink(<?php echo e($link->id); ?>)"
                                                wire:loading.attr="disabled"
                                                title="Remove this link (keeps the anchor text)"
                                                style="flex-shrink:0;font-size:.7rem;font-weight:600;color:#b91c1c;background:transparent;border:1px solid #fecaca;border-radius:.35rem;padding:.15rem .45rem;cursor:pointer">
                                            Unlink
                                        </button>
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($link->anchor): ?>
                                        <p class="ilr-anchor">anchor: “<?php echo e($link->anchor); ?>”</p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </li>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <li class="ilr-empty-in">Orphan — nothing links here. Add a mention in a related product's copy or a blog post.</li>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </ul>
                    </div>
                    <div class="ilr-detail-col">
                        <h4 class="ilr-col-title"><span class="ilr-col-icon out">↑</span> Links TO (<?php echo e($detailData->outbound->count()); ?>)</h4>
                        <p class="ilr-col-sub">pages this <?php echo e(strtolower($detailData->kind)); ?>'s content links out to</p>
                        <ul class="ilr-links">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $detailData->outbound; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <li class="ilr-link">
                                    <a href="<?php echo e($link->url); ?>" target="_blank"><?php echo e($link->name); ?></a>
                                    <span class="ilr-kind"><?php echo e($link->kind); ?></span>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($link->anchor): ?>
                                        <p class="ilr-anchor">anchor: “<?php echo e($link->anchor); ?>”</p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </li>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <li class="ilr-empty-out">No outbound internal links in this content.</li>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <div class="ilr-panel">
            <div class="ilr-panel-head">
                <h3 class="ilr-panel-title">Products <small>fewest inbound first</small></h3>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($productTotal > $products->count()): ?>
                    <span class="ilr-note">showing <?php echo e($products->count()); ?> of <?php echo e($productTotal); ?></span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="ilr-table-wrap">
                <table class="ilr-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="ilr-center">Inbound</th>
                            <th class="ilr-center">Outbound</th>
                            <th class="ilr-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr class="<?php echo e($detail === 'Product:'.$p->id ? 'ilr-active' : ''); ?>">
                                <td>
                                    <button type="button" class="ilr-name" wire:click="showDetail('Product', <?php echo e($p->id); ?>)"><?php echo e($p->name); ?></button>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->inbound === 0): ?>
                                        <span class="ilr-pill">orphan</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td class="ilr-center"><span class="<?php echo e($badge($p->inbound)); ?>"><?php echo e($p->inbound); ?></span></td>
                                <td class="ilr-center" style="color:#6b7280"><?php echo e($p->outbound); ?></td>
                                <td class="ilr-right ilr-actions">
                                    <button type="button" class="ilr-primary" wire:click="showDetail('Product', <?php echo e($p->id); ?>)">Details</button>
                                    <span class="ilr-dot">·</span>
                                    <a href="<?php echo e($p->editUrl); ?>">Edit</a>
                                    <span class="ilr-dot">·</span>
                                    <a href="<?php echo e($p->url); ?>" target="_blank">View ↗</a>
                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr><td colspan="4" class="ilr-empty-row">No published products<?php echo e(trim($search) !== '' ? ' matching “'.$search.'”' : ''); ?>.</td></tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($posts->isNotEmpty()): ?>
            <div class="ilr-panel">
                <div class="ilr-panel-head">
                    <h3 class="ilr-panel-title">Blog posts</h3>
                </div>
                <div class="ilr-table-wrap">
                    <table class="ilr-table">
                        <thead>
                            <tr>
                                <th>Post</th>
                                <th class="ilr-center">Inbound</th>
                                <th class="ilr-center">Outbound</th>
                                <th class="ilr-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $posts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr class="<?php echo e($detail === 'Post:'.$p->id ? 'ilr-active' : ''); ?>">
                                    <td>
                                        <button type="button" class="ilr-name" wire:click="showDetail('Post', <?php echo e($p->id); ?>)"><?php echo e($p->name); ?></button>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->inbound === 0): ?>
                                            <span class="ilr-pill">orphan</span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                    <td class="ilr-center"><span class="<?php echo e($badge($p->inbound)); ?>"><?php echo e($p->inbound); ?></span></td>
                                    <td class="ilr-center" style="color:#6b7280"><?php echo e($p->outbound); ?></td>
                                    <td class="ilr-right ilr-actions">
                                        <button type="button" class="ilr-primary" wire:click="showDetail('Post', <?php echo e($p->id); ?>)">Details</button>
                                        <span class="ilr-dot">·</span>
                                        <a href="<?php echo e($p->editUrl); ?>">Edit</a>
                                        <span class="ilr-dot">·</span>
                                        <a href="<?php echo e($p->url); ?>" target="_blank">View ↗</a>
                                    </td>
                                </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($categories->isNotEmpty()): ?>
            <div class="ilr-panel">
                <div class="ilr-panel-head">
                    <h3 class="ilr-panel-title">Product categories <small>fewest inbound first</small></h3>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($categoryTotal > $categories->count()): ?>
                        <span class="ilr-note">showing <?php echo e($categories->count()); ?> of <?php echo e($categoryTotal); ?></span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="ilr-table-wrap">
                    <table class="ilr-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th class="ilr-center">Inbound</th>
                                <th class="ilr-center">Outbound</th>
                                <th class="ilr-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr class="<?php echo e($detail === 'Category:'.$c->id ? 'ilr-active' : ''); ?>">
                                    <td>
                                        <button type="button" class="ilr-name" wire:click="showDetail('Category', <?php echo e($c->id); ?>)"><?php echo e($c->name); ?></button>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c->inbound === 0): ?>
                                            <span class="ilr-pill">orphan</span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                    <td class="ilr-center"><span class="<?php echo e($badge($c->inbound)); ?>"><?php echo e($c->inbound); ?></span></td>
                                    <td class="ilr-center" style="color:#6b7280"><?php echo e($c->outbound); ?></td>
                                    <td class="ilr-right ilr-actions">
                                        <button type="button" class="ilr-primary" wire:click="showDetail('Category', <?php echo e($c->id); ?>)">Details</button>
                                        <span class="ilr-dot">·</span>
                                        <a href="<?php echo e($c->editUrl); ?>">Edit</a>
                                        <span class="ilr-dot">·</span>
                                        <a href="<?php echo e($c->url); ?>" target="_blank">View ↗</a>
                                    </td>
                                </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/filament/pages/internal-links-report.blade.php ENDPATH**/ ?>