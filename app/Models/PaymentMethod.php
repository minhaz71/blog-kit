<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * An admin-defined offline payment method (cash/card on delivery, bank
 * transfer, …). Fully customizable: name, checkout message, and an optional
 * named surcharge (flat + percent). Each active method appears at checkout
 * through the OfflineGateway adapter.
 */
class PaymentMethod extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fee_fixed' => 'decimal:2',
            'fee_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Auto-derive a stable key from the name on create when none given.
        static::creating(function (self $method): void {
            if (blank($method->key)) {
                $method->key = static::uniqueKey($method->name);
            }
        });

        static::saved(fn () => Cache::forget('payment_methods.active'));
        static::deleted(fn () => Cache::forget('payment_methods.active'));
    }

    public static function uniqueKey(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'method';
        $key = $base;
        $n = 2;
        while (static::where('key', $key)->exists()) {
            $key = $base.'_'.$n++;
        }

        return $key;
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }

    /** Active methods, cached (rehydration-safe: keyed plain collection). */
    public static function activeMethods()
    {
        return static::active()->ordered()->get();
    }

    /** Surcharge for this method given the order subtotal. */
    public function feeFor(float $subtotal): float
    {
        return round((float) $this->fee_fixed + $subtotal * ((float) $this->fee_percent / 100), 2);
    }

    public function hasFee(): bool
    {
        return (float) $this->fee_fixed > 0 || (float) $this->fee_percent > 0;
    }

    public function feeLabel(): string
    {
        return $this->fee_label ?: 'Payment fee';
    }
}
