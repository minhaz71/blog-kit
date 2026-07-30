@props(['data' => []])
@php $style = pb_block_style((array) $data); @endphp
<div {{ $attributes->merge(['class' => 'pb-block '.($data['custom_class'] ?? '')]) }}
     @if($style) style="{{ $style }}" @endif>
    {{ $slot }}
</div>
