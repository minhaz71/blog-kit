<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksPermission;

class TransactionPolicy
{
    use ChecksPermission;

    protected function permissions(): array
    {
        return ['manage orders'];
    }
}
