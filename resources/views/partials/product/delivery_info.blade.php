@php
    $boxes = array_filter((array) ($block['boxes'] ?? []), fn ($b) => ! empty($b['title']));

    // Subtle gradient from the editable box color — modern depth without
    // asking the store owner to pick two colors.
    $darken = function (string $hex, float $f = .22): string {
        if (! preg_match('/^#([0-9a-f]{6})$/i', $hex)) {
            return $hex;
        }
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return sprintf('#%02x%02x%02x', (int) ($r * (1 - $f)), (int) ($g * (1 - $f)), (int) ($b * (1 - $f)));
    };
@endphp
@if($boxes !== [])
    <x-pb-block :data="$block" class="mt-5 space-y-2.5">
        @foreach($boxes as $box)
            @php
                $bg = $box['bg_color'] ?? '#0f766e';
                $fg = $box['text_color'] ?? '#ffffff';
            @endphp
            <div class="flex items-start gap-3.5 rounded-xl px-4 py-3.5 shadow-sm ring-1 ring-black/5"
                 style="background: linear-gradient(135deg, {{ $bg }}, {{ $darken($bg) }}); color: {{ $fg }}">
                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-base"
                      style="background: rgba(255,255,255,.16)" aria-hidden="true">{{ $box['icon'] ?? '🚚' }}</span>
                <div class="min-w-0">
                    <p class="text-sm font-bold leading-snug">{{ $box['title'] }}</p>
                    @if(! empty($box['body']))
                        <div class="mt-0.5 text-xs leading-relaxed" style="opacity:.92">{!! nl2br(e($box['body'])) !!}</div>
                    @endif
                </div>
            </div>
        @endforeach
    </x-pb-block>
@endif
