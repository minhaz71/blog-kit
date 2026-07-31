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
        .spf { display: flex; flex-direction: column; gap: 1.5rem; font-variant-numeric: tabular-nums; }
        .spf-cards { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        .spf-card { border-radius: 1rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); padding: 1.25rem; }
        .dark .spf-card { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .spf-label { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; }
        .spf-value { margin-top: .25rem; font-size: 1.9rem; font-weight: 800; color: #111827; }
        .dark .spf-value { color: #f9fafb; }

        .spf-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; border-radius: 1rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); padding: 1rem 1.5rem; }
        .dark .spf-toolbar { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .spf-toolbar p { font-size: .85rem; color: #6b7280; }

        .spf-setup { border: 1px dashed #99f6e4; border-radius: 1rem; background: #f0fdfa; padding: 1.5rem; font-size: .9rem; color: #134e4a; line-height: 1.7; }
        .dark .spf-setup { background: rgba(99,102,241,.08); border-color: rgba(99,102,241,.35); color: #5eead4; }
        .spf-setup ol { margin: .5rem 0 0 1.2rem; }

        .spf-panel { border-radius: 1rem; overflow: hidden; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); }
        .dark .spf-panel { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .spf-table-wrap { overflow-x: auto; }
        .spf-table { width: 100%; font-size: .85rem; border-collapse: collapse; min-width: 860px; }
        .spf-table thead tr { background: #f9fafb; text-align: left; }
        .dark .spf-table thead tr { background: rgba(255,255,255,.04); }
        .spf-table th { padding: .65rem 1.25rem; font-size: .66rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; white-space: nowrap; }
        .spf-table th.r, .spf-table td.r { text-align: right; }
        .spf-table td { padding: .65rem 1.25rem; border-top: 1px solid rgba(0,0,0,.04); color: #374151; }
        .dark .spf-table td { border-color: rgba(255,255,255,.05); color: #d1d5db; }
        .spf-table a { color: #0f766e; text-decoration: none; font-weight: 500; }
        .spf-table a:hover { text-decoration: underline; }

        .spf-badge { display: inline-block; border-radius: 999px; padding: .15rem .6rem; font-size: .68rem; font-weight: 700; }
        .spf-pass { background: #dcfce7; color: #15803d; }
        .spf-fail { background: #fee2e2; color: #b91c1c; }
        .spf-warn { background: #fef3c7; color: #b45309; }
        .spf-unknown { background: #f3f4f6; color: #9ca3af; }
        .dark .spf-pass { background: rgba(34,197,94,.15); color: #4ade80; }
        .dark .spf-fail { background: rgba(239,68,68,.15); color: #f87171; }
        .dark .spf-warn { background: rgba(245,158,11,.15); color: #fbbf24; }
        .spf-empty { padding: 3rem; text-align: center; color: #9ca3af; }
    </style>

    <div class="spf">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $configured): ?>
            <div class="spf-setup">
                <strong>Connect Google Search Console (one-time setup):</strong>
                <ol>
                    <li>Google Cloud Console → create a <em>service account</em>; enable the <em>Search Console API</em> and <em>Analytics Data API</em>.</li>
                    <li>Download its JSON key and paste it in <em>SEO settings → Integrations → Google service account JSON</em>.</li>
                    <li>Add the service account's email as a user on your Search Console property (Full access) and, optionally, your GA4 property (Viewer).</li>
                    <li>Fill in the Search Console property (e.g. <code>sc-domain:tereahub.ae</code>) and GA4 property ID, save, then hit Sync now.</li>
                </ol>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="spf-cards">
            <div class="spf-card">
                <p class="spf-label">Clicks (<?php echo e($periodDays); ?>d)</p>
                <p class="spf-value"><?php echo e(number_format($totals->clicks)); ?></p>
            </div>
            <div class="spf-card">
                <p class="spf-label">Impressions</p>
                <p class="spf-value"><?php echo e(number_format($totals->impressions)); ?></p>
            </div>
            <div class="spf-card">
                <p class="spf-label">Indexed (checked)</p>
                <p class="spf-value" style="color:#16a34a"><?php echo e($totals->indexed); ?></p>
            </div>
            <div class="spf-card">
                <p class="spf-label">Not indexed</p>
                <p class="spf-value" style="color: <?php echo e($totals->notIndexed > 0 ? '#dc2626' : '#16a34a'); ?>"><?php echo e($totals->notIndexed); ?></p>
            </div>
        </div>

        <div class="spf-toolbar">
            <p>
                Page-level Google Search data<?php echo e($fetchedAt ? ', synced '.\Carbon\Carbon::parse($fetchedAt)->diffForHumans() : ''); ?> ·
                refreshes daily via cron · index status covers the top pages by impressions (quota-aware).
            </p>
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

                Sync now
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

        <div class="spf-panel">
            <div class="spf-table-wrap">
                <table class="spf-table">
                    <thead>
                        <tr>
                            <th>Page</th>
                            <th class="r">Clicks</th>
                            <th class="r">Impressions</th>
                            <th class="r">CTR</th>
                            <th class="r">Position</th>
                            <th class="r">Organic sessions (GA4)</th>
                            <th>Index status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php $status = $statuses[$row->url] ?? null; ?>
                            <tr>
                                <td><a href="<?php echo e($row->url); ?>" target="_blank"><?php echo e(\Illuminate\Support\Str::limit(parse_url($row->url, PHP_URL_PATH) ?: '/', 60)); ?></a></td>
                                <td class="r" style="font-weight:700"><?php echo e(number_format($row->clicks)); ?></td>
                                <td class="r"><?php echo e(number_format($row->impressions)); ?></td>
                                <td class="r"><?php echo e($row->ctr); ?>%</td>
                                <td class="r"><?php echo e($row->position); ?></td>
                                <td class="r"><?php echo e($row->organic_sessions !== null ? number_format($row->organic_sessions) : '—'); ?></td>
                                <td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($status): ?>
                                        <span class="spf-badge <?php echo e($status->verdict === 'PASS' ? 'spf-pass' : ($status->verdict === 'FAIL' ? 'spf-fail' : 'spf-warn')); ?>"
                                              title="<?php echo e($status->coverage); ?><?php echo e($status->last_crawl_at ? ' · last crawl '.\Carbon\Carbon::parse($status->last_crawl_at)->diffForHumans() : ''); ?>">
                                            <?php echo e($status->verdict === 'PASS' ? 'Indexed' : ($status->coverage ?: $status->verdict)); ?>

                                        </span>
                                    <?php else: ?>
                                        <span class="spf-badge spf-unknown">not checked</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr><td colspan="7" class="spf-empty"><?php echo e($configured ? 'No data yet — click Sync now.' : 'Connect Search Console above to see which pages get clicks, rankings and index status.'); ?></td></tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
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
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/filament/pages/search-performance.blade.php ENDPATH**/ ?>