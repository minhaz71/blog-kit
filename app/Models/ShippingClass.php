<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Model;

class ShippingClass extends Model
{
    use HasSlug;

    protected $guarded = [];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
