<?php

namespace App\Payments\Contracts;

use App\Models\Order;
use App\Payments\PaymentResult;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /** Machine key, e.g. "stripe", "cod". */
    public function key(): string;

    /** Customer-facing title (admin-editable via settings). */
    public function title(): string;

    public function description(): ?string;

    /** Instructions shown on the thank-you page / emails (bank details etc.). */
    public function instructions(): ?string;

    public function isEnabled(): bool;

    /** Start payment. Either completes offline or returns a redirect URL. */
    public function initiate(Order $order): PaymentResult;

    /** Handle async webhook. MUST verify the signature before trusting payload. */
    public function handleWebhook(Request $request): PaymentResult;

    public function supportsRefunds(): bool;

    public function refund(Order $order, float $amount): PaymentResult;
}
