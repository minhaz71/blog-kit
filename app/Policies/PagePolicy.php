<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksPermission;

class PagePolicy
{
    use ChecksPermission;

    protected function permissions(): array
    {
        return ['manage content'];
    }
}
