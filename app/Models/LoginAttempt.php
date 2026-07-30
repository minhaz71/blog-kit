<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'is_admin_area' => 'boolean',
        ];
    }
}
