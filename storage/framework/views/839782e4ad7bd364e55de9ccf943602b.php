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
        .aiud { display: flex; flex-direction: column; gap: 1.5rem; font-variant-numeric: tabular-nums; }

        .aiud-cards { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 640px) { .aiud-cards { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 1280px) { .aiud-cards { grid-template-columns: repeat(4, 1fr); } }

        .aiud-card {
            position: relative; overflow: hidden; border-radius: 1rem; padding: 1.25rem;
            background: #fff; border: 1px solid rgba(0,0,0,.06);
            box-shadow: 0 4px 16px rgba(15,23,42,.05);
        }
        .dark .aiud-card { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }

        .aiud-card-glow { position: absolute; top: -1.5rem; right: -1.5rem; width: 6rem; height: 6rem; border-radius: 9999px; opacity: .12; pointer-events: none; }

        .aiud-card-head { display: flex; align-items: center; gap: .5rem; }
        .aiud-chip {
            display: flex; align-items: center; justify-content: center;
            width: 2rem; height: 2rem; border-radius: .5rem; color: #fff; flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(15,23,42,.18);
        }
        .aiud-chip svg { width: 1rem; height: 1rem; }

        .aiud-label { font-size: .8125rem; font-weight: 500; color: #6b7280; margin: 0; }
        .dark .aiud-label { color: #9ca3af; }

        .aiud-amount { margin: .75rem 0 0; font-size: 1.875rem; line-height: 1.2; font-weight: 800; letter-spacing: -.02em; }
        .aiud-sub { margin: .125rem 0 0; font-size: .625rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #9ca3af; }
        .aiud-meta { margin: .5rem 0 0; font-size: .75rem; color: #6b7280; }
        .dark .aiud-meta { color: #9ca3af; }

        .aiud-panel { border-radius: 1rem; overflow: hidden; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); }
        .dark .aiud-panel { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }

        .aiud-panel-head { padding: 1rem 1.25rem; border-bottom: 1px solid rgba(0,0,0,.05); }
        .dark .aiud-panel-head { border-color: rgba(255,255,255,.08); }
        .aiud-panel-head h2 { margin: 0; font-size: 1rem; font-weight: 600; }
        .aiud-panel-head p { margin: .125rem 0 0; font-size: .75rem; color: #9ca3af; }
        .aiud-panel-head .aiud-hint { font-size: .75rem; font-weight: 400; color: #9ca3af; margin-left: .5rem; }

        .aiud-scroll { overflow-x: auto; }
        .aiud-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .aiud-table th {
            padding: .75rem 1.25rem; text-align: left; font-size: .6875rem; font-weight: 600;
            letter-spacing: .06em; text-transform: uppercase; color: #6b7280; white-space: nowrap;
        }
        .dark .aiud-table th { color: #9ca3af; }
        .aiud-table td { padding: .75rem 1.25rem; border-top: 1px solid rgba(0,0,0,.05); vertical-align: middle; }
        .dark .aiud-table td { border-color: rgba(255,255,255,.06); }
        .aiud-table tbody tr:hover td { background: rgba(99,102,241,.05); }
        .aiud-right { text-align: right !important; }
        .aiud-cost { font-weight: 700; color: #0f766e; }
        .dark .aiud-cost { color: #5eead4; }
        .aiud-green { color: #059669; }
        .aiud-dim { color: #9ca3af; }
        .aiud-strong { font-weight: 600; }
        .aiud-empty { padding: 2rem 1.25rem !important; text-align: center; color: #6b7280; }

        .aiud-badge {
            display: inline-block; padding: .125rem .625rem; border-radius: 9999px;
            font-size: .75rem; font-weight: 500; background: #f3f4f6; color: #4b5563; white-space: nowrap;
        }
        .dark .aiud-badge { background: rgba(255,255,255,.08); color: #d1d5db; }
        .aiud-badge.write { background: #f0fdfa; color: #115e59; }
        .dark .aiud-badge.write { background: rgba(99,102,241,.15); color: #5eead4; }
        .aiud-badge.review { background: #fffbeb; color: #b45309; }
        .dark .aiud-badge.review { background: rgba(245,158,11,.15); color: #fcd34d; }

        .aiud-share { display: flex; align-items: center; gap: .5rem; min-width: 10rem; }
        .aiud-share-track { width: 7rem; height: .375rem; border-radius: 9999px; background: rgba(0,0,0,.08); overflow: hidden; flex-shrink: 0; }
        .dark .aiud-share-track { background: rgba(255,255,255,.1); }
        .aiud-share-fill { height: 100%; border-radius: 9999px; background: linear-gradient(90deg, #0d9488, #8b5cf6); }
        .aiud-share span { font-size: .75rem; color: #6b7280; }

        .aiud-edit {
            display: inline-flex; align-items: center; gap: .25rem; padding: .25rem .625rem;
            border-radius: .5rem; font-size: .75rem; font-weight: 600; text-decoration: none;
            color: #115e59; background: #f0fdfa; border: 1px solid #99f6e4; transition: background .15s;
            white-space: nowrap;
        }
        .aiud-edit:hover { background: #ccfbf1; }
        .dark .aiud-edit { color: #5eead4; background: rgba(99,102,241,.1); border-color: rgba(99,102,241,.3); }
        .dark .aiud-edit:hover { background: rgba(99,102,241,.2); }
        .aiud-edit svg { width: .75rem; height: .75rem; }

        .aiud-savings { border-color: rgba(16,185,129,.25); }
        .dark .aiud-savings { border-color: rgba(16,185,129,.2); }
        .aiud-savings .aiud-label { color: #047857; }
        .dark .aiud-savings .aiud-label { color: #34d399; }
        .aiud-savings .aiud-amount { color: #059669; }
        .aiud-savings .aiud-sub { color: rgba(5,150,105,.7); }
    </style>

    <div class="aiud" wire:poll.10s>
        
        <div class="aiud-cards">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                ['label' => 'Today', 'data' => $today, 'from' => '#f59e0b', 'to' => '#ef4444', 'icon' => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                ['label' => 'Last 7 days', 'data' => $week, 'from' => '#0d9488', 'to' => '#8b5cf6', 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5'],
                ['label' => 'All time', 'data' => $allTime, 'from' => '#10b981', 'to' => '#14b8a6', 'icon' => 'M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941'],
            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="aiud-card">
                    <div class="aiud-card-glow" style="background: linear-gradient(135deg, <?php echo e($card['from']); ?>, <?php echo e($card['to']); ?>)"></div>
                    <div class="aiud-card-head">
                        <span class="aiud-chip" style="background: linear-gradient(135deg, <?php echo e($card['from']); ?>, <?php echo e($card['to']); ?>)">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="<?php echo e($card['icon']); ?>" />
                            </svg>
                        </span>
                        <p class="aiud-label"><?php echo e($card['label']); ?></p>
                    </div>
                    <p class="aiud-amount" style="color: <?php echo e($card['from']); ?>">$<?php echo e(number_format((float) $card['data']->cost, 4)); ?></p>
                    <p class="aiud-sub">USD total cost</p>
                    <p class="aiud-meta">
                        <?php echo e(number_format((int) $card['data']->requests)); ?> requests ·
                        <?php echo e(number_format((int) $card['data']->input)); ?> in / <?php echo e(number_format((int) $card['data']->output)); ?> out tokens
                    </p>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

            <div class="aiud-card aiud-savings">
                <div class="aiud-card-glow" style="background: linear-gradient(135deg, #10b981, #14b8a6)"></div>
                <div class="aiud-card-head">
                    <span class="aiud-chip" style="background: linear-gradient(135deg, #10b981, #14b8a6)">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </span>
                    <p class="aiud-label">Saved by prompt caching</p>
                </div>
                <p class="aiud-amount">$<?php echo e(number_format($cacheSavings, 4)); ?></p>
                <p class="aiud-sub">USD saved</p>
                <p class="aiud-meta"><?php echo e(number_format((int) $allTime->cached)); ?> cached tokens billed at the cache rate</p>
            </div>
        </div>

        
        <div class="aiud-panel">
            <div class="aiud-panel-head">
                <h2>Cost per product</h2>
                <p>Write + QA-review spend for every AI-written product (top 50 by cost)</p>
            </div>
            <div class="aiud-scroll">
                <table class="aiud-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Batch</th>
                            <th class="aiud-right">Requests</th>
                            <th class="aiud-right">Input tok</th>
                            <th class="aiud-right">Cached tok</th>
                            <th class="aiud-right">Output tok</th>
                            <th class="aiud-right">Cost (USD)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $byProduct; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr>
                                <td class="aiud-dim"><?php echo e($index + 1); ?></td>
                                <td class="aiud-strong"><?php echo e($row->name); ?></td>
                                <td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row->batch): ?>
                                        <span class="aiud-badge"><?php echo e($row->batch); ?></span>
                                    <?php else: ?>
                                        <span class="aiud-dim">—</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td class="aiud-right"><?php echo e(number_format($row->requests)); ?></td>
                                <td class="aiud-right"><?php echo e(number_format($row->input)); ?></td>
                                <td class="aiud-right aiud-green"><?php echo e(number_format($row->cached)); ?></td>
                                <td class="aiud-right"><?php echo e(number_format($row->output)); ?></td>
                                <td class="aiud-right aiud-cost">$<?php echo e(number_format($row->cost, 4)); ?></td>
                                <td class="aiud-right">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row->product_id): ?>
                                        <a href="/admin/products/<?php echo e($row->product_id); ?>/edit" class="aiud-edit">
                                            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Z" />
                                            </svg>
                                            Edit
                                        </a>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr><td colspan="9" class="aiud-empty">No product spend yet — run an AI product batch to see per-product costs here.</td></tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        
        <div class="aiud-panel">
            <div class="aiud-panel-head">
                <h2>Cost by model</h2>
                <p>Share of total spend per provider/model</p>
            </div>
            <div class="aiud-scroll">
                <table class="aiud-table">
                    <thead>
                        <tr>
                            <th>Provider</th>
                            <th>Model</th>
                            <th>Share</th>
                            <th class="aiud-right">Requests</th>
                            <th class="aiud-right">Input tok</th>
                            <th class="aiud-right">Cached tok</th>
                            <th class="aiud-right">Output tok</th>
                            <th class="aiud-right">Cost (USD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $byModel; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php $share = (float) $allTime->cost > 0 ? (float) $row->cost / (float) $allTime->cost * 100 : 0; ?>
                            <tr>
                                <td style="text-transform: capitalize"><?php echo e($row->provider); ?></td>
                                <td class="aiud-strong">
                                    <?php echo e($row->model); ?>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! (\App\Models\AiUsageLog::isPriced($row->model))): ?>
                                        <span class="aiud-badge review" title="No pricing entry for this model — its costs show as $0.00. Add a row to AiUsageLog::PRICES.">unpriced</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td>
                                    <div class="aiud-share">
                                        <div class="aiud-share-track"><div class="aiud-share-fill" style="width: <?php echo e(round($share)); ?>%"></div></div>
                                        <span><?php echo e(number_format($share, 1)); ?>%</span>
                                    </div>
                                </td>
                                <td class="aiud-right"><?php echo e(number_format((int) $row->requests)); ?></td>
                                <td class="aiud-right"><?php echo e(number_format((int) $row->input)); ?></td>
                                <td class="aiud-right aiud-green"><?php echo e(number_format((int) $row->cached)); ?></td>
                                <td class="aiud-right"><?php echo e(number_format((int) $row->output)); ?></td>
                                <td class="aiud-right aiud-cost">$<?php echo e(number_format((float) $row->cost, 4)); ?></td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr><td colspan="8" class="aiud-empty">No AI usage yet — run a product batch to see costs here.</td></tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        
        <div class="aiud-panel">
            <div class="aiud-panel-head">
                <h2>Recent requests <span class="aiud-hint">(auto-refreshes every 10s)</span></h2>
            </div>
            <div class="aiud-scroll">
                <table class="aiud-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Batch</th>
                            <th>Purpose</th>
                            <th>Model</th>
                            <th class="aiud-right">Tokens (in / cached / out)</th>
                            <th class="aiud-right">Cost (USD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $recent; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr>
                                <td style="white-space: nowrap"><?php echo e($log->created_at->format('H:i:s')); ?></td>
                                <td><?php echo e($log->batch?->name ?? '—'); ?></td>
                                <td><span class="aiud-badge <?php echo e($log->purpose === 'write' ? 'write' : ($log->purpose === 'review' ? 'review' : '')); ?>"><?php echo e($log->purpose); ?></span></td>
                                <td><?php echo e($log->model); ?></td>
                                <td class="aiud-right">
                                    <?php echo e(number_format($log->input_tokens)); ?> /
                                    <span class="aiud-green"><?php echo e(number_format($log->cached_tokens)); ?></span> /
                                    <?php echo e(number_format($log->output_tokens)); ?>

                                </td>
                                <td class="aiud-right aiud-strong">$<?php echo e(number_format((float) $log->cost, 5)); ?></td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr><td colspan="6" class="aiud-empty">Nothing yet.</td></tr>
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
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/filament/pages/ai-usage-dashboard.blade.php ENDPATH**/ ?>