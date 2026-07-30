<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksPermission;

class TagPolicy
{
    use ChecksPermission;

    protected function permissions(): array
    {
        return ['manage products', 'manage content'];
    }
}
