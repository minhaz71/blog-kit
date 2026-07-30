<?php

namespace App\Services\Invoice;

use App\Models\InvoiceDownload;
use App\Models\Order;
use App\Support\StoreBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfInstance;
use Illuminate\Http\Request;

class InvoiceService
{
    /** Build the branded invoice PDF for an order (not yet streamed). */
    public function make(Order $order): PdfInstance
    {
        $order->loadMissing('items');

        return Pdf::loadView('pdf.invoice', [
            'order' => $order,
            'brand' => StoreBranding::all(),
        ])->setPaper('a4');
    }

    /** Standard download filename for an order's invoice. */
    public function filename(Order $order): string
    {
        return 'invoice-'.$order->order_number.'.pdf';
    }

    /**
     * Record that this order's invoice was downloaded (source = email /
     * account / admin) so the store can see how many recipients opened it.
     * Cheap, best-effort — never blocks the download.
     */
    public function recordDownload(Order $order, string $source, ?Request $request = null): void
    {
        $source = in_array($source, ['email', 'account', 'admin'], true) ? $source : 'email';

        try {
            InvoiceDownload::create([
                'order_id' => $order->getKey(),
                'token' => $order->invoiceCode(),
                'source' => $source,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
                'downloaded_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
