<div class="mt-3 overflow-x-auto rounded-lg border border-gray-200">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
            <tr>
                <th class="px-4 py-3">Order</th>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Total</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($orders as $order)
                <tr>
                    <td class="px-4 py-3 font-medium">{{ $order->order_number }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ $order->created_at->format('M j, Y') }}</td>
                    <td class="px-4 py-3">
                        @php
                            $statusColors = [
                                'pending' => 'bg-amber-100 text-amber-800', 'processing' => 'bg-blue-100 text-blue-800',
                                'completed' => 'bg-green-100 text-green-800', 'cancelled' => 'bg-gray-100 text-gray-600',
                                'refunded' => 'bg-purple-100 text-purple-800', 'failed' => 'bg-red-100 text-red-800',
                                'on_hold' => 'bg-orange-100 text-orange-800',
                            ];
                        @endphp
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $statusColors[$order->status] ?? 'bg-gray-100' }}">
                            {{ str($order->status)->headline() }}
                        </span>
                    </td>
                    <td class="px-4 py-3 font-medium">{{ price_format($order->total) }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('account.order', $order->order_number) }}" class="text-indigo-600 hover:underline">View</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
