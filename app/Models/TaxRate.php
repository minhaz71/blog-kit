<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'applies_to_shipping' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
