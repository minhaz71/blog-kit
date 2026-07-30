<?php

namespace App\Policies;

use App\Policies\Concerns\ChecksPermission;

class EmailTemplatePolicy
{
    use ChecksPermission;

    protected function permissions(): array
    {
        return ['manage emails'];
    }
}
