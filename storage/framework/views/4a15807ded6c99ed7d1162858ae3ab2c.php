<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-md px-4 py-12 sm:px-6">
    <h1 class="text-3xl font-bold">Sign in</h1>
    <form action="<?php echo e(route('login')); ?>" method="POST" class="mt-6 space-y-4">
        <?php echo csrf_field(); ?>
        <div>
            <label for="email" class="text-sm font-medium">Email</label>
            <input id="email" type="email" name="email" value="<?php echo e(old('email')); ?>" required autofocus class="mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-gray-900 shadow-sm transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30">
        </div>
        <div>
            <label for="password" class="text-sm font-medium">Password</label>
            <input id="password" type="password" name="password" required class="mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-gray-900 shadow-sm transition focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30">
        </div>
        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="remember" class="rounded border-gray-300"> Remember me</label>
            <a href="<?php echo e(route('password.request')); ?>" class="text-indigo-600 hover:underline">Forgot password?</a>
        </div>
        <button class="w-full rounded-md bg-indigo-600 px-6 py-3 font-semibold text-white hover:bg-indigo-500">Sign in</button>
    </form>
    <?php echo $__env->make('partials.auth.no-account', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <p class="mt-4 text-center text-sm text-gray-600">New here? <a href="<?php echo e(route('register')); ?>" class="text-indigo-600 hover:underline">Create an account</a></p>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/resources/views/auth/login.blade.php ENDPATH**/ ?>