<?php
    $limit = (int) $section->setting('limit', 3);
    $posts = \App\Models\Post::published()->with(['author', 'category'])->latest('published_at')->take($limit)->get();
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($posts->isNotEmpty()): ?>
    <section class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm" aria-label="<?php echo e($section->title ?? 'From the blog'); ?>">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
            <h2 class="flex items-center gap-2 truncate text-base font-bold text-gray-900 sm:text-lg">
                <span class="h-5 w-1.5 shrink-0 rounded-full bg-teal-600"></span><?php echo e($section->title ?? 'From the blog'); ?>

            </h2>
            <a href="<?php echo e(route('blog.index')); ?>" class="shrink-0 text-sm font-semibold text-teal-700 hover:underline">All posts →</a>
        </div>
        <div class="grid gap-3 p-4 sm:grid-cols-<?php echo e(min($limit, 3)); ?>">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $posts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $post): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <a href="<?php echo e($post->url()); ?>" class="group rounded-lg border border-gray-100 bg-gray-50/60 p-4 transition hover:border-teal-200 hover:bg-white hover:shadow-sm">
                    <p class="text-xs text-gray-500"><?php echo e(optional($post->published_at)->format('M j, Y')); ?> · <?php echo e($post->reading_time); ?> min read</p>
                    <h3 class="mt-1.5 text-sm font-bold text-gray-900 group-hover:text-teal-700"><?php echo e($post->title); ?></h3>
                    <p class="mt-1.5 text-xs text-gray-600"><?php echo e(str($post->excerpt)->limit(120)); ?></p>
                </a>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    </section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/partials/homepage/blog_posts.blade.php ENDPATH**/ ?>