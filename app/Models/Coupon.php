<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    public const TYPES = ['fixed_cart', 'percent', 'fixed_product', 'free_shipping', 'bxgy', 'first_order'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'free_shipping' => 'boolean',
            'min_order_amount' => 'decimal:2',
            'max_order_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'first_order_only' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (Coupon $c) => $c->code = strtoupper(trim($c->code)));
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'coupon_products')->withPivot('is_excluded');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'coupon_categories')->withPivot('is_excluded');
    }

    public function allowedUsers()
    {
        return $this->belongsToMany(User::class, 'coupon_users');
    }

    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function isWithinDates(): bool
    {
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        return ! ($this->expires_at && $this->expires_at->isPast());
    }

    public function hasUsagesLeft(): bool
    {
        return $this->usage_limit === null || $this->used_count < $this->usage_limit;
    }

    public function usagesByCustomer(?int $userId, ?string $email): int
    {
        return $this->usages()
            ->where(function ($q) use ($userId, $email) {
                $q->when($userId, fn ($q) => $q->orWhere('user_id', $userId))
                    ->when($email, fn ($q) => $q->orWhere('email', $email));
            })
            ->count();
    }

    public function recordUsage(?User $user, ?string $email, ?int $orderId = null): void
    {
        $this->usages()->create([
            'user_id' => $user?->id,
            'email' => $email,
            'order_id' => $orderId,
        ]);

        $this->increment('used_count');
    }
}
