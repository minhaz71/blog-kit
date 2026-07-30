<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileHash extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['file_modified_at' => 'datetime'];
    }
}
