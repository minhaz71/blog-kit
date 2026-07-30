<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksPermission;

class UserPolicy
{
    use ChecksPermission;

    protected function permissions(): array
    {
        return ['manage customers'];
    }
}
