<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileScanResult extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_resolved' => 'boolean',
            'scanned_at' => 'datetime',
        ];
    }
}
