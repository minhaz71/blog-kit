<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
            'is_verified_purchase' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (Review $review) => $review->product?->recalculateRating());
        static::deleted(fn (Review $review) => $review->product?->recalculateRating());
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeApproved($q)
    {
        return $q->where('is_approved', true);
    }
}
