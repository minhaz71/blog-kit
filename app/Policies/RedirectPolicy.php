<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksPermission;

class RedirectPolicy
{
    use ChecksPermission;

    protected function permissions(): array
    {
        return ['manage seo'];
    }
}
