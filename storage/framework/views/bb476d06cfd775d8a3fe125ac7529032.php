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
        .fr { display:flex; flex-direction:column; gap:1.25rem; }
        .fr-card { border-radius:1rem; background:#fff; border:1px solid rgba(0,0,0,.06); box-shadow:0 4px 16px rgba(15,23,42,.05); padding:1.25rem; }
        .dark .fr-card { background:rgba(255,255,255,.03); border-color:rgba(255,255,255,.08); box-shadow:none; }
        .fr-row { display:grid; gap:1rem; grid-template-columns:1fr 1fr; }
        @media (max-width:640px){ .fr-row { grid-template-columns:1fr; } }
        .fr-label { font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#6b7280; margin-bottom:.35rem; display:block; }
        .fr-input { width:100%; border-radius:.6rem; border:1px solid #d1d5db; padding:.55rem .75rem; font-size:.9rem; background:#fff; color:#111827; }
        .dark .fr-input { background:rgba(255,255,255,.05); border-color:rgba(255,255,255,.12); color:#f9fafb; }
        .fr-scopes { display:grid; gap:.4rem .9rem; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); margin-top:.5rem; }
        .fr-scope { display:flex; align-items:center; gap:.5rem; font-size:.82rem; color:#374151; }
        .dark .fr-scope { color:#d1d5db; }
        .fr-toggles { display:flex; gap:1.5rem; margin-top:.75rem; font-size:.85rem; }
        .fr-actions { display:flex; gap:.75rem; margin-top:1rem; flex-wrap:wrap; }
        .fr-btn { border-radius:.6rem; padding:.55rem 1.1rem; font-size:.85rem; font-weight:600; cursor:pointer; border:1px solid transparent; }
        .fr-btn-secondary { background:#f3f4f6; color:#111827; border-color:#e5e7eb; }
        .dark .fr-btn-secondary { background:rgba(255,255,255,.08); color:#f9fafb; border-color:rgba(255,255,255,.12); }
        .fr-btn-primary { background:#4f46e5; color:#fff; }
        .fr-btn-danger { background:#fef2f2; color:#b91c1c; border-color:#fecaca; }
        .fr-note { font-size:.75rem; color:#9ca3af; margin-top:.5rem; }
        .fr-warn { font-size:.8rem; color:#92400e; background:#fffbeb; border:1px solid #fde68a; border-radius:.6rem; padding:.6rem .85rem; margin-top:1rem; }
        .dark .fr-warn { color:#fcd34d; background:rgba(251,191,36,.08); border-color:rgba(251,191,36,.25); }
        table.fr-table { width:100%; border-collapse:collapse; font-size:.8rem; }
        .fr-table th, .fr-table td { text-align:left; padding:.5rem .6rem; border-bottom:1px solid rgba(0,0,0,.06); vertical-align:top; }
        .dark .fr-table th, .dark .fr-table td { border-color:rgba(255,255,255,.08); }
        .fr-table th { font-size:.68rem; text-transform:uppercase; letter-spacing:.04em; color:#6b7280; }
        .fr-loc { font-family:ui-monospace,monospace; font-size:.72rem; color:#6b7280; }
        .fr-prev mark { background:#fde68a; padding:0 .1rem; border-radius:.2rem; }
        .fr-title { font-size:1rem; font-weight:700; color:#111827; }
        .dark .fr-title { color:#f9fafb; }
        .fr-badge { display:inline-block; font-size:.7rem; font-weight:600; padding:.1rem .5rem; border-radius:1rem; background:#eef2ff; color:#4338ca; }
        .fr-reverted { background:#f3f4f6; color:#6b7280; }
    </style>

    <div class="fr" wire:loading.class="fr-busy">
        
        <div class="fr-card">
            <div class="fr-row">
                <div>
                    <label class="fr-label">Find</label>
                    <input type="text" class="fr-input" wire:model="find" placeholder="free delivery over 300 AED">
                </div>
                <div>
                    <label class="fr-label">Replace with</label>
                    <input type="text" class="fr-input" wire:model="replace" placeholder="free delivery over 400 AED">
                </div>
            </div>

            <div class="fr-toggles">
                <label class="fr-scope"><input type="checkbox" wire:model="caseSensitive"> Case-sensitive</label>
                <label class="fr-scope"><input type="checkbox" wire:model="wholeWord"> Whole word only</label>
            </div>

            <div style="margin-top:1rem;">
                <label class="fr-label">Where to search</label>
                <div class="fr-scopes">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->scopeOptions(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <label class="fr-scope">
                            <input type="checkbox" wire:model="scopes.<?php echo e($key); ?>"> <?php echo e($label); ?>

                        </label>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </div>

            <div class="fr-actions">
                <button class="fr-btn fr-btn-secondary" wire:click="dryRun" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="dryRun">Dry run (preview)</span>
                    <span wire:loading wire:target="dryRun">Scanning…</span>
                </button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preview && $preview['occurrences'] > 0): ?>
                    <button class="fr-btn fr-btn-primary"
                            wire:click="apply"
                            wire:confirm="Replace <?php echo e($preview['occurrences']); ?> occurrence(s) across <?php echo e($preview['records']); ?> record(s)? You can undo this afterwards."
                            wire:loading.attr="disabled">
                        Replace all <?php echo e($preview['occurrences']); ?>

                    </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="fr-warn">
                This changes wording only, in descriptions / content / SEO text. It never touches product
                names, prices, slugs or settings. If you're changing a threshold like free-delivery, also
                update the actual shipping rule in your shipping settings.
            </div>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preview !== null): ?>
            <div class="fr-card">
                <div class="fr-title">
                    Preview — <?php echo e($preview['occurrences']); ?> match(es) in <?php echo e($preview['records']); ?> record(s)
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preview['occurrences'] === 0): ?>
                    <p class="fr-note">No matches. Nothing would change.</p>
                <?php else: ?>
                    <p class="fr-note">Nothing has been changed yet. Review below, then click “Replace”.</p>
                    <div style="overflow-x:auto; margin-top:.75rem;">
                        <table class="fr-table">
                            <thead>
                                <tr><th>Type</th><th>Record</th><th>Field</th><th>Location</th><th>Hits</th><th>Preview</th></tr>
                            </thead>
                            <tbody>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $preview['matches']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <tr>
                                        <td><?php echo e($m['type']); ?></td>
                                        <td><?php echo e($m['record']); ?></td>
                                        <td><?php echo e($m['field']); ?></td>
                                        <td class="fr-loc"><?php echo e($m['location']); ?></td>
                                        <td><?php echo e($m['occurrences']); ?></td>
                                        <td class="fr-prev"><?php echo str_replace(['«','»'], ['<mark>','</mark>'], e($m['preview'])); ?></td>
                                    </tr>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preview['truncated']): ?>
                        <p class="fr-note">Showing the first <?php echo e(count($preview['matches'])); ?> matches — the replace still applies to all <?php echo e($preview['occurrences']); ?>.</p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php ($batches = $this->recentBatches()); ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batches->isNotEmpty()): ?>
            <div class="fr-card">
                <div class="fr-title">Recent replacements</div>
                <div style="overflow-x:auto; margin-top:.75rem;">
                    <table class="fr-table">
                        <thead>
                            <tr><th>When</th><th>Find → Replace</th><th>Scope</th><th>Changed</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $batches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr>
                                    <td><?php echo e($b->created_at?->diffForHumans()); ?></td>
                                    <td><code class="fr-loc"><?php echo e(\Illuminate\Support\Str::limit($b->find, 40)); ?></code> → <code class="fr-loc"><?php echo e(\Illuminate\Support\Str::limit($b->replace, 40)); ?></code></td>
                                    <td class="fr-loc"><?php echo e(implode(', ', (array) $b->scopes)); ?></td>
                                    <td><?php echo e($b->occurrences_count); ?> / <?php echo e($b->records_count); ?> rec</td>
                                    <td>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($b->isReverted()): ?>
                                            <span class="fr-badge fr-reverted">Reverted</span>
                                        <?php else: ?>
                                            <span class="fr-badge">Applied</span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($b->isReverted())): ?>
                                            <button class="fr-btn fr-btn-danger"
                                                    wire:click="undo(<?php echo e($b->id); ?>)"
                                                    wire:confirm="Undo this replacement and restore the previous text?">
                                                Undo
                                            </button>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/filament/pages/find-replace.blade.php ENDPATH**/ ?>