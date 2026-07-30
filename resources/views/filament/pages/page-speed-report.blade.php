<x-filament-panels::page>
    {{-- Self-contained styles — custom pages are not covered by the panel's
         compiled Tailwind (same pattern as the AI batch monitor). --}}
    <style>
        .psr { display: flex; flex-direction: column; gap: 1.5rem; font-variant-numeric: tabular-nums; }
        .psr-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; border-radius: 1rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); padding: 1rem 1.5rem; }
        .dark .psr-toolbar { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .psr-toolbar p { font-size: .85rem; color: #6b7280; max-width: 46rem; }

        .psr-panel { border-radius: 1rem; overflow: hidden; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); }
        .dark .psr-panel { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .psr-table-wrap { overflow-x: auto; }
        .psr-table { width: 100%; font-size: .875rem; border-collapse: collapse; }
        .psr-table thead tr { background: #f9fafb; text-align: left; }
        .dark .psr-table thead tr { background: rgba(255,255,255,.04); }
        .psr-table th { padding: .65rem 1.5rem; font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; white-space: nowrap; }
        .psr-table th.c, .psr-table td.c { text-align: center; }
        .psr-table th.r, .psr-table td.r { text-align: right; }
        .psr-table td { padding: .7rem 1.5rem; border-top: 1px solid rgba(0,0,0,.04); color: #374151; }
        .dark .psr-table td { border-color: rgba(255,255,255,.05); color: #d1d5db; }
        .psr-table a { color: #0f766e; text-decoration: none; font-weight: 500; }
        .psr-table a:hover { text-decoration: underline; }

        .psr-score { display: inline-block; min-width: 2.6rem; border-radius: 999px; padding: .18rem .6rem; font-size: .75rem; font-weight: 800; text-align: center; }
        .psr-good { background: #dcfce7; color: #15803d; }
        .psr-mid { background: #fef3c7; color: #b45309; }
        .psr-bad { background: #fee2e2; color: #b91c1c; }
        .dark .psr-good { background: rgba(34,197,94,.15); color: #4ade80; }
        .dark .psr-mid { background: rgba(245,158,11,.15); color: #fbbf24; }
        .dark .psr-bad { background: rgba(239,68,68,.15); color: #f87171; }
        .psr-none { color: #d1d5db; }
        .psr-empty { padding: 2.5rem 1.5rem; text-align: center; color: #9ca3af; }
    </style>

    <div class="psr">
        <div class="psr-toolbar">
            <p>
                Key pages (home, categories, most-linked products), snapshotted weekly via Google PageSpeed Insights.
                {{ $lastRun ? 'Last run: '.\Carbon\Carbon::parse($lastRun)->diffForHumans().'.' : 'No data yet — click Refresh now.' }}
                Add a PSI API key in SEO settings for a reliable quota.
            </p>
            <x-filament::button wire:click="refreshNow" wire:loading.attr="disabled" icon="heroicon-o-arrow-path">
                Refresh now
            </x-filament::button>
        </div>

        <div class="psr-panel">
            <div class="psr-table-wrap">
                <table class="psr-table">
                    <thead>
                        <tr>
                            <th>Page</th>
                            <th class="c">Mobile</th>
                            <th class="c">Desktop</th>
                            <th class="r">LCP (mobile)</th>
                            <th class="r">CLS</th>
                            <th class="r">INP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>
                                    <a href="{{ $row->url }}" target="_blank">{{ \Illuminate\Support\Str::limit(parse_url($row->url, PHP_URL_PATH) ?: '/', 50) }}</a>
                                </td>
                                @foreach(['mobile', 'desktop'] as $strategy)
                                    @php $s = $row->{$strategy}; @endphp
                                    <td class="c">
                                        @if($s)
                                            <span class="psr-score {{ $s->performance >= 90 ? 'psr-good' : ($s->performance >= 50 ? 'psr-mid' : 'psr-bad') }}">{{ $s->performance }}</span>
                                        @else
                                            <span class="psr-none">—</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="r">{{ $row->mobile?->lcp !== null ? $row->mobile->lcp.'s' : '—' }}</td>
                                <td class="r">{{ $row->mobile?->cls ?? '—' }}</td>
                                <td class="r">{{ $row->mobile?->inp !== null ? $row->mobile->inp.'ms' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="psr-empty">No snapshots yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
