<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_read' => 'boolean'];
    }

    public function scopeUnread($q)
    {
        return $q->where('is_read', false);
    }
}
