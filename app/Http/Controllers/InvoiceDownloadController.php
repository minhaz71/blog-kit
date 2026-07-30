<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Invoice\InvoiceService;
use Illuminate\Http\Request;

/**
 * Public, login-free invoice download used by the order email.
 *
 * The old invoice route lived behind `auth` + `$user->orders()`, so it 404'd
 * for guest checkouts and for anyone opening the link from their inbox — the
 * "invoice download doesn't work" report. This route is guarded instead by the
 * per-order HMAC `code` (Order::invoiceCode()): tamper-proof, no session
 * needed, and the code doubles as the tracking token recorded on each open.
 */
class InvoiceDownloadController extends Controller
{
    public function __invoke(Request $request, string $orderNumber, string $code, InvoiceService $invoices)
    {
        $order = Order::where('order_number', $orderNumber)->first();

        // Unknown order and wrong code look identical (both 404): a caller
        // without the exact HMAC code can't even tell whether an order number
        // exists — no enumeration oracle.
        abort_unless($order && $order->verifyInvoiceCode($code), 404);

        // Don't count link scanners / prefetchers as real downloads.
        if (! $this->looksAutomated($request)) {
            $invoices->recordDownload($order, (string) $request->query('src', 'email'), $request);
        }

        return $invoices->make($order)->download($invoices->filename($order));
    }

    /** Crude bot / prefetch filter so tracking counts reflect real people. */
    protected function looksAutomated(Request $request): bool
    {
        if ($request->isMethod('HEAD')) {
            return true;
        }

        $ua = strtolower((string) $request->userAgent());
        if ($ua === '') {
            return true;
        }

        foreach (['bot', 'crawler', 'spider', 'preview', 'slurp', 'facebookexternalhit', 'headless', 'monitor'] as $needle) {
            if (str_contains($ua, $needle)) {
                return true;
            }
        }

        return false;
    }
}
