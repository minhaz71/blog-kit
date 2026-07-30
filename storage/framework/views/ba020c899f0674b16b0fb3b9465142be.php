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
        .ist { display: flex; flex-direction: column; gap: 1.5rem; }
        .ist-cards { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
        .ist-card { border-radius: 1rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); padding: 1.25rem; }
        .dark .ist-card { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .ist-label { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; }
        .ist-value { margin-top: .25rem; font-size: 1.9rem; font-weight: 800; color: #111827; }
        .dark .ist-value { color: #f9fafb; }

        .ist-panel { border-radius: 1rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); }
        .dark .ist-panel { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .ist-panel-body { padding: 1.5rem; }
        .ist-panel h3 { font-size: 1rem; font-weight: 700; color: #111827; }
        .dark .ist-panel h3 { color: #f9fafb; }
        .ist-help { margin-top: .25rem; font-size: .82rem; color: #6b7280; }

        .ist-field span { display: block; font-size: .78rem; font-weight: 600; color: #374151; margin-bottom: .3rem; }
        .dark .ist-field span { color: #d1d5db; }
        .ist-input, .ist-select { width: 100%; border-radius: .6rem; border: 1px solid #d1d5db; padding: .5rem .75rem; font-size: .85rem; background: #fff; color: #111827; }
        .dark .ist-input, .dark .ist-select { background: rgba(255,255,255,.05); border-color: rgba(255,255,255,.12); color: #f9fafb; }

        .ist-ai { display: grid; gap: 1rem; align-items: end; }
        @media (min-width: 900px) { .ist-ai { grid-template-columns: 1fr 1fr 1.2fr auto; } }

        .ist-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; padding: 1rem 1.5rem; border-bottom: 1px solid rgba(0,0,0,.05); }
        .dark .ist-toolbar { border-color: rgba(255,255,255,.08); }
        .ist-toolbar .ist-input { max-width: 20rem; }
        .ist-check { display: flex; align-items: center; gap: .45rem; font-size: .85rem; color: #374151; cursor: pointer; }
        .dark .ist-check { color: #d1d5db; }

        .ist-table-wrap { overflow-x: auto; }
        .ist-table { width: 100%; font-size: .85rem; border-collapse: collapse; min-width: 980px; }
        .ist-table thead tr { background: #f9fafb; text-align: left; }
        .dark .ist-table thead tr { background: rgba(255,255,255,.04); }
        .ist-table th { padding: .6rem 1rem; font-size: .66rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; white-space: nowrap; }
        .ist-table td { padding: .7rem 1rem; border-top: 1px solid rgba(0,0,0,.04); vertical-align: top; color: #374151; }
        .dark .ist-table td { border-color: rgba(255,255,255,.05); color: #d1d5db; }

        .ist-thumb { width: 56px; height: 56px; border-radius: .6rem; object-fit: cover; background: #f3f4f6; border: 1px solid rgba(0,0,0,.06); }
        .ist-product a { color: #0f766e; font-weight: 600; text-decoration: none; font-size: .82rem; }
        .ist-product a:hover { text-decoration: underline; }
        .ist-file { margin-top: .2rem; font-size: .7rem; color: #9ca3af; word-break: break-all; max-width: 180px; }
        .ist-issues { margin-top: .3rem; font-size: .68rem; color: #d97706; max-width: 200px; }
        .ist-ok { margin-top: .3rem; font-size: .68rem; color: #16a34a; }

        .ist-meta-input { width: 100%; min-width: 170px; border-radius: .5rem; border: 1px solid #e5e7eb; padding: .4rem .6rem; font-size: .78rem; background: #fff; color: #111827; }
        .ist-meta-input:focus { border-color: #0d9488; outline: none; }
        .dark .ist-meta-input { background: rgba(255,255,255,.05); border-color: rgba(255,255,255,.1); color: #f9fafb; }
        .ist-meta-input.missing { border-color: #fca5a5; background: #fef2f2; }
        .dark .ist-meta-input.missing { background: rgba(239,68,68,.08); }
        .ist-count { display: block; margin-top: .15rem; font-size: .62rem; color: #9ca3af; text-align: right; }

        .ist-row-actions { display: flex; flex-direction: column; gap: .4rem; white-space: nowrap; }
        .ist-btn { display: inline-flex; align-items: center; gap: .3rem; border: 0; cursor: pointer; border-radius: .5rem; padding: .35rem .7rem; font-size: .72rem; font-weight: 600; }
        .ist-btn-ai { background: #f0fdfa; color: #0f766e; }
        .ist-btn-ai:hover { background: #ccfbf1; }
        .ist-btn-gray { background: #f3f4f6; color: #4b5563; }
        .ist-btn-gray:hover { background: #e5e7eb; }
        .dark .ist-btn-ai { background: rgba(99,102,241,.15); color: #5eead4; }
        .dark .ist-btn-gray { background: rgba(255,255,255,.08); color: #d1d5db; }

        .ist-pager { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .9rem 1.5rem; border-top: 1px solid rgba(0,0,0,.05); font-size: .8rem; color: #6b7280; }
        .dark .ist-pager { border-color: rgba(255,255,255,.08); }
        .ist-pager button { border: 1px solid #d1d5db; background: #fff; border-radius: .5rem; padding: .35rem .9rem; font-size: .78rem; cursor: pointer; color: #374151; }
        .ist-pager button:disabled { opacity: .4; cursor: default; }
        .dark .ist-pager button { background: rgba(255,255,255,.05); border-color: rgba(255,255,255,.12); color: #d1d5db; }

        .ist-fields-grid { margin-top: 1rem; display: grid; gap: 1rem; }
        @media (min-width: 640px) { .ist-fields-grid { grid-template-columns: 1fr 1fr; } }
        .ist-controls { margin-top: 1rem; display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; }
        .ist-checks { display: flex; gap: 1rem; font-size: .85rem; color: #374151; }
        .dark .ist-checks { color: #d1d5db; }
        .ist-right { margin-left: auto; display: flex; align-items: center; gap: .75rem; }
        .ist-match { font-size: .85rem; color: #0f766e; font-weight: 600; }
        .ist-match.zero { color: #9ca3af; font-weight: 400; }
        .ist-empty-row { padding: 2.5rem; text-align: center; color: #9ca3af; }
    </style>

    <div class="ist">
        
        <div class="ist-cards">
            <div class="ist-card">
                <p class="ist-label">Product images</p>
                <p class="ist-value"><?php echo e(number_format($totalImages)); ?></p>
            </div>
            <div class="ist-card">
                <p class="ist-label">Missing alt text</p>
                <p class="ist-value" style="color: <?php echo e($missingAlt > 0 ? '#d97706' : '#16a34a'); ?>"><?php echo e(number_format($missingAlt)); ?></p>
            </div>
            <div class="ist-card">
                <p class="ist-label">Missing title</p>
                <p class="ist-value" style="color: <?php echo e($missingTitle > 0 ? '#d97706' : '#16a34a'); ?>"><?php echo e(number_format($missingTitle)); ?></p>
            </div>
        </div>

        
        <div class="ist-panel">
            <div class="ist-panel-body">
                <h3>✨ AI metadata writer</h3>
                <p class="ist-help">
                    Writes alt, title and caption per the image SEO rulebook, from each image's filename + its product's
                    name, brand, category and description. Fills missing fields in batches of <?php echo e(\App\Services\Seo\ImageSeoWriter::BATCH); ?>;
                    the per-row AI button rewrites one image completely.
                </p>
                <div class="ist-ai" style="margin-top: 1rem">
                    <label class="ist-field">
                        <span>AI provider</span>
                        <select wire:model.live="aiProvider" class="ist-select">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $providers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <option value="<?php echo e($key); ?>"><?php echo e($label); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                    </label>
                    <label class="ist-field">
                        <span>Model</span>
                        <input type="text" wire:model="aiModel" class="ist-input" placeholder="<?php echo e($defaultModel); ?> (default)">
                    </label>
                    <div class="ist-help" style="align-self: center">
                        Uses the API key from Settings → AI settings. Usage is logged on the AI cost dashboard.
                    </div>
                    <?php if (isset($component)) { $__componentOriginal6330f08526bbb3ce2a0da37da512a11f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6330f08526bbb3ce2a0da37da512a11f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament::components.button.index','data' => ['wire:click' => 'generateMissing','wire:loading.attr' => 'disabled','icon' => 'heroicon-o-sparkles']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:click' => 'generateMissing','wire:loading.attr' => 'disabled','icon' => 'heroicon-o-sparkles']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                        <span wire:loading.remove wire:target="generateMissing">Generate missing</span>
                        <span wire:loading wire:target="generateMissing">Writing…</span>
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
        </div>

        
        <div class="ist-panel">
            <div class="ist-toolbar">
                <input type="search" wire:model.live.debounce.400ms="search" class="ist-input" placeholder="Search by product or filename…">
                <label class="ist-check">
                    <input type="checkbox" wire:model.live="missingOnly">
                    Missing alt/title only
                </label>
                <span style="margin-left:auto; font-size:.75rem; color:#9ca3af"><?php echo e($images->total()); ?> image(s)</span>
            </div>
            <div class="ist-table-wrap">
                <table class="ist-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Product / file</th>
                            <th>Alt text <span style="text-transform:none">(≤<?php echo e(\App\Services\Seo\ImageSeoRules::ALT_MAX); ?>)</span></th>
                            <th>Title <span style="text-transform:none">(≤<?php echo e(\App\Services\Seo\ImageSeoRules::TITLE_MAX); ?>)</span></th>
                            <th>Caption <span style="text-transform:none">(≤<?php echo e(\App\Services\Seo\ImageSeoRules::CAPTION_MAX); ?>)</span></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $images; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $image): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'img-'.e($image->id).''; ?>wire:key="img-<?php echo e($image->id); ?>">
                                <td>
                                    <a href="<?php echo e($image->url()); ?>" target="_blank">
                                        <img src="<?php echo e($image->url()); ?>" alt="" class="ist-thumb" loading="lazy" width="56" height="56">
                                    </a>
                                </td>
                                <td class="ist-product">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($image->product): ?>
                                        <a href="<?php echo e(\App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $image->product])); ?>"><?php echo e($image->product->name); ?></a>
                                    <?php else: ?>
                                        <span style="color:#9ca3af">— unassigned —</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <p class="ist-file"><?php echo e(basename($image->path)); ?></p>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($lint[$image->id] ?? []) !== []): ?>
                                        <p class="ist-issues">⚠ <?php echo e(implode(' ', $lint[$image->id])); ?></p>
                                    <?php else: ?>
                                        <p class="ist-ok">✓ passes the rulebook</p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td>
                                    <input type="text" class="ist-meta-input <?php echo e(blank($image->alt) ? 'missing' : ''); ?>"
                                           value="<?php echo e($image->alt); ?>"
                                           maxlength="<?php echo e(\App\Services\Seo\ImageSeoRules::ALT_MAX); ?>"
                                           wire:change="updateMeta(<?php echo e($image->id); ?>, 'alt', $event.target.value)"
                                           placeholder="Describe what the image shows…">
                                    <span class="ist-count"><?php echo e(mb_strlen((string) $image->alt)); ?>/<?php echo e(\App\Services\Seo\ImageSeoRules::ALT_MAX); ?></span>
                                </td>
                                <td>
                                    <input type="text" class="ist-meta-input <?php echo e(blank($image->title) ? 'missing' : ''); ?>"
                                           value="<?php echo e($image->title); ?>"
                                           maxlength="<?php echo e(\App\Services\Seo\ImageSeoRules::TITLE_MAX); ?>"
                                           wire:change="updateMeta(<?php echo e($image->id); ?>, 'title', $event.target.value)"
                                           placeholder="Hover tooltip…">
                                </td>
                                <td>
                                    <input type="text" class="ist-meta-input"
                                           value="<?php echo e($image->caption); ?>"
                                           maxlength="<?php echo e(\App\Services\Seo\ImageSeoRules::CAPTION_MAX); ?>"
                                           wire:change="updateMeta(<?php echo e($image->id); ?>, 'caption', $event.target.value)"
                                           placeholder="Buyer-facing caption (optional)…">
                                </td>
                                <td>
                                    <div class="ist-row-actions">
                                        <button type="button" class="ist-btn ist-btn-ai"
                                                wire:click="generateForImage(<?php echo e($image->id); ?>)"
                                                wire:loading.attr="disabled">✨ AI write</button>
                                    </div>
                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr><td colspan="6" class="ist-empty-row">No images<?php echo e(trim($search) !== '' ? ' matching “'.$search.'”' : ''); ?>.</td></tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($images->hasPages()): ?>
                <div class="ist-pager">
                    <button type="button" wire:click="previousPage" <?php if($images->onFirstPage()): echo 'disabled'; endif; ?>>← Previous</button>
                    <span>Page <?php echo e($images->currentPage()); ?> of <?php echo e($images->lastPage()); ?></span>
                    <button type="button" wire:click="nextPage" <?php if(! $images->hasMorePages()): echo 'disabled'; endif; ?>>Next →</button>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div class="ist-panel">
            <div class="ist-panel-body">
                <h3>Find &amp; replace</h3>
                <p class="ist-help">
                    Bulk-fix alt, title, and caption text across every product image.
                    Example: replace "IMG_2026" with "IQOS TEREA Amber Flavor UAE". Case-sensitive.
                </p>

                <div class="ist-fields-grid">
                    <label class="ist-field">
                        <span>Find</span>
                        <input type="text" class="ist-input" wire:model.live.debounce.400ms="findText" wire:change="preview" placeholder="IMG_2026">
                    </label>
                    <label class="ist-field">
                        <span>Replace with</span>
                        <input type="text" class="ist-input" wire:model="replaceText" placeholder="IQOS TEREA Amber Flavor UAE">
                    </label>
                </div>

                <div class="ist-controls">
                    <div class="ist-checks">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['alt' => 'Alt text', 'title' => 'Title', 'caption' => 'Caption']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <label class="ist-check">
                                <input type="checkbox" wire:model="fields" value="<?php echo e($field); ?>">
                                <?php echo e($label); ?>

                            </label>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>

                    <div class="ist-right">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($previewCount !== null): ?>
                            <span class="ist-match <?php echo e($previewCount === 0 ? 'zero' : ''); ?>"><?php echo e($previewCount); ?> image(s) match</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if (isset($component)) { $__componentOriginal6330f08526bbb3ce2a0da37da512a11f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6330f08526bbb3ce2a0da37da512a11f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament::components.button.index','data' => ['color' => 'gray','wire:click' => 'preview','wire:loading.attr' => 'disabled']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['color' => 'gray','wire:click' => 'preview','wire:loading.attr' => 'disabled']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
Preview <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6330f08526bbb3ce2a0da37da512a11f)): ?>
<?php $attributes = $__attributesOriginal6330f08526bbb3ce2a0da37da512a11f; ?>
<?php unset($__attributesOriginal6330f08526bbb3ce2a0da37da512a11f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6330f08526bbb3ce2a0da37da512a11f)): ?>
<?php $component = $__componentOriginal6330f08526bbb3ce2a0da37da512a11f; ?>
<?php unset($__componentOriginal6330f08526bbb3ce2a0da37da512a11f); ?>
<?php endif; ?>
                        <?php if (isset($component)) { $__componentOriginal6330f08526bbb3ce2a0da37da512a11f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6330f08526bbb3ce2a0da37da512a11f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament::components.button.index','data' => ['wire:click' => 'apply','wire:loading.attr' => 'disabled','wire:confirm' => 'Replace \''.e($findText).'\' in the selected fields across all matching product images?']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:click' => 'apply','wire:loading.attr' => 'disabled','wire:confirm' => 'Replace \''.e($findText).'\' in the selected fields across all matching product images?']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                            Replace all
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
                        <?php if (isset($component)) { $__componentOriginal6330f08526bbb3ce2a0da37da512a11f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6330f08526bbb3ce2a0da37da512a11f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament::components.button.index','data' => ['color' => 'gray','wire:click' => 'autoFill','wire:loading.attr' => 'disabled','icon' => 'heroicon-o-bolt']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['color' => 'gray','wire:click' => 'autoFill','wire:loading.attr' => 'disabled','icon' => 'heroicon-o-bolt']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                            Auto-fill empty from product names
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
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/filament/pages/image-seo-tools.blade.php ENDPATH**/ ?>