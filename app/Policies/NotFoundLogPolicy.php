<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksPermission;

class NotFoundLogPolicy
{
    use ChecksPermission;

    protected function permissions(): array
    {
        return ['manage seo'];
    }
}
