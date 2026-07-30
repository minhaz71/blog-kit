@props(['rating' => 0, 'count' => null])
<div {{ $attributes->merge(['class' => 'flex items-center gap-1']) }}>
    <div class="flex" aria-label="Rated {{ $rating }} out of 5">
        @for($i = 1; $i <= 5; $i++)
            <svg class="h-3.5 w-3.5 {{ $i <= round($rating) ? 'text-amber-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.07 3.29a1 1 0 00.95.69h3.46c.97 0 1.37 1.24.59 1.81l-2.8 2.03a1 1 0 00-.36 1.12l1.07 3.29c.3.92-.76 1.69-1.54 1.12l-2.8-2.03a1 1 0 00-1.18 0l-2.8 2.03c-.78.57-1.84-.2-1.54-1.12l1.07-3.29a1 1 0 00-.36-1.12L2.98 8.72c-.78-.57-.38-1.81.59-1.81h3.46a1 1 0 00.95-.69l1.07-3.29z"/>
            </svg>
        @endfor
    </div>
    @if($count !== null)
        <span class="text-xs text-gray-500">({{ $count }})</span>
    @endif
</div>
