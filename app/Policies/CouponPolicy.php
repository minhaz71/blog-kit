<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksPermission;

class CouponPolicy
{
    use ChecksPermission;

    protected function permissions(): array
    {
        return ['manage coupons'];
    }
}
