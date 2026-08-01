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
        .aibm { display: flex; flex-direction: column; gap: 1.5rem; font-variant-numeric: tabular-nums; }

        .aibm-panel { border-radius: 1rem; overflow: hidden; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); }
        .dark .aibm-panel { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .aibm-panel-head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: 1rem 1.25rem; border-bottom: 1px solid rgba(0,0,0,.05); }
        .dark .aibm-panel-head { border-color: rgba(255,255,255,.08); }
        .aibm-panel-head h2 { margin: 0; font-size: 1rem; font-weight: 600; }
        .aibm-hint { font-size: .75rem; color: #9ca3af; }

        /* Status strip */
        .aibm-status { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.25rem; }
        .aibm-status-left { display: flex; align-items: center; gap: .75rem; }
        .aibm-dot { position: relative; display: inline-flex; height: .75rem; width: .75rem; }
        .aibm-dot span.ping { position: absolute; inline-size: 100%; block-size: 100%; border-radius: 9999px; opacity: .75; animation: aibm-ping 1s cubic-bezier(0,0,.2,1) infinite; }
        .aibm-dot span.core { position: relative; display: inline-flex; height: .75rem; width: .75rem; border-radius: 9999px; }
        @keyframes aibm-ping { 75%, 100% { transform: scale(2); opacity: 0; } }
        .aibm-state { font-size: 1.125rem; font-weight: 600; text-transform: capitalize; }
        .aibm-provider { font-size: .875rem; color: #6b7280; }

        .aibm-btn { display: inline-flex; align-items: center; gap: .375rem; border-radius: .5rem; border: 1px solid; padding: .375rem .75rem; font-size: .75rem; font-weight: 600; cursor: pointer; background: transparent; transition: background .15s, opacity .15s; }
        .aibm-btn:disabled { opacity: .5; cursor: not-allowed; }
        .aibm-btn.amber  { border-color: #fcd34d; background: #fffbeb; color: #b45309; }
        .aibm-btn.amber:hover  { background: #fef3c7; }
        .aibm-btn.green  { border-color: #6ee7b7; background: #ecfdf5; color: #047857; }
        .aibm-btn.green:hover  { background: #d1fae5; }
        .aibm-btn.red    { border-color: #fca5a5; background: #fef2f2; color: #b91c1c; }
        .aibm-btn.red:hover    { background: #fee2e2; }
        .dark .aibm-btn.amber { background: rgba(245,158,11,.12); color: #fcd34d; border-color: rgba(245,158,11,.35); }
        .dark .aibm-btn.green { background: rgba(16,185,129,.12); color: #6ee7b7; border-color: rgba(16,185,129,.35); }
        .dark .aibm-btn.red   { background: rgba(239,68,68,.12); color: #fca5a5; border-color: rgba(239,68,68,.35); }

        /* Stat cards */
        .aibm-cards { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
        @media (min-width: 1024px) { .aibm-cards { grid-template-columns: repeat(4, 1fr); } }
        .aibm-card { position: relative; overflow: hidden; border-radius: 1rem; padding: 1.25rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); }
        .dark .aibm-card { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .aibm-card-glow { position: absolute; top: -1.5rem; right: -1.5rem; width: 6rem; height: 6rem; border-radius: 9999px; opacity: .12; pointer-events: none; }
        .aibm-card-label { margin: 0; font-size: .8125rem; font-weight: 500; color: #6b7280; }
        .dark .aibm-card-label { color: #9ca3af; }
        .aibm-card-value { margin: .5rem 0 0; font-size: 1.875rem; line-height: 1.1; font-weight: 800; letter-spacing: -.02em; }
        .aibm-card-sub { margin: .25rem 0 0; font-size: .75rem; color: #9ca3af; }

        /* Progress bar */
        .aibm-progress { height: .625rem; border-radius: 9999px; background: rgba(0,0,0,.08); overflow: hidden; }
        .dark .aibm-progress { background: rgba(255,255,255,.1); }
        .aibm-progress-fill { height: 100%; border-radius: 9999px; background: linear-gradient(90deg, #0d9488, #10b981); transition: width .7s ease; }

        /* Alerts */
        .aibm-alert { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; border-radius: .75rem; padding: .75rem 1rem; font-size: .875rem; border: 1px solid; }
        .aibm-alert.err   { border-color: #fca5a5; background: #fef2f2; color: #991b1b; }
        .aibm-alert.warn  { border-color: #fcd34d; background: #fffbeb; color: #92400e; }
        .dark .aibm-alert.err  { background: rgba(239,68,68,.1); color: #fca5a5; border-color: rgba(239,68,68,.3); }
        .dark .aibm-alert.warn { background: rgba(245,158,11,.1); color: #fcd34d; border-color: rgba(245,158,11,.3); }
        .aibm-alert code { border-radius: .25rem; background: rgba(0,0,0,.06); padding: .1rem .35rem; font-family: ui-monospace, monospace; font-size: .75rem; }
        .aibm-alert-btn { flex-shrink: 0; border: 0; border-radius: .5rem; background: #d97706; color: #fff; padding: .5rem 1rem; font-size: .875rem; font-weight: 600; cursor: pointer; }
        .aibm-alert-btn:hover { background: #b45309; }
        .aibm-alert-btn:disabled { opacity: .6; cursor: not-allowed; }

        /* Two-column grid */
        .aibm-grid { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
        @media (min-width: 1024px) { .aibm-grid { grid-template-columns: 2fr 3fr; } }

        /* Items list */
        .aibm-items { max-height: 32rem; overflow-y: auto; }
        .aibm-item { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .75rem 1.25rem; border-top: 1px solid rgba(0,0,0,.05); }
        .dark .aibm-item { border-color: rgba(255,255,255,.06); }
        .aibm-item:first-child { border-top: 0; }
        .aibm-item-name { font-size: .875rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .aibm-item-meta { margin: .125rem 0 0; font-size: .75rem; color: #6b7280; }
        .aibm-item-err { margin: .125rem 0 0; font-size: .75rem; color: #dc2626; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .aibm-pill { flex-shrink: 0; border-radius: 9999px; padding: .125rem .625rem; font-size: .75rem; font-weight: 600; white-space: nowrap; }
        .aibm-pill.gray    { background: #f3f4f6; color: #4b5563; }
        .aibm-pill.indigo  { background: #ccfbf1; color: #115e59; }
        .aibm-pill.amber   { background: #fef3c7; color: #b45309; }
        .aibm-pill.green   { background: #d1fae5; color: #047857; }
        .aibm-pill.red     { background: #fee2e2; color: #b91c1c; }
        .aibm-pulse { animation: aibm-pulse 1.5s ease-in-out infinite; }
        @keyframes aibm-pulse { 50% { opacity: .5; } }

        /* Activity feed */
        .aibm-feed { max-height: 32rem; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: .25rem; }
        .aibm-log { display: flex; gap: .75rem; border-radius: .625rem; padding: .5rem .75rem; font-size: .875rem; }
        .aibm-log.info    { background: rgba(0,0,0,.03); color: #374151; }
        .aibm-log.success { background: #ecfdf5; color: #065f46; }
        .aibm-log.warning { background: #fffbeb; color: #92400e; }
        .aibm-log.error   { background: #fef2f2; color: #991b1b; }
        .dark .aibm-log.info    { background: rgba(255,255,255,.05); color: #d1d5db; }
        .dark .aibm-log.success { background: rgba(16,185,129,.1); color: #6ee7b7; }
        .dark .aibm-log.warning { background: rgba(245,158,11,.1); color: #fcd34d; }
        .dark .aibm-log.error   { background: rgba(239,68,68,.1); color: #fca5a5; }
        .aibm-log-time { flex-shrink: 0; font-family: ui-monospace, monospace; font-size: .75rem; color: #9ca3af; }
        .aibm-log-stage { flex-shrink: 0; border-radius: .25rem; background: rgba(0,0,0,.06); padding: 0 .375rem; font-size: .625rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; align-self: flex-start; line-height: 1.5; }
        .dark .aibm-log-stage { background: rgba(255,255,255,.1); }
        .aibm-log-msg { min-width: 0; overflow-wrap: anywhere; }
        .aibm-empty { padding: 2rem 1.25rem; text-align: center; font-size: .875rem; color: #6b7280; }
    </style>

    <?php
        $statusColor = match ($batch->status) {
            'completed' => '#10b981',
            'processing', 'linking' => '#f59e0b',
            'failed' => '#ef4444',
            'paused', 'stopped' => '#f97316',
            default => '#9ca3af',
        };
        $running = in_array($batch->status, ['processing', 'linking']);
    ?>

    <div class="aibm" wire:poll.3s>
        
        <div class="aibm-panel">
            <div class="aibm-status">
                <div class="aibm-status-left">
                    <span class="aibm-dot">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($running): ?>
                            <span class="ping" style="background: <?php echo e($statusColor); ?>"></span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <span class="core" style="background: <?php echo e($statusColor); ?>"></span>
                    </span>
                    <span class="aibm-state"><?php echo e($batch->status); ?></span>
                    <span class="aibm-provider"><?php echo e(\App\Models\AiImportBatch::PROVIDERS[$batch->provider] ?? $batch->provider); ?></span>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batch->status === 'processing'): ?>
                        <button type="button" class="aibm-btn amber" wire:click="pauseBatch" wire:loading.attr="disabled">⏸ Pause</button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($batch->status, ['paused', 'stopped'])): ?>
                        <button type="button" class="aibm-btn green" wire:click="resumeBatch" wire:loading.attr="disabled">▶ Resume</button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($batch->status, ['processing', 'paused'])): ?>
                        <button type="button" class="aibm-btn red" wire:click="stopBatch" wire:loading.attr="disabled"
                                wire:confirm="Stop this batch? Waiting products will not be processed until you resume.">⏹ Stop</button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>

        
        <div class="aibm-cards">
            <div class="aibm-card">
                <div class="aibm-card-glow" style="background: linear-gradient(135deg, #0d9488, #8b5cf6)"></div>
                <p class="aibm-card-label">Progress</p>
                <p class="aibm-card-value" style="color: #0d9488"><?php echo e($batch->progressPercent()); ?>%</p>
                <p class="aibm-card-sub"><?php echo e($batch->done_items); ?> of <?php echo e($batch->total_items); ?> products</p>
            </div>
            <div class="aibm-card">
                <div class="aibm-card-glow" style="background: linear-gradient(135deg, #10b981, #14b8a6)"></div>
                <p class="aibm-card-label">Published</p>
                <p class="aibm-card-value" style="color: #059669"><?php echo e($batch->done_items); ?></p>
                <p class="aibm-card-sub">written, reviewed &amp; linked</p>
            </div>
            <div class="aibm-card">
                <div class="aibm-card-glow" style="background: linear-gradient(135deg, #ef4444, #f97316)"></div>
                <p class="aibm-card-label">Failed</p>
                <p class="aibm-card-value" style="color: <?php echo e($batch->failed_items > 0 ? '#dc2626' : '#9ca3af'); ?>"><?php echo e($batch->failed_items); ?></p>
                <p class="aibm-card-sub"><?php echo e($batch->failed_items > 0 ? 'open the batch to retry' : 'none'); ?></p>
            </div>
            <div class="aibm-card">
                <div class="aibm-card-glow" style="background: linear-gradient(135deg, #f59e0b, #ef4444)"></div>
                <p class="aibm-card-label">Spent</p>
                <p class="aibm-card-value" style="color: #d97706">$<?php echo e(number_format($spend, 4)); ?></p>
                <p class="aibm-card-sub">USD, this batch</p>
            </div>
            <div class="aibm-card">
                <div class="aibm-card-glow" style="background: linear-gradient(135deg, #10b981, #14b8a6)"></div>
                <p class="aibm-card-label">Cache savings</p>
                <p class="aibm-card-value" style="color: #059669">$<?php echo e(number_format($cacheSaved, 4)); ?></p>
                <p class="aibm-card-sub"><?php echo e($cacheHitRate); ?>% of input read from prompt cache</p>
            </div>
        </div>

        
        <div class="aibm-panel" style="padding: 1.25rem">
            <div class="aibm-progress"><div class="aibm-progress-fill" style="width: <?php echo e($batch->progressPercent()); ?>%"></div></div>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batch->error): ?>
            <div class="aibm-alert err"><span><strong>Batch error:</strong> <?php echo e($batch->error); ?></span></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batch->total_items === 0 && in_array($batch->status, ['pending', 'processing'])): ?>
            <div class="aibm-alert warn">
                <span><strong>The CSV hasn't been parsed yet</strong> — the parse job is still waiting for a queue worker.</span>
                <button type="button" class="aibm-alert-btn" wire:click="parseNow" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="parseNow">Parse CSV now</span>
                    <span wire:loading wire:target="parseNow">Parsing…</span>
                </button>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($workerStalled && $pendingItems > 0): ?>
            <div class="aibm-alert warn">
                <span>
                    <strong>Processing looks stalled</strong> — <?php echo e($pendingItems); ?> product(s) are still waiting.
                    Click resume to finish the whole batch in the background, or process one product right here:
                </span>
                <div style="display:flex; gap:.5rem; flex-shrink:0;">
                    <button type="button" class="aibm-alert-btn" wire:click="resumeBatch" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="resumeBatch">▶ Resume &amp; finish all</span>
                        <span wire:loading wire:target="resumeBatch">Starting…</span>
                    </button>
                    <button type="button" class="aibm-alert-btn" style="background:#6b7280" wire:click="processNextItem" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="processNextItem">Just one now</span>
                        <span wire:loading wire:target="processNextItem">Writing…</span>
                    </button>
                </div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <div class="aibm-grid">
            
            <div class="aibm-panel">
                <div class="aibm-panel-head"><h2><?php echo e($this->record->kind === 'blog' ? 'Articles' : 'Products'); ?> (<?php echo e($items->count()); ?>)</h2></div>
                <ul class="aibm-items">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <li class="aibm-item">
                            <div style="min-width: 0">
                                <p class="aibm-item-name"><?php echo e($item->row['name'] ?? "Item #{$item->id}"); ?></p>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->error): ?>
                                    <p class="aibm-item-err" title="<?php echo e($item->error); ?>"><?php echo e($item->error); ?></p>
                                <?php elseif($item->status === 'needs_review' && $item->review_summary): ?>
                                    <p class="aibm-item-err" title="<?php echo e($item->review_summary); ?>"><?php echo e($item->open_issues); ?> open issue(s): <?php echo e($item->review_summary); ?></p>
                                <?php elseif($item->passes_done > 0 && in_array($item->status, ['reviewing', 'published', 'linked'])): ?>
                                    <p class="aibm-item-meta">QA passes: <?php echo e($item->passes_done); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->preview_url): ?> · <a href="<?php echo e($item->preview_url); ?>" target="_blank" style="color:#0f766e">view content</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <?php
                                [$pillClass, $pulse] = match ($item->status) {
                                    'pending'  => ['gray', ''],
                                    'writing'  => ['indigo', 'aibm-pulse'],
                                    'reviewing' => ['amber', 'aibm-pulse'],
                                    'published', 'linked' => ['green', ''],
                                    'needs_review' => ['amber', ''],
                                    'failed'   => ['red', ''],
                                    default    => ['gray', ''],
                                };
                            ?>
                            <span class="aibm-pill <?php echo e($pillClass); ?> <?php echo e($pulse); ?>"><?php echo e($item->status === 'linked' ? 'published + linked' : str_replace('_', ' ', $item->status)); ?></span>
                        </li>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <li class="aibm-empty">No items yet — press Start on the batch.</li>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </ul>
            </div>

            
            <div class="aibm-panel">
                <div class="aibm-panel-head">
                    <h2>Live activity</h2>
                    <span class="aibm-hint">auto-refreshes every 3s</span>
                </div>
                <div class="aibm-feed">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $feed; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="aibm-log <?php echo e($log->level); ?>">
                            <span class="aibm-log-time"><?php echo e($log->created_at->format('H:i:s')); ?></span>
                            <span class="aibm-log-stage"><?php echo e($log->stage); ?></span>
                            <span class="aibm-log-msg"><?php echo e($log->message); ?></span>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <div class="aibm-empty">Waiting for activity… start the batch and keep a queue worker running (<code>php artisan queue:work</code>).</div>
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
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/filament/pages/ai-batch-monitor.blade.php ENDPATH**/ ?>