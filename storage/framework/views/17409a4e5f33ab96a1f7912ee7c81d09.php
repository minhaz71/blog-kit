<div
    <?php echo e($attributes
            ->merge([
                'id' => $getId(),
            ], escape: false)
            ->merge($getExtraAttributes(), escape: false)); ?>

>
    <?php echo e($getChildSchema()); ?>

</div>
<?php /**PATH /Users/minhaz/multi blog site/hemdox-blogkit/vendor/filament/schemas/resources/views/components/grid.blade.php ENDPATH**/ ?>