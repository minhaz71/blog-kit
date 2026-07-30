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
        .lga { display: flex; flex-direction: column; gap: 1.5rem; }
        .lga-cards { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        .lga-card { border-radius: 1rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); padding: 1.25rem; }
        .dark .lga-card { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .lga-label { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; }
        .lga-value { margin-top: .25rem; font-size: 1.9rem; font-weight: 800; color: #111827; }
        .dark .lga-value { color: #f9fafb; }

        .lga-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; border-radius: 1rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); padding: .9rem 1.25rem; }
        .dark .lga-toolbar { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .lga-input { border-radius: .6rem; border: 1px solid #d1d5db; padding: .5rem .75rem; font-size: .85rem; min-width: 16rem; background: #fff; color: #111827; }
        .dark .lga-input { background: rgba(255,255,255,.05); border-color: rgba(255,255,255,.12); color: #f9fafb; }
        .lga-note { font-size: .75rem; color: #9ca3af; }

        .lga-panel { border-radius: 1rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); overflow: hidden; }
        .dark .lga-panel { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .lga-group-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .85rem 1.5rem; background: #f9fafb; border-bottom: 1px solid rgba(0,0,0,.05); }
        .dark .lga-group-head { background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.08); }
        .lga-group-title { font-weight: 700; font-size: .9rem; color: #111827; }
        .dark .lga-group-title { color: #f9fafb; }
        .lga-kind { display: inline-block; border-radius: .35rem; background: #f0fdfa; color: #0f766e; font-size: .6rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; padding: .15rem .45rem; margin-right: .45rem; vertical-align: middle; }
        .dark .lga-kind { background: rgba(99,102,241,.15); color: #5eead4; }

        .lga-row { display: flex; gap: 1rem; align-items: flex-start; padding: 1rem 1.5rem; border-bottom: 1px solid rgba(0,0,0,.04); }
        .dark .lga-row { border-color: rgba(255,255,255,.05); }
        .lga-row:last-child { border-bottom: 0; }
        .lga-score { flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 999px; font-weight: 800; font-size: .85rem; }
        .lga-score-high { background: #dcfce7; color: #15803d; }
        .lga-score-mid { background: #fef3c7; color: #b45309; }
        .dark .lga-score-high { background: rgba(34,197,94,.15); color: #4ade80; }
        .dark .lga-score-mid { background: rgba(245,158,11,.15); color: #fbbf24; }
        .lga-body { flex: 1; min-width: 0; }
        .lga-sentence { font-size: .9rem; color: #374151; line-height: 1.55; }
        .dark .lga-sentence { color: #d1d5db; }
        .lga-sentence mark { background: #99f6e4; color: #134e4a; border-radius: .25rem; padding: 0 .25rem; font-weight: 600; }
        .dark .lga-sentence mark { background: rgba(99,102,241,.35); color: #ccfbf1; }
        .lga-target { margin-top: .35rem; font-size: .75rem; color: #6b7280; }
        .lga-target a { color: #0f766e; font-weight: 600; text-decoration: none; }
        .lga-target a:hover { text-decoration: underline; }
        .lga-actions { display: flex; flex-shrink: 0; gap: .5rem; }
        .lga-btn { border: 0; cursor: pointer; border-radius: .55rem; padding: .45rem .95rem; font-size: .78rem; font-weight: 700; }
        .lga-apply { background: #0f766e; color: #fff; }
        .lga-apply:hover { background: #115e59; }
        .lga-dismiss { background: #f3f4f6; color: #6b7280; }
        .lga-dismiss:hover { background: #e5e7eb; }
        .dark .lga-dismiss { background: rgba(255,255,255,.08); color: #d1d5db; }
        .lga-undo { background: #fee2e2; color: #b91c1c; }
        .lga-undo:hover { background: #fecaca; }
        .lga-empty { padding: 3rem; text-align: center; color: #9ca3af; }
        .lga-h3 { font-weight: 700; padding: 1rem 1.5rem .25rem; color: #111827; }
        .dark .lga-h3 { color: #f9fafb; }
    </style>

    <div class="lga">
        <div class="lga-cards">
            <div class="lga-card">
                <p class="lga-label">Pending suggestions</p>
                <p class="lga-value" style="color:#0f766e"><?php echo e($stats->pending); ?></p>
            </div>
            <div class="lga-card">
                <p class="lga-label">Links applied</p>
                <p class="lga-value" style="color:#16a34a"><?php echo e($stats->applied); ?></p>
            </div>
            <div class="lga-card">
                <p class="lga-label">Dictionary phrases</p>
                <p class="lga-value"><?php echo e(number_format($stats->phrases)); ?></p>
            </div>
        </div>

        <div class="lga-toolbar">
            <input type="search" class="lga-input" wire:model.live.debounce.400ms="search" placeholder="Filter by product or post name…">
            <span class="lga-note">Suggest-only: nothing is ever applied without your click. Re-scans run weekly and after every content edit.</span>
            <div style="margin-left:auto">
                <?php if (isset($component)) { $__componentOriginal6330f08526bbb3ce2a0da37da512a11f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6330f08526bbb3ce2a0da37da512a11f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament::components.button.index','data' => ['wire:click' => 'rebuild','wire:loading.attr' => 'disabled','icon' => 'heroicon-o-arrow-path']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:click' => 'rebuild','wire:loading.attr' => 'disabled','icon' => 'heroicon-o-arrow-path']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                    <span wire:loading.remove wire:target="rebuild">Re-scan now</span>
                    <span wire:loading wire:target="rebuild">Scanning…</span>
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

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $suggestions): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php $first = $suggestions->first(); ?>
            <div class="lga-panel">
                <div class="lga-group-head">
                    <span class="lga-group-title">
                        <span class="lga-kind"><?php echo e(class_basename($first->source_type)); ?></span>
                        <?php echo e($first->source->name ?? $first->source->title); ?>

                    </span>
                    <a href="<?php echo e($first->source->url()); ?>" target="_blank" class="lga-note">view page ↗</a>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $suggestions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="lga-row" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'sg-'.e($s->id).''; ?>wire:key="sg-<?php echo e($s->id); ?>">
                        <span class="lga-score <?php echo e($s->score >= 60 ? 'lga-score-high' : 'lga-score-mid'); ?>"><?php echo e($s->score); ?></span>
                        <div class="lga-body">
                            <p class="lga-sentence">
                                <?php
                                    $pos = mb_stripos($s->sentence ?? '', $s->anchor);
                                ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pos !== false): ?>
                                    <?php echo e(mb_substr($s->sentence, 0, $pos)); ?><mark><?php echo e(mb_substr($s->sentence, $pos, mb_strlen($s->anchor))); ?></mark><?php echo e(mb_substr($s->sentence, $pos + mb_strlen($s->anchor))); ?>

                                <?php else: ?>
                                    <?php echo e($s->sentence); ?> → <mark><?php echo e($s->anchor); ?></mark>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </p>
                            <p class="lga-target">
                                links to: <a href="<?php echo e($s->target->url()); ?>" target="_blank"><?php echo e($s->target->name ?? $s->target->title); ?></a>
                                <span class="lga-kind" style="margin-left:.4rem"><?php echo e(class_basename($s->target_type)); ?></span>
                            </p>
                        </div>
                        <div class="lga-actions">
                            <button type="button" class="lga-btn lga-apply" wire:click="apply(<?php echo e($s->id); ?>)" wire:loading.attr="disabled">✓ Apply</button>
                            <button type="button" class="lga-btn lga-dismiss" wire:click="dismiss(<?php echo e($s->id); ?>)" wire:loading.attr="disabled">✗ Dismiss</button>
                        </div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <div class="lga-panel"><div class="lga-empty">No pending suggestions<?php echo e(trim($search) !== '' ? ' matching your filter' : ' — click "Re-scan now" after adding content'); ?>.</div></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($applied->isNotEmpty()): ?>
            <div class="lga-panel">
                <h3 class="lga-h3">Recently applied (<?php echo e($applied->count()); ?>)</h3>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $applied; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="lga-row" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'ap-'.e($s->id).''; ?>wire:key="ap-<?php echo e($s->id); ?>">
                        <div class="lga-body">
                            <p class="lga-sentence"><mark><?php echo e($s->anchor); ?></mark> in <?php echo e($s->source->name ?? $s->source->title); ?>

                                → <a href="<?php echo e($s->target->url()); ?>" target="_blank" style="color:#0f766e"><?php echo e($s->target->name ?? $s->target->title); ?></a></p>
                            <p class="lga-target"><?php echo e($s->applied_at?->diffForHumans()); ?></p>
                        </div>
                        <div class="lga-actions">
                            <button type="button" class="lga-btn lga-undo" wire:click="undo(<?php echo e($s->id); ?>)" wire:loading.attr="disabled">Undo</button>
                        </div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
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
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/filament/pages/link-agent.blade.php ENDPATH**/ ?>