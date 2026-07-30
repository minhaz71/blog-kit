<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksPermission;

class CustomerGroupPolicy
{
    use ChecksPermission;

    protected function permissions(): array
    {
        return ['manage customers'];
    }
}
