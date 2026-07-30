<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksPermission;

class ShippingZonePolicy
{
    use ChecksPermission;

    protected function permissions(): array
    {
        return ['manage shipping'];
    }
}
