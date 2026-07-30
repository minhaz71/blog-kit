<x-filament-panels::page>
    @php($s = $this->getStats())
    {{-- Self-contained styles (custom admin pages don't get Tailwind
         utilities); scoped sca-* prefix, dark-mode aware. --}}
    <style>
        .sca { display: flex; flex-direction: column; gap: 1.5rem; font-variant-numeric: tabular-nums; }
        .sca-range { display: flex; gap: .5rem; }
        .sca-range button { border: 1px solid rgba(0,0,0,.1); background: transparent; border-radius: .5rem; padding: .35rem .8rem; font-size: .8rem; font-weight: 600; cursor: pointer; color: #6b7280; }
        .sca-range button.active { background: var(--brand, #0f766e); border-color: var(--brand, #0f766e); color: #fff; }
        .dark .sca-range button { border-color: rgba(255,255,255,.15); }

        .sca-cards { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
        @media (min-width: 1024px) { .sca-cards { grid-template-columns: repeat(4, 1fr); } }
        .sca-card { border-radius: 1rem; padding: 1.25rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); }
        .dark .sca-card { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .sca-card-label { margin: 0; font-size: .8125rem; font-weight: 500; color: #6b7280; }
        .sca-card-value { margin: .4rem 0 0; font-size: 1.875rem; line-height: 1.1; font-weight: 800; letter-spacing: -.02em; }
        .sca-card-value.warn { color: #b45309; }

        .sca-grid { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
        @media (min-width: 1024px) { .sca-grid { grid-template-columns: 1fr 1fr; } }
        .sca-panel { border-radius: 1rem; overflow: hidden; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); }
        .dark .sca-panel { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .sca-panel-head { padding: 1rem 1.25rem; border-bottom: 1px solid rgba(0,0,0,.05); display: flex; align-items: baseline; justify-content: space-between; gap: .5rem; }
        .dark .sca-panel-head { border-color: rgba(255,255,255,.08); }
        .sca-panel-head h2 { margin: 0; font-size: 1rem; font-weight: 600; }
        .sca-panel-head span { font-size: .75rem; color: #9ca3af; }

        .sca-row { display: flex; align-items: center; gap: .75rem; padding: .6rem 1.25rem; border-top: 1px solid rgba(0,0,0,.04); }
        .dark .sca-row { border-color: rgba(255,255,255,.05); }
        .sca-row:first-child { border-top: 0; }
        .sca-term { flex: 1; min-width: 0; font-size: .875rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sca-bar { flex: 0 0 34%; height: .5rem; border-radius: 999px; background: rgba(0,0,0,.06); overflow: hidden; }
        .dark .sca-bar { background: rgba(255,255,255,.1); }
        .sca-bar span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--brand, #0f766e), #10b981); }
        .sca-hits { flex-shrink: 0; font-size: .8rem; font-weight: 700; color: #374151; min-width: 2.5rem; text-align: right; }
        .dark .sca-hits { color: #d1d5db; }
        .sca-none { flex-shrink: 0; font-size: .7rem; font-weight: 700; color: #b91c1c; background: #fee2e2; border-radius: 999px; padding: .1rem .5rem; }
        .dark .sca-none { background: rgba(239,68,68,.15); color: #fca5a5; }
        .sca-empty { padding: 2rem 1.25rem; text-align: center; color: #9ca3af; font-size: .875rem; }
    </style>

    <div class="sca">
        <div class="sca-range">
            @foreach([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'] as $d => $label)
                <button wire:click="setRange({{ $d }})" class="{{ $days === $d ? 'active' : '' }}">{{ $label }}</button>
            @endforeach
        </div>

        <div class="sca-cards">
            <div class="sca-card"><p class="sca-card-label">Total searches</p><p class="sca-card-value">{{ number_format($s['total']) }}</p></div>
            <div class="sca-card"><p class="sca-card-label">Unique terms</p><p class="sca-card-value">{{ number_format($s['unique_terms']) }}</p></div>
            <div class="sca-card"><p class="sca-card-label">No-result searches</p><p class="sca-card-value warn">{{ number_format($s['no_results']) }}</p></div>
            <div class="sca-card"><p class="sca-card-label">Find rate</p><p class="sca-card-value">{{ $s['total'] > 0 ? round(100 * ($s['total'] - $s['no_results']) / $s['total']) : 0 }}%</p></div>
        </div>

        <div class="sca-grid">
            {{-- Top searched terms --}}
            <div class="sca-panel">
                <div class="sca-panel-head"><h2>Top searches</h2><span>keyword · times searched</span></div>
                @php($max = $s['top']->max('hits') ?: 1)
                @forelse($s['top'] as $row)
                    <div class="sca-row">
                        <span class="sca-term">{{ $row->query }}</span>
                        <span class="sca-bar"><span style="width: {{ max(6, round(100 * $row->hits / $max)) }}%"></span></span>
                        <span class="sca-hits">{{ number_format($row->hits) }}</span>
                        @if((int) $row->results === 0)<span class="sca-none">0 found</span>@endif
                    </div>
                @empty
                    <div class="sca-empty">No searches recorded yet in this range.</div>
                @endforelse
            </div>

            {{-- Zero-result terms — the gaps worth stocking or renaming for --}}
            <div class="sca-panel">
                <div class="sca-panel-head"><h2>Searches with no results</h2><span>demand you aren't meeting</span></div>
                @forelse($s['zero'] as $row)
                    <div class="sca-row">
                        <span class="sca-term">{{ $row->query }}</span>
                        <span class="sca-hits">{{ number_format($row->hits) }}×</span>
                    </div>
                @empty
                    <div class="sca-empty">Every search is finding products. 🎉</div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
