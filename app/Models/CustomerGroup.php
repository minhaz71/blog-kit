<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerGroup extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['discount_percent' => 'decimal:2'];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
