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
        .mlb { display: flex; flex-direction: column; gap: 1.5rem; }
        .mlb-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; border-radius: 1rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); padding: .9rem 1.25rem; }
        .dark .mlb-toolbar { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .mlb-input { border-radius: .6rem; border: 1px solid #d1d5db; padding: .5rem .75rem; font-size: .85rem; background: #fff; color: #111827; min-width: 16rem; }
        .dark .mlb-input { background: rgba(255,255,255,.05); border-color: rgba(255,255,255,.12); color: #f9fafb; }
        .mlb-check { display: flex; align-items: center; gap: .45rem; font-size: .85rem; color: #374151; cursor: pointer; }
        .dark .mlb-check { color: #d1d5db; }
        .mlb-count { font-size: .75rem; color: #9ca3af; }

        .mlb-views { margin-left: auto; display: inline-flex; border: 1px solid #d1d5db; border-radius: .6rem; overflow: hidden; }
        .dark .mlb-views { border-color: rgba(255,255,255,.12); }
        .mlb-views button { border: 0; background: #fff; padding: .45rem .8rem; font-size: .75rem; font-weight: 600; color: #6b7280; cursor: pointer; display: inline-flex; align-items: center; gap: .35rem; }
        .dark .mlb-views button { background: rgba(255,255,255,.05); color: #9ca3af; }
        .mlb-views button.active { background: #0f766e; color: #fff; }
        .mlb-views svg { width: 14px; height: 14px; }

        /* Tiles */
        .mlb-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
        .mlb-tile { border-radius: .8rem; overflow: hidden; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 2px 8px rgba(15,23,42,.05); cursor: pointer; transition: box-shadow .15s, transform .15s; text-align: left; padding: 0; }
        .mlb-tile:hover { box-shadow: 0 8px 20px rgba(15,23,42,.12); transform: translateY(-2px); }
        .dark .mlb-tile { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); }
        .mlb-tile img { width: 100%; aspect-ratio: 1; object-fit: cover; background: #f3f4f6; display: block; }
        .mlb-tile-meta { padding: .55rem .7rem; }
        .mlb-tile-name { font-size: .72rem; font-weight: 600; color: #374151; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dark .mlb-tile-name { color: #d1d5db; }
        .mlb-tile-sub { font-size: .65rem; color: #9ca3af; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mlb-flag { display: inline-block; border-radius: 999px; font-size: .58rem; font-weight: 800; text-transform: uppercase; padding: .1rem .45rem; }
        .mlb-flag-featured { background: #ccfbf1; color: #115e59; }
        .mlb-flag-noalt { background: #fee2e2; color: #b91c1c; }
        .dark .mlb-flag-featured { background: rgba(99,102,241,.2); color: #5eead4; }
        .dark .mlb-flag-noalt { background: rgba(239,68,68,.15); color: #f87171; }

        /* List */
        .mlb-panel { border-radius: 1rem; overflow: hidden; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); }
        .dark .mlb-panel { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .mlb-table { width: 100%; font-size: .85rem; border-collapse: collapse; }
        .mlb-table thead tr { background: #f9fafb; text-align: left; }
        .dark .mlb-table thead tr { background: rgba(255,255,255,.04); }
        .mlb-table th { padding: .6rem 1.25rem; font-size: .66rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; }
        .mlb-table td { padding: .65rem 1.25rem; border-top: 1px solid rgba(0,0,0,.04); color: #374151; vertical-align: middle; }
        .dark .mlb-table td { border-color: rgba(255,255,255,.05); color: #d1d5db; }
        .mlb-thumb-sm { width: 44px; height: 44px; border-radius: .5rem; object-fit: cover; background: #f3f4f6; }
        .mlb-link { color: #0f766e; font-weight: 600; text-decoration: none; }
        .mlb-link:hover { text-decoration: underline; }
        .mlb-muted { color: #9ca3af; font-size: .75rem; }

        /* Edit panel */
        .mlb-edit { border: 2px solid #5eead4; border-radius: 1rem; background: #fff; box-shadow: 0 12px 32px rgba(79,70,229,.12); overflow: hidden; }
        .dark .mlb-edit { background: #111827; border-color: rgba(129,140,248,.4); }
        .mlb-edit-head { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.5rem; border-bottom: 1px solid rgba(0,0,0,.05); }
        .dark .mlb-edit-head { border-color: rgba(255,255,255,.08); }
        .mlb-edit-title { font-weight: 800; color: #111827; }
        .dark .mlb-edit-title { color: #f9fafb; }
        .mlb-close { background: none; border: 0; cursor: pointer; border-radius: .5rem; padding: .4rem; color: #9ca3af; line-height: 0; }
        .mlb-close:hover { background: rgba(0,0,0,.05); color: #4b5563; }
        .mlb-close svg { width: 18px; height: 18px; }
        .mlb-edit-grid { display: grid; gap: 1.5rem; padding: 1.5rem; }
        @media (min-width: 860px) { .mlb-edit-grid { grid-template-columns: 280px 1fr; } }
        .mlb-preview img { width: 100%; border-radius: .8rem; border: 1px solid rgba(0,0,0,.07); background: #f3f4f6; }
        .mlb-facts { margin-top: .8rem; font-size: .75rem; color: #6b7280; display: flex; flex-direction: column; gap: .3rem; }
        .mlb-facts strong { color: #374151; font-weight: 600; }
        .dark .mlb-facts strong { color: #d1d5db; }
        .mlb-form { display: flex; flex-direction: column; gap: .9rem; }
        .mlb-field span { display: block; font-size: .78rem; font-weight: 600; color: #374151; margin-bottom: .3rem; }
        .dark .mlb-field span { color: #d1d5db; }
        .mlb-field input { width: 100%; border-radius: .6rem; border: 1px solid #d1d5db; padding: .55rem .8rem; font-size: .85rem; background: #fff; color: #111827; }
        .dark .mlb-field input { background: rgba(255,255,255,.05); border-color: rgba(255,255,255,.12); color: #f9fafb; }
        .mlb-field input[readonly] { background: #f3f4f6; color: #6b7280; cursor: not-allowed; }
        .dark .mlb-field input[readonly] { background: rgba(255,255,255,.03); }
        .mlb-hint { font-size: .68rem; color: #9ca3af; margin-top: .25rem; }
        .mlb-lint { border-radius: .6rem; background: #fffbeb; border: 1px solid #fde68a; color: #b45309; padding: .6rem .8rem; font-size: .75rem; }
        .dark .mlb-lint { background: rgba(245,158,11,.08); border-color: rgba(245,158,11,.35); }
        .mlb-actions-row { display: flex; gap: .75rem; justify-content: flex-end; }

        .mlb-pager { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .9rem 1.25rem; font-size: .8rem; color: #6b7280; }
        .mlb-pager button { border: 1px solid #d1d5db; background: #fff; border-radius: .5rem; padding: .35rem .9rem; font-size: .78rem; cursor: pointer; color: #374151; }
        .mlb-pager button:disabled { opacity: .4; cursor: default; }
        .dark .mlb-pager button { background: rgba(255,255,255,.05); border-color: rgba(255,255,255,.12); color: #d1d5db; }
        .mlb-empty { padding: 3rem; text-align: center; color: #9ca3af; }
    </style>

    <div class="mlb">
        
        <div class="mlb-toolbar">
            <input type="search" class="mlb-input" wire:model.live.debounce.400ms="search" placeholder="Search filename, alt text or product…">
            <label class="mlb-check">
                <input type="checkbox" wire:model.live="missingOnly">
                Missing alt only
            </label>
            <span class="mlb-count"><?php echo e($images->total()); ?> of <?php echo e($totalImages); ?> image(s)</span>
            <div class="mlb-views">
                <button type="button" class="<?php echo e($view_mode === 'tiles' ? 'active' : ''); ?>" wire:click="setView('tiles')">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"/></svg>
                    Tiles
                </button>
                <button type="button" class="<?php echo e($view_mode === 'list' ? 'active' : ''); ?>" wire:click="setView('list')">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                    List
                </button>
            </div>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($editing): ?>
            <div class="mlb-edit">
                <div class="mlb-edit-head">
                    <span class="mlb-edit-title">Edit image details</span>
                    <button type="button" class="mlb-close" wire:click="closeEdit" title="Close">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="mlb-edit-grid">
                    <div class="mlb-preview">
                        <img src="<?php echo e($editing->url); ?>" alt="">
                        <div class="mlb-facts">
                            <span><strong>File:</strong> <?php echo e($editing->filename); ?></span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($editing->dimensions): ?><span><strong>Dimensions:</strong> <?php echo e($editing->dimensions); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($editing->sizeKb !== null): ?><span><strong>Size:</strong> <?php echo e(number_format($editing->sizeKb)); ?> KB</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <span>
                                <strong>Product:</strong>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($editing->model->product): ?>
                                    <a class="mlb-link" href="<?php echo e(\App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $editing->model->product])); ?>"><?php echo e($editing->model->product->name); ?></a>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($editing->isFeatured): ?><span class="mlb-flag mlb-flag-featured">featured</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php else: ?>
                                    <span class="mlb-muted">unassigned</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <div class="mlb-form">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($editing->lint !== []): ?>
                            <div class="mlb-lint">⚠ <?php echo e(implode(' ', $editing->lint)); ?></div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <label class="mlb-field">
                            <span>Alt text (≤<?php echo e(\App\Services\Seo\ImageSeoRules::ALT_MAX); ?>)</span>
                            <input type="text" wire:model="editAlt" maxlength="<?php echo e(\App\Services\Seo\ImageSeoRules::ALT_MAX); ?>" placeholder="Describe what the image shows…">
                        </label>
                        <label class="mlb-field">
                            <span>Title — hover tooltip (≤<?php echo e(\App\Services\Seo\ImageSeoRules::TITLE_MAX); ?>)</span>
                            <input type="text" wire:model="editTitle" maxlength="<?php echo e(\App\Services\Seo\ImageSeoRules::TITLE_MAX); ?>">
                        </label>
                        <label class="mlb-field">
                            <span>Caption (≤<?php echo e(\App\Services\Seo\ImageSeoRules::CAPTION_MAX); ?>)</span>
                            <input type="text" wire:model="editCaption" maxlength="<?php echo e(\App\Services\Seo\ImageSeoRules::CAPTION_MAX); ?>">
                        </label>
                        <label class="mlb-field">
                            <span>Sort order</span>
                            <input type="number" wire:model="editSort" min="0" style="max-width: 8rem">
                        </label>
                        <label class="mlb-field">
                            <span>Permalink — locked</span>
                            <input type="text" value="<?php echo e($editing->url); ?>" readonly onclick="this.select()">
                            <p class="mlb-hint">Defined once from the original file name at upload ("terea kazakhstan amber.jpg" → /terea-kazakhstan-amber.jpg) and never changes — stable URLs keep Google Images rankings. Name files properly before uploading.</p>
                        </label>
                        <div class="mlb-actions-row">
                            <?php if (isset($component)) { $__componentOriginal6330f08526bbb3ce2a0da37da512a11f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6330f08526bbb3ce2a0da37da512a11f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament::components.button.index','data' => ['color' => 'gray','wire:click' => 'closeEdit']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['color' => 'gray','wire:click' => 'closeEdit']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
Cancel <?php echo $__env->renderComponent(); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament::components.button.index','data' => ['wire:click' => 'save','wire:loading.attr' => 'disabled']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:click' => 'save','wire:loading.attr' => 'disabled']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
Save details <?php echo $__env->renderComponent(); ?>
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
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($view_mode === 'tiles'): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($images->isEmpty()): ?>
                <div class="mlb-panel"><div class="mlb-empty">No images<?php echo e(trim($search) !== '' ? ' matching “'.$search.'”' : ''); ?>.</div></div>
            <?php else: ?>
                <div class="mlb-grid">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $images; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $image): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <button type="button" class="mlb-tile" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'tile-'.e($image->id).''; ?>wire:key="tile-<?php echo e($image->id); ?>" wire:click="edit(<?php echo e($image->id); ?>)">
                            <img src="<?php echo e($image->url()); ?>" alt="" loading="lazy">
                            <div class="mlb-tile-meta">
                                <p class="mlb-tile-name"><?php echo e($image->product?->name ?? basename($image->path)); ?></p>
                                <p class="mlb-tile-sub"><?php echo e(basename($image->path)); ?></p>
                                <div style="margin-top:.3rem; display:flex; gap:.3rem; flex-wrap:wrap">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($image->product?->featured_image === $image->path): ?>
                                        <span class="mlb-flag mlb-flag-featured">featured</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(blank($image->alt)): ?>
                                        <span class="mlb-flag mlb-flag-noalt">no alt</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </div>
                        </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php else: ?>
            
            <div class="mlb-panel">
                <table class="mlb-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>File</th>
                            <th>Product</th>
                            <th>Alt text</th>
                            <th>Title</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $images; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $image): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'row-'.e($image->id).''; ?>wire:key="row-<?php echo e($image->id); ?>">
                                <td><img src="<?php echo e($image->url()); ?>" alt="" class="mlb-thumb-sm" loading="lazy"></td>
                                <td>
                                    <?php echo e(basename($image->path)); ?>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($image->product?->featured_image === $image->path): ?>
                                        <span class="mlb-flag mlb-flag-featured">featured</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($image->product): ?>
                                        <a class="mlb-link" href="<?php echo e(\App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $image->product])); ?>"><?php echo e($image->product->name); ?></a>
                                    <?php else: ?>
                                        <span class="mlb-muted">unassigned</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td><?php echo e($image->alt ?: ''); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(blank($image->alt)): ?><span class="mlb-flag mlb-flag-noalt">missing</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                                <td class="mlb-muted"><?php echo e($image->title ?: '—'); ?></td>
                                <td><button type="button" class="mlb-link" style="background:none;border:0;cursor:pointer;font-size:.8rem" wire:click="edit(<?php echo e($image->id); ?>)">Edit</button></td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr><td colspan="6" class="mlb-empty">No images<?php echo e(trim($search) !== '' ? ' matching “'.$search.'”' : ''); ?>.</td></tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($images->hasPages()): ?>
            <div class="mlb-panel mlb-pager">
                <button type="button" wire:click="previousPage" <?php if($images->onFirstPage()): echo 'disabled'; endif; ?>>← Previous</button>
                <span>Page <?php echo e($images->currentPage()); ?> of <?php echo e($images->lastPage()); ?></span>
                <button type="button" wire:click="nextPage" <?php if(! $images->hasMorePages()): echo 'disabled'; endif; ?>>Next →</button>
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
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/filament/pages/media-library.blade.php ENDPATH**/ ?>