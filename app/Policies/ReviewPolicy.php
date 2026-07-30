<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksPermission;

class ReviewPolicy
{
    use ChecksPermission;

    protected function permissions(): array
    {
        return ['manage reviews'];
    }
}
