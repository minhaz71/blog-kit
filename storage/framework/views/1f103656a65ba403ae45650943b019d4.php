<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-md px-4 py-10">
    <h1 class="text-2xl font-bold">Two-factor authentication</h1>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
        <div class="mt-4 rounded-lg bg-green-50 p-3 text-sm text-green-800"><?php echo e(session('success')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('recovery_codes')): ?>
        <div class="mt-4 rounded-lg border-2 border-amber-400 bg-amber-50 p-4">
            <p class="text-sm font-semibold text-amber-900">Save these one-time recovery codes.</p>
            <p class="mt-1 text-xs text-amber-800">If you lose your authenticator you can use these to sign in — one per code. Once you leave this page, you won't see them again.</p>
            <div class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = session('recovery_codes'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $code): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <span class="rounded bg-white px-2 py-1"><?php echo e($code); ?></span>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $isConfirmed): ?>
        <p class="mt-4 text-gray-600">
            Scan the QR code with an authenticator app (Google Authenticator, Authy, 1Password), then type the 6-digit code it shows to confirm.
        </p>
        <div class="mt-4 flex justify-center">
            <div class="w-48 h-48"><?php echo $qrSvg; ?></div>
        </div>
        <p class="mt-2 text-center text-xs text-gray-500">
            Can't scan? Enter this secret manually:
            <code class="ml-1 select-all rounded bg-gray-100 px-2 py-0.5 font-mono"><?php echo e($secret); ?></code>
        </p>

        <form method="POST" action="<?php echo e(route('two-factor.confirm')); ?>" class="mt-6 space-y-3">
            <?php echo csrf_field(); ?>
            <label for="code" class="block text-sm font-medium">Verification code</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required
                   class="w-full rounded-md border-gray-300 text-center tracking-widest text-lg focus:border-indigo-500"
                   maxlength="6" placeholder="123456">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-sm text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <button class="w-full rounded-full bg-indigo-600 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Confirm & enable
            </button>
        </form>
    <?php else: ?>
        <div class="mt-4 rounded-lg bg-green-50 p-4 text-sm text-green-800">
            Two-factor authentication is <strong>enabled</strong> on your account.
        </div>

        <form method="POST" action="<?php echo e(route('two-factor.disable')); ?>" class="mt-6 space-y-3">
            <?php echo csrf_field(); ?>
            <?php echo method_field('DELETE'); ?>
            <label for="code" class="block text-sm font-medium">Enter a current code to disable</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required
                   class="w-full rounded-md border-gray-300 text-center tracking-widest text-lg"
                   maxlength="6" placeholder="123456">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-sm text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <button class="w-full rounded-full bg-red-600 py-2 text-sm font-semibold text-white hover:bg-red-700">
                Disable two-factor
            </button>
        </form>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/auth/two-factor/setup.blade.php ENDPATH**/ ?>