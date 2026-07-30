<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksPermission;

class BackupPolicy
{
    use ChecksPermission;

    protected function permissions(): array
    {
        return ['manage settings'];
    }
}
