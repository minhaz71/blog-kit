<?php

namespace App\Http\Controllers;

use App\Services\Security\LoginSecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();

        return view('account.dashboard', [
            'user' => $user,
            'recentOrders' => $user->orders()->latest()->take(5)->get(),
            'orderCount' => $user->orderCount(),
            'wishlistCount' => $user->wishlist()->count(),
        ]);
    }

    public function orders(Request $request)
    {
        return view('account.orders', [
            'orders' => $request->user()->orders()->latest()->paginate(10),
        ]);
    }

    public function order(Request $request, string $orderNumber)
    {
        $order = $request->user()->orders()
            ->where('order_number', $orderNumber)
            ->with(['items', 'notes' => fn ($q) => $q->where('is_customer_visible', true)])
            ->firstOrFail();

        return view('account.order', ['order' => $order]);
    }

    public function invoice(Request $request, string $orderNumber, \App\Services\Invoice\InvoiceService $invoices)
    {
        $order = $request->user()->orders()
            ->where('order_number', $orderNumber)
            ->with('items')
            ->firstOrFail();

        $invoices->recordDownload($order, 'account', $request);

        return $invoices->make($order)->download($invoices->filename($order));
    }

    public function profile(Request $request)
    {
        return view('account.profile', ['user' => $request->user()]);
    }

    public function updateProfile(Request $request, LoginSecurityService $security)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'accepts_marketing' => ['nullable', 'boolean'],
            'current_password' => ['nullable', 'required_with:password', 'string'],
            'password' => ['nullable', ...$security->passwordRules()],
        ]);

        if (! empty($data['password'])) {
            if (! Hash::check((string) $data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
            }

            $user->forceFill(['password' => $data['password'], 'password_changed_at' => now()]);
        }

        $user->fill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'accepts_marketing' => (bool) ($data['accepts_marketing'] ?? false),
        ])->save();

        return back()->with('success', 'Profile updated.');
    }

    /** GDPR-style data export: all personal data as JSON download. */
    public function exportData(Request $request)
    {
        $user = $request->user();

        $payload = [
            'profile' => $user->only(['name', 'email', 'phone', 'created_at']),
            'addresses' => $user->addresses->map->toOrderArray(),
            'orders' => $user->orders()->with('items')->get()->map(fn ($order) => [
                'number' => $order->order_number,
                'status' => $order->status,
                'total' => (string) $order->total,
                'placed_at' => $order->created_at->toIso8601String(),
                'items' => $order->items->map(fn ($item) => $item->only(['name', 'sku', 'qty', 'unit_price', 'total'])),
            ]),
            'reviews' => $user->reviews()->get(['product_id', 'rating', 'title', 'body', 'created_at']),
        ];

        return response()->streamDownload(
            fn () => print json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'my-data.json',
            ['Content-Type' => 'application/json'],
        );
    }
}
