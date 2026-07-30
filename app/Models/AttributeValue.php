<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AttributeValue extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['needs_review' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function ($value) {
            $value->slug = $value->slug ?: Str::slug($value->value);
        });
    }

    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }
}
