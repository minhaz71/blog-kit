<?php

namespace App\Http\Controllers;

use App\Payments\PaymentManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, PaymentManager $payments)
    {
        if (! in_array($gateway, $payments->keys())) {
            return response()->json(['message' => 'Unknown gateway'], 404);
        }

        $result = $payments->gateway($gateway)->handleWebhook($request);

        if (! $result->success) {
            Log::warning('Webhook rejected', ['gateway' => $gateway, 'reason' => $result->message, 'ip' => $request->ip()]);

            return response()->json(['message' => $result->message ?? 'Rejected'], 400);
        }

        return response()->json(['message' => 'ok']);
    }
}
