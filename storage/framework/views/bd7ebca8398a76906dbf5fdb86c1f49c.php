
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('no_account')): ?>
    <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm">
        <p class="font-semibold text-amber-900">
            We couldn&rsquo;t find an account<?php echo e(old('email') ? ' for '.old('email') : ''); ?>.
        </p>
        <p class="mt-1 text-amber-800">You&rsquo;ll need to create an account first &mdash; it only takes a moment.</p>
        <a href="<?php echo e(route('register', array_filter(['email' => old('email')]))); ?>"
           class="mt-3 inline-flex items-center justify-center rounded-md bg-teal-600 px-5 py-2.5 font-semibold text-white transition hover:bg-teal-700">
            Create an account
        </a>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/partials/auth/no-account.blade.php ENDPATH**/ ?>