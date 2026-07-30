<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderNote extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_customer_visible' => 'boolean'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
