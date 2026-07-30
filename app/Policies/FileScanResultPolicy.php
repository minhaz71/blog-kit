<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksPermission;

class FileScanResultPolicy
{
    use ChecksPermission;

    protected function permissions(): array
    {
        return ['manage security'];
    }
}
