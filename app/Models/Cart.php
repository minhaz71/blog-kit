<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'abandoned_email_sent_at' => 'datetime',
            'abandoned_at' => 'datetime',
            'last_reminder_at' => 'datetime',
            'recovered_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /** Best email to reach this cart's shopper (captured guest email, or the user's). */
    public function recipientEmail(): ?string
    {
        return $this->email ?: $this->user?->email;
    }

    /**
     * Tamper-proof token tying a recovery link to THIS cart (HMAC keyed by
     * APP_KEY). Lets an abandoned-cart reminder restore the exact cart on any
     * device / after the session cookie expired — without it a session-keyed
     * guest cart would be unreachable from the email.
     */
    public function recoveryCode(): string
    {
        return hash_hmac('sha256', $this->getKey().'|'.optional($this->created_at)->timestamp, (string) config('app.key'));
    }

    public function verifyRecoveryCode(string $code): bool
    {
        return hash_equals($this->recoveryCode(), $code);
    }

    public function recoveryUrl(): string
    {
        return route('cart.restore', ['cart' => $this->getKey(), 'code' => $this->recoveryCode()]);
    }

    public function recipientName(): string
    {
        return $this->customer_name ?: ($this->user?->name ?: 'there');
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function itemCount(): int
    {
        return (int) $this->items->sum('qty');
    }

    /** Subtotal computed from live product prices — never trusted from the client. */
    public function subtotal(): float
    {
        return round($this->items->sum(fn (CartItem $item) => $item->lineTotal()), 2);
    }

    public function totalWeight(): float
    {
        return (float) $this->items->sum(function (CartItem $item) {
            $weight = $item->variation?->weight ?? $item->product?->weight ?? 0;

            return (float) $weight * $item->qty;
        });
    }
}
