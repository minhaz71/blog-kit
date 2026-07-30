<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksPermission;

class BrandPolicy
{
    use ChecksPermission;

    protected function permissions(): array
    {
        return ['manage products'];
    }
}
