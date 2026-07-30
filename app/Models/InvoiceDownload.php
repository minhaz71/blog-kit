<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceDownload extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['downloaded_at' => 'datetime'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
