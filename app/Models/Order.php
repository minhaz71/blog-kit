<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['pending', 'processing', 'on_hold', 'completed', 'cancelled', 'refunded', 'failed'];
    public const PAYMENT_STATUSES = ['pending', 'paid', 'failed', 'refunded', 'partially_refunded'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public static function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('Ymd').'-'.strtoupper(str()->random(6));
        } while (static::where('order_number', $number)->exists());

        return $number;
    }

    // ── Relationships ──────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function notes()
    {
        return $this->hasMany(OrderNote::class)->latest();
    }

    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function invoiceDownloads()
    {
        return $this->hasMany(InvoiceDownload::class);
    }

    // ── Invoice link (public, tamper-proof, trackable) ─────────────

    /**
     * Signature hash tying an invoice link to THIS order. It is an HMAC of the
     * order identity keyed by APP_KEY, so a link can't be forged or edited to
     * point at another order — no login required, which is why the link works
     * straight from the email (including guest orders). It also doubles as the
     * tracking token recorded on each download.
     */
    public function invoiceCode(): string
    {
        return hash_hmac('sha256', $this->getKey().'|'.$this->order_number, (string) config('app.key'));
    }

    public function verifyInvoiceCode(string $code): bool
    {
        return hash_equals($this->invoiceCode(), $code);
    }

    /** Public invoice-download URL for the given source (email/account/admin). */
    public function invoiceUrl(string $source = 'email'): string
    {
        return route('invoice.download', [
            'orderNumber' => $this->order_number,
            'code' => $this->invoiceCode(),
            'src' => $source,
        ]);
    }

    /**
     * Fulfilment step for the email progress tracker:
     * 1 = confirmed, 2 = shipped/processing, 3 = delivered/completed.
     */
    public function fulfilmentStep(): int
    {
        return match ($this->status) {
            'completed' => 3,
            'processing', 'on_hold' => 2,
            default => 1,
        };
    }

    // ── Status transitions ─────────────────────────────────────────

    public function updateStatus(string $status, ?User $actor = null): void
    {
        if ($status === $this->status || ! in_array($status, self::STATUSES)) {
            return;
        }

        $from = $this->status;

        $this->forceFill([
            'status' => $status,
            'completed_at' => $status === 'completed' ? now() : $this->completed_at,
        ])->save();

        $this->statusHistories()->create([
            'from_status' => $from,
            'to_status' => $status,
            'user_id' => $actor?->id,
        ]);

        event(new \App\Events\OrderStatusChanged($this, $from, $status));
    }

    public function markPaid(?string $transactionId = null): void
    {
        $this->forceFill([
            'payment_status' => 'paid',
            'transaction_id' => $transactionId ?? $this->transaction_id,
            'paid_at' => now(),
        ])->save();

        if ($this->status === 'pending') {
            $this->updateStatus('processing');
        }
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function customerName(): string
    {
        $billing = $this->billing_address ?? [];

        return trim(($billing['first_name'] ?? '').' '.($billing['last_name'] ?? ''));
    }

    /**
     * True while the order may still have its line items edited by an admin.
     * Once it leaves "pending" (payment) it is locked to view / status changes only.
     */
    public function isEditable(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * A completed order is counted as final, realised sales. Everything still
     * moving through fulfilment (pending / processing / on hold) is "in process".
     */
    public function isFinalSale(): bool
    {
        return $this->status === 'completed';
    }

    public function isInProcess(): bool
    {
        return in_array($this->status, ['pending', 'processing', 'on_hold'], true);
    }

    /**
     * Recompute each line item and the order money columns from the current
     * items. Called after an admin edits items while the order is pending.
     * Snapshot fields (name / sku / unit_price) are backfilled from the linked
     * product when they were left blank on a freshly added row.
     */
    public function recalculateTotals(): void
    {
        $this->loadMissing('items.product');

        foreach ($this->items as $item) {
            if ($item->product) {
                if (blank($item->name)) {
                    $item->name = $item->product->name;
                }
                if (blank($item->sku)) {
                    $item->sku = $item->product->sku;
                }
                if ((float) $item->unit_price <= 0) {
                    $item->unit_price = (float) $item->product->price;
                }
            }

            $item->qty = max(1, (int) $item->qty);
            $item->subtotal = round((float) $item->unit_price * $item->qty, 2);
            $item->total = round($item->subtotal - (float) $item->discount, 2);
            $item->saveQuietly();
        }

        $subtotal = round((float) $this->items->sum('subtotal'), 2);

        $this->subtotal = $subtotal;
        $this->total = round(
            $subtotal
            - (float) $this->discount_total
            + (float) $this->shipping_total
            + (float) $this->tax_total
            + (float) $this->payment_fee,
            2
        );
        $this->saveQuietly();
    }

    public function scopeStatus($q, string $status)
    {
        return $q->where('status', $status);
    }
}
