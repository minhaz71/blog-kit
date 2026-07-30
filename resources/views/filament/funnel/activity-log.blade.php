@php
    /** @var \Illuminate\Support\Collection $feed */
@endphp
<div class="fi-funnel-log" style="max-height:60vh;overflow-y:auto;font-size:.8125rem;line-height:1.5">
    @forelse ($feed as $row)
        @php
            $color = match ($row->level) {
                'error' => '#dc2626',
                'warning' => '#d97706',
                'success' => '#16a34a',
                default => '#6b7280',
            };
        @endphp
        <div style="display:flex;gap:.5rem;padding:.35rem 0;border-bottom:1px solid rgba(148,163,184,.15)">
            <span style="flex:0 0 auto;color:#94a3b8;font-variant-numeric:tabular-nums">{{ $row->created_at?->format('H:i:s') }}</span>
            <span style="flex:0 0 auto;width:.5rem;border-radius:9999px;background:{{ $color }}"></span>
            <span style="color:{{ $color }}">{{ $row->message }}</span>
        </div>
    @empty
        <p style="color:#94a3b8;padding:1rem 0">No activity was logged for this run. If it burned tokens but recorded nothing, the background process was killed before it could log — use Retry.</p>
    @endforelse
</div>
