<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksPermission;

class ProductVariationPolicy
{
    use ChecksPermission;

    protected function permissions(): array
    {
        return ['manage products'];
    }
}
