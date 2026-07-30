<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksPermission;

class BlockedIpPolicy
{
    use ChecksPermission;

    protected function permissions(): array
    {
        return ['manage security'];
    }
}
