{{ $heading }}

{{ strip_tags($body) }}
@if(!empty($order['items']))

Order {{ $order['number'] ?? '' }}
@foreach($order['items'] as $item)
- {{ $item['name'] }} × {{ $item['qty'] }} — {{ $item['total'] }}
@endforeach

Subtotal: {{ $order['subtotal'] }}
@if(!empty($order['discount']))Discount: -{{ $order['discount'] }}@endif
Shipping: {{ $order['shipping'] }}
@if(!empty($order['tax']))Tax: {{ $order['tax'] }}@endif
@if(!empty($order['payment_fee'])){{ $order['payment_fee_label'] }}: {{ $order['payment_fee'] }}@endif
Total: {{ $order['total'] }}
@if(!empty($order['url']))

View your order: {{ $order['url'] }}
@endif
@endif

--
{{ setting('general.store_name', config('app.name')) }}
{{ config('app.url') }}
