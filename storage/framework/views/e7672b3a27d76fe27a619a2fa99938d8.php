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
        .sf { max-width: 44rem; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 1.25rem; }

        .sf-hero { border-radius: 1.25rem; padding: 1.75rem; background: linear-gradient(135deg, rgba(15,118,110,.10), rgba(15,118,110,.03) 60%, transparent); box-shadow: inset 0 0 0 1px rgba(15,118,110,.12); }
        .dark .sf-hero { background: linear-gradient(135deg, rgba(45,212,191,.10), rgba(45,212,191,.02) 60%, transparent); box-shadow: inset 0 0 0 1px rgba(255,255,255,.08); }
        .sf-hero-top { display: flex; align-items: center; gap: .75rem; }
        .sf-hero-badge { display: flex; height: 2.5rem; width: 2.5rem; align-items: center; justify-content: center; border-radius: .85rem; background: rgba(15,118,110,.15); color: #0f766e; flex: none; }
        .dark .sf-hero-badge { color: #2dd4bf; }
        .sf-hero-badge svg { width: 1.4rem; height: 1.4rem; }
        .sf-title { font-size: 1.15rem; font-weight: 800; color: #0f172a; line-height: 1.2; }
        .dark .sf-title { color: #fff; }
        .sf-sub { font-size: .85rem; color: #64748b; margin-top: .1rem; }
        .dark .sf-sub { color: #94a3b8; }

        .sf-search { position: relative; margin-top: 1.25rem; }
        .sf-search-icon { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; }
        .sf-search-icon svg { width: 1.25rem; height: 1.25rem; }
        .sf-search input { width: 100%; border: 0; border-radius: .85rem; background: #fff; padding: .85rem 1rem .85rem 2.9rem; font-size: 1rem; color: #0f172a; box-shadow: 0 1px 2px rgba(15,23,42,.06), inset 0 0 0 1px rgba(15,23,42,.10); transition: box-shadow .15s ease; }
        .sf-search input:focus { outline: none; box-shadow: inset 0 0 0 2px #0f766e; }
        .dark .sf-search input { background: rgba(255,255,255,.05); color: #fff; box-shadow: inset 0 0 0 1px rgba(255,255,255,.10); }
        .dark .sf-search input:focus { box-shadow: inset 0 0 0 2px #2dd4bf; }
        .sf-spin { position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: #0f766e; }
        .sf-spin svg { width: 1.25rem; height: 1.25rem; }

        .sf-suggest { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; margin-top: 1rem; }
        .sf-suggest-label { font-size: .72rem; font-weight: 600; color: #94a3b8; }
        .sf-chip { border: 0; cursor: pointer; border-radius: 999px; background: #fff; padding: .3rem .75rem; font-size: .75rem; font-weight: 500; color: #475569; box-shadow: inset 0 0 0 1px rgba(15,23,42,.10); transition: all .12s ease; }
        .sf-chip:hover { background: #f0fdfa; color: #0f766e; box-shadow: inset 0 0 0 1px rgba(15,118,110,.45); }
        .dark .sf-chip { background: rgba(255,255,255,.05); color: #cbd5e1; box-shadow: inset 0 0 0 1px rgba(255,255,255,.10); }
        .dark .sf-chip:hover { background: rgba(45,212,191,.10); color: #5eead4; }

        .sf-count { font-size: .85rem; color: #64748b; padding: 0 .25rem; }
        .dark .sf-count { color: #94a3b8; }
        .sf-count b { color: #334155; font-weight: 700; }
        .dark .sf-count b { color: #e2e8f0; }

        .sf-group { display: flex; flex-direction: column; gap: .5rem; }
        .sf-group + .sf-group { margin-top: 1.25rem; }
        .sf-group-head { display: flex; align-items: center; gap: .45rem; padding: 0 .25rem; }
        .sf-group-head svg { width: 1rem; height: 1rem; color: #94a3b8; }
        .sf-group-head h3 { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #64748b; }
        .dark .sf-group-head h3 { color: #94a3b8; }
        .sf-group-count { border-radius: 999px; background: #f1f5f9; padding: 0 .4rem; font-size: .65rem; font-weight: 700; color: #64748b; }
        .dark .sf-group-count { background: rgba(255,255,255,.08); color: #94a3b8; }

        .sf-list { border-radius: 1rem; overflow: hidden; background: #fff; box-shadow: 0 4px 16px rgba(15,23,42,.05), inset 0 0 0 1px rgba(15,23,42,.06); }
        .dark .sf-list { background: rgba(255,255,255,.03); box-shadow: inset 0 0 0 1px rgba(255,255,255,.09); }
        .sf-item { display: flex; align-items: center; gap: .8rem; padding: .8rem 1rem; text-decoration: none; border-top: 1px solid rgba(15,23,42,.05); transition: background .12s ease; }
        .sf-item:first-child { border-top: 0; }
        .dark .sf-item { border-color: rgba(255,255,255,.05); }
        .sf-item:hover { background: rgba(15,118,110,.05); }
        .dark .sf-item:hover { background: rgba(255,255,255,.04); }
        .sf-item-icon { display: flex; height: 2.25rem; width: 2.25rem; flex: none; align-items: center; justify-content: center; border-radius: .65rem; background: #f1f5f9; color: #64748b; }
        .dark .sf-item-icon { background: rgba(255,255,255,.08); color: #94a3b8; }
        .sf-item-icon.is-setting { background: rgba(15,118,110,.12); color: #0f766e; }
        .dark .sf-item-icon.is-setting { color: #2dd4bf; }
        .sf-item-icon svg { width: 1.25rem; height: 1.25rem; }
        .sf-item-main { min-width: 0; flex: 1; }
        .sf-item-titlerow { display: flex; align-items: center; gap: .5rem; }
        .sf-item-title { font-weight: 600; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dark .sf-item-title { color: #fff; }
        .sf-badge { flex: none; border-radius: 999px; background: rgba(15,118,110,.10); padding: .05rem .45rem; font-size: .58rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #0f766e; }
        .dark .sf-badge { background: rgba(45,212,191,.12); color: #5eead4; }
        .sf-item-loc { margin-top: .1rem; font-size: .75rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .dark .sf-item-loc { color: #94a3b8; }
        .sf-item-loc .sep { color: #cbd5e1; }
        .dark .sf-item-loc .sep { color: #475569; }
        .sf-arrow { flex: none; color: #cbd5e1; transition: transform .12s ease, color .12s ease; }
        .sf-arrow svg { width: 1rem; height: 1rem; }
        .sf-item:hover .sf-arrow { color: #0f766e; transform: translateX(2px); }
        .dark .sf-item:hover .sf-arrow { color: #2dd4bf; }

        .sf-empty { border-radius: 1.25rem; padding: 3.5rem 1.5rem; text-align: center; background: #f8fafc; box-shadow: inset 0 0 0 1px rgba(15,23,42,.06); }
        .dark .sf-empty { background: rgba(255,255,255,.03); box-shadow: inset 0 0 0 1px rgba(255,255,255,.08); }
        .sf-empty-badge { display: inline-flex; height: 3rem; width: 3rem; align-items: center; justify-content: center; border-radius: 999px; background: #e2e8f0; color: #94a3b8; }
        .dark .sf-empty-badge { background: rgba(255,255,255,.08); }
        .sf-empty-badge svg { width: 1.5rem; height: 1.5rem; }
        .sf-empty h4 { margin-top: 1rem; font-weight: 600; color: #334155; }
        .dark .sf-empty h4 { color: #e2e8f0; }
        .sf-empty p { margin-top: .25rem; font-size: .85rem; color: #64748b; }
    </style>

    <div class="sf">
        
        <div class="sf-hero">
            <div class="sf-hero-top">
                <span class="sf-hero-badge"><?php if (isset($component)) { $__componentOriginalbfc641e0710ce04e5fe02876ffc6f950 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament::components.icon','data' => ['icon' => 'heroicon-o-magnifying-glass']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'heroicon-o-magnifying-glass']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950)): ?>
<?php $attributes = $__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950; ?>
<?php unset($__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbfc641e0710ce04e5fe02876ffc6f950)): ?>
<?php $component = $__componentOriginalbfc641e0710ce04e5fe02876ffc6f950; ?>
<?php unset($__componentOriginalbfc641e0710ce04e5fe02876ffc6f950); ?>
<?php endif; ?></span>
                <div>
                    <div class="sf-title">Find a setting</div>
                    <div class="sf-sub">Search every menu and setting — jump straight to it.</div>
                </div>
            </div>

            <div class="sf-search">
                <span class="sf-search-icon"><?php if (isset($component)) { $__componentOriginalbfc641e0710ce04e5fe02876ffc6f950 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament::components.icon','data' => ['icon' => 'heroicon-o-magnifying-glass']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'heroicon-o-magnifying-glass']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950)): ?>
<?php $attributes = $__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950; ?>
<?php unset($__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbfc641e0710ce04e5fe02876ffc6f950)): ?>
<?php $component = $__componentOriginalbfc641e0710ce04e5fe02876ffc6f950; ?>
<?php unset($__componentOriginalbfc641e0710ce04e5fe02876ffc6f950); ?>
<?php endif; ?></span>
                <input
                    type="search"
                    wire:model.live.debounce.250ms="q"
                    autofocus
                    placeholder="Try “maintenance”, “currency”, “permalink”, “analytics”…"
                />
                <span class="sf-spin" wire:loading wire:target="q"><?php if (isset($component)) { $__componentOriginalbef7c2371a870b1887ec3741fe311a10 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbef7c2371a870b1887ec3741fe311a10 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament::components.loading-indicator','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::loading-indicator'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbef7c2371a870b1887ec3741fe311a10)): ?>
<?php $attributes = $__attributesOriginalbef7c2371a870b1887ec3741fe311a10; ?>
<?php unset($__attributesOriginalbef7c2371a870b1887ec3741fe311a10); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbef7c2371a870b1887ec3741fe311a10)): ?>
<?php $component = $__componentOriginalbef7c2371a870b1887ec3741fe311a10; ?>
<?php unset($__componentOriginalbef7c2371a870b1887ec3741fe311a10); ?>
<?php endif; ?></span>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($q === ''): ?>
                <div class="sf-suggest">
                    <span class="sf-suggest-label">Popular:</span>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Filament\Pages\SettingsFinder::SUGGESTIONS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $suggestion): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <button type="button" class="sf-chip" wire:click="$set('q', <?php echo \Illuminate\Support\Js::from($suggestion)->toHtml() ?>)"><?php echo e($suggestion); ?></button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div class="sf-count">
            <b><?php echo e(count($this->results)); ?></b> result<?php echo e(count($this->results) === 1 ? '' : 's'); ?><?php echo e($q !== '' ? ' for “'.$q.'”' : ''); ?>

        </div>

        
        <div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = collect($this->results)->groupBy('group'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group => $items): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <section class="sf-group">
                    <div class="sf-group-head">
                        <?php if (isset($component)) { $__componentOriginalbfc641e0710ce04e5fe02876ffc6f950 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament::components.icon','data' => ['icon' => \App\Filament\Pages\SettingsFinder::groupIcon($group)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(\App\Filament\Pages\SettingsFinder::groupIcon($group))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950)): ?>
<?php $attributes = $__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950; ?>
<?php unset($__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbfc641e0710ce04e5fe02876ffc6f950)): ?>
<?php $component = $__componentOriginalbfc641e0710ce04e5fe02876ffc6f950; ?>
<?php unset($__componentOriginalbfc641e0710ce04e5fe02876ffc6f950); ?>
<?php endif; ?>
                        <h3><?php echo e($group); ?></h3>
                        <span class="sf-group-count"><?php echo e(count($items)); ?></span>
                    </div>
                    <div class="sf-list">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <a href="<?php echo e($item['url']); ?>" class="sf-item">
                                <span class="sf-item-icon <?php echo e($item['type'] === 'setting' ? 'is-setting' : ''); ?>">
                                    <?php if (isset($component)) { $__componentOriginalbfc641e0710ce04e5fe02876ffc6f950 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament::components.icon','data' => ['icon' => $item['type'] === 'setting' ? 'heroicon-o-adjustments-horizontal' : \App\Filament\Pages\SettingsFinder::groupIcon($item['group'])]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($item['type'] === 'setting' ? 'heroicon-o-adjustments-horizontal' : \App\Filament\Pages\SettingsFinder::groupIcon($item['group']))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950)): ?>
<?php $attributes = $__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950; ?>
<?php unset($__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbfc641e0710ce04e5fe02876ffc6f950)): ?>
<?php $component = $__componentOriginalbfc641e0710ce04e5fe02876ffc6f950; ?>
<?php unset($__componentOriginalbfc641e0710ce04e5fe02876ffc6f950); ?>
<?php endif; ?>
                                </span>
                                <span class="sf-item-main">
                                    <span class="sf-item-titlerow">
                                        <span class="sf-item-title"><?php echo e($item['title']); ?></span>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item['type'] === 'setting'): ?><span class="sf-badge">Setting</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </span>
                                    <span class="sf-item-loc"><?php echo e($item['group']); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item['page']): ?> <span class="sep">›</span> <?php echo e($item['page']); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item['section']): ?><span class="sep">›</span> <?php echo e($item['section']); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span>
                                </span>
                                <span class="sf-arrow"><?php if (isset($component)) { $__componentOriginalbfc641e0710ce04e5fe02876ffc6f950 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament::components.icon','data' => ['icon' => 'heroicon-o-arrow-right']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'heroicon-o-arrow-right']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950)): ?>
<?php $attributes = $__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950; ?>
<?php unset($__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbfc641e0710ce04e5fe02876ffc6f950)): ?>
<?php $component = $__componentOriginalbfc641e0710ce04e5fe02876ffc6f950; ?>
<?php unset($__componentOriginalbfc641e0710ce04e5fe02876ffc6f950); ?>
<?php endif; ?></span>
                            </a>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                </section>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <div class="sf-empty">
                    <span class="sf-empty-badge"><?php if (isset($component)) { $__componentOriginalbfc641e0710ce04e5fe02876ffc6f950 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'filament::components.icon','data' => ['icon' => 'heroicon-o-magnifying-glass']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('filament::icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'heroicon-o-magnifying-glass']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950)): ?>
<?php $attributes = $__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950; ?>
<?php unset($__attributesOriginalbfc641e0710ce04e5fe02876ffc6f950); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbfc641e0710ce04e5fe02876ffc6f950)): ?>
<?php $component = $__componentOriginalbfc641e0710ce04e5fe02876ffc6f950; ?>
<?php unset($__componentOriginalbfc641e0710ce04e5fe02876ffc6f950); ?>
<?php endif; ?></span>
                    <h4>No matches for “<?php echo e($q); ?>”</h4>
                    <p>Try a simpler word like “email”, “tax”, “url” or “backup”.</p>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/filament/pages/settings-finder.blade.php ENDPATH**/ ?>