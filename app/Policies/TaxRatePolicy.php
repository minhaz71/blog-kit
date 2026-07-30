<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksPermission;

class TaxRatePolicy
{
    use ChecksPermission;

    protected function permissions(): array
    {
        return ['manage shipping'];
    }
}
