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
        .prv-grid { display: grid; gap: 1rem; }
        @media (min-width: 1024px) { .prv-grid { grid-template-columns: 320px 1fr; } }
        .prv-card { background: #fff; border: 1px solid #e5e7eb; border-radius: .75rem; overflow: hidden; }
        .dark .prv-card { background: #18181b; border-color: #27272a; }
        .prv-card h3 { font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; padding: .8rem 1rem; border-bottom: 1px solid #e5e7eb; }
        .dark .prv-card h3 { border-color: #27272a; }
        .prv-list { max-height: 34rem; overflow-y: auto; }
        .prv-item { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .65rem 1rem; border-bottom: 1px solid #f3f4f6; font-size: .82rem; }
        .dark .prv-item { border-color: #27272a; }
        .prv-item time { font-weight: 600; display: block; }
        .prv-item span { color: #6b7280; font-size: .74rem; }
        .prv-restore { color: #0f766e; font-weight: 600; font-size: .75rem; cursor: pointer; white-space: nowrap; }
        .prv-restore:hover { text-decoration: underline; }
        .prv-selects { display: flex; flex-wrap: wrap; gap: .75rem; padding: 1rem; border-bottom: 1px solid #e5e7eb; }
        .dark .prv-selects { border-color: #27272a; }
        .prv-selects label { font-size: .75rem; font-weight: 600; color: #6b7280; display: block; margin-bottom: .25rem; }
        .prv-selects select { border-radius: .5rem; border-color: #d1d5db; font-size: .82rem; min-width: 15rem; }
        .dark .prv-selects select { background: #27272a; border-color: #3f3f46; color: #e4e4e7; }
        .prv-diff { padding: 1rem 1.25rem; font-size: .9rem; line-height: 1.8; }
        .prv-diff h4 { font-size: .78rem; font-weight: 700; text-transform: uppercase; color: #6b7280; margin: 1.1rem 0 .35rem; }
        .prv-diff h4:first-child { margin-top: 0; }
        .prv-diff del { background: #fee2e2; color: #991b1b; text-decoration: line-through; border-radius: .2rem; padding: 0 .15rem; }
        .prv-diff ins { background: #d1fae5; color: #065f46; text-decoration: none; border-radius: .2rem; padding: 0 .15rem; }
        .dark .prv-diff del { background: #7f1d1d55; color: #fca5a5; }
        .dark .prv-diff ins { background: #06402955; color: #6ee7b7; }
        .prv-same { color: #9ca3af; font-style: italic; font-size: .84rem; }
        .prv-meta { font-size: .74rem; color: #6b7280; padding: 0 1.25rem .9rem; }
    </style>

    <div class="prv-grid">
        
        <div class="prv-card">
            <h3>History (<?php echo e($revisions->count()); ?> snapshot<?php echo e($revisions->count() === 1 ? '' : 's'); ?>)</h3>
            <div class="prv-list">
                <div class="prv-item">
                    <div>
                        <time>Current live version</time>
                        <span>Last edited by <?php echo e($record->lastEditor?->name ?: 'AI writer / system'); ?> · <?php echo e($record->updated_at->format('M j, g:ia')); ?></span>
                    </div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $revisions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $revision): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="prv-item">
                        <div>
                            <time><?php echo e($revision->created_at->format('M j, Y g:ia')); ?></time>
                            <span>replaced by <?php echo e($revision->editorLabel()); ?></span>
                        </div>
                        <a class="prv-restore" wire:click="restore(<?php echo e($revision->id); ?>)"
                           wire:confirm="Restore this version? The current live version is snapshotted first.">
                            Restore
                        </a>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="prv-item"><span>No snapshots yet — they appear when the title, excerpt or content changes.</span></div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        
        <div class="prv-card">
            <div class="prv-selects">
                <div>
                    <label for="prv-from">Compare from</label>
                    <select id="prv-from" wire:model.live="from">
                        <option value="0">Current live version</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $revisions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $revision): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <option value="<?php echo e($revision->id); ?>"><?php echo e($revision->created_at->format('M j, Y g:ia')); ?> · <?php echo e($revision->editorLabel()); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                </div>
                <div>
                    <label for="prv-to">Compare to</label>
                    <select id="prv-to" wire:model.live="to">
                        <option value="0">Current live version</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $revisions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $revision): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <option value="<?php echo e($revision->id); ?>"><?php echo e($revision->created_at->format('M j, Y g:ia')); ?> · <?php echo e($revision->editorLabel()); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                </div>
            </div>
            <p class="prv-meta" style="padding-top:.9rem">
                <del style="background:#fee2e2;color:#991b1b;padding:0 .3rem;border-radius:.2rem;text-decoration:line-through">removed</del>
                = only in "<?php echo e($fromVersion['label']); ?>" ·
                <ins style="background:#d1fae5;color:#065f46;padding:0 .3rem;border-radius:.2rem;text-decoration:none">added</ins>
                = only in "<?php echo e($toVersion['label']); ?>"
            </p>
            <div class="prv-diff">
                <h4>Title</h4>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($diff['title']): ?><p><?php echo $diff['title']; ?></p><?php else: ?><p class="prv-same">No change.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <h4>Excerpt</h4>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($diff['excerpt']): ?><p><?php echo $diff['excerpt']; ?></p><?php else: ?><p class="prv-same">No change.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <h4>Content</h4>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($diff['content']): ?><p><?php echo $diff['content']; ?></p><?php else: ?><p class="prv-same">No change.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/filament/resources/post-revisions.blade.php ENDPATH**/ ?>