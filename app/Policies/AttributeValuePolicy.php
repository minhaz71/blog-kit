<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksPermission;

class AttributeValuePolicy
{
    use ChecksPermission;

    protected function permissions(): array
    {
        return ['manage products'];
    }
}
