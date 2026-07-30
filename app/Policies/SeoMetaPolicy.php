<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksPermission;

class SeoMetaPolicy
{
    use ChecksPermission;

    protected function permissions(): array
    {
        return ['manage seo', 'manage products', 'manage content'];
    }
}
