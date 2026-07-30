<x-filament-panels::page>
    {{-- Self-contained styles — custom pages are not covered by the panel's
         compiled Tailwind. --}}
    <style>
        .spf { display: flex; flex-direction: column; gap: 1.5rem; font-variant-numeric: tabular-nums; }
        .spf-cards { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        .spf-card { border-radius: 1rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); padding: 1.25rem; }
        .dark .spf-card { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .spf-label { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; }
        .spf-value { margin-top: .25rem; font-size: 1.9rem; font-weight: 800; color: #111827; }
        .dark .spf-value { color: #f9fafb; }

        .spf-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; border-radius: 1rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); padding: 1rem 1.5rem; }
        .dark .spf-toolbar { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .spf-toolbar p { font-size: .85rem; color: #6b7280; }

        .spf-setup { border: 1px dashed #99f6e4; border-radius: 1rem; background: #f0fdfa; padding: 1.5rem; font-size: .9rem; color: #134e4a; line-height: 1.7; }
        .dark .spf-setup { background: rgba(99,102,241,.08); border-color: rgba(99,102,241,.35); color: #5eead4; }
        .spf-setup ol { margin: .5rem 0 0 1.2rem; }

        .spf-panel { border-radius: 1rem; overflow: hidden; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); }
        .dark .spf-panel { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .spf-table-wrap { overflow-x: auto; }
        .spf-table { width: 100%; font-size: .85rem; border-collapse: collapse; min-width: 860px; }
        .spf-table thead tr { background: #f9fafb; text-align: left; }
        .dark .spf-table thead tr { background: rgba(255,255,255,.04); }
        .spf-table th { padding: .65rem 1.25rem; font-size: .66rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; white-space: nowrap; }
        .spf-table th.r, .spf-table td.r { text-align: right; }
        .spf-table td { padding: .65rem 1.25rem; border-top: 1px solid rgba(0,0,0,.04); color: #374151; }
        .dark .spf-table td { border-color: rgba(255,255,255,.05); color: #d1d5db; }
        .spf-table a { color: #0f766e; text-decoration: none; font-weight: 500; }
        .spf-table a:hover { text-decoration: underline; }

        .spf-badge { display: inline-block; border-radius: 999px; padding: .15rem .6rem; font-size: .68rem; font-weight: 700; }
        .spf-pass { background: #dcfce7; color: #15803d; }
        .spf-fail { background: #fee2e2; color: #b91c1c; }
        .spf-warn { background: #fef3c7; color: #b45309; }
        .spf-unknown { background: #f3f4f6; color: #9ca3af; }
        .dark .spf-pass { background: rgba(34,197,94,.15); color: #4ade80; }
        .dark .spf-fail { background: rgba(239,68,68,.15); color: #f87171; }
        .dark .spf-warn { background: rgba(245,158,11,.15); color: #fbbf24; }
        .spf-empty { padding: 3rem; text-align: center; color: #9ca3af; }
    </style>

    <div class="spf">
        @if(! $configured)
            <div class="spf-setup">
                <strong>Connect Google Search Console (one-time setup):</strong>
                <ol>
                    <li>Google Cloud Console → create a <em>service account</em>; enable the <em>Search Console API</em> and <em>Analytics Data API</em>.</li>
                    <li>Download its JSON key and paste it in <em>SEO settings → Integrations → Google service account JSON</em>.</li>
                    <li>Add the service account's email as a user on your Search Console property (Full access) and, optionally, your GA4 property (Viewer).</li>
                    <li>Fill in the Search Console property (e.g. <code>sc-domain:tereahub.ae</code>) and GA4 property ID, save, then hit Sync now.</li>
                </ol>
            </div>
        @endif

        <div class="spf-cards">
            <div class="spf-card">
                <p class="spf-label">Clicks ({{ $periodDays }}d)</p>
                <p class="spf-value">{{ number_format($totals->clicks) }}</p>
            </div>
            <div class="spf-card">
                <p class="spf-label">Impressions</p>
                <p class="spf-value">{{ number_format($totals->impressions) }}</p>
            </div>
            <div class="spf-card">
                <p class="spf-label">Indexed (checked)</p>
                <p class="spf-value" style="color:#16a34a">{{ $totals->indexed }}</p>
            </div>
            <div class="spf-card">
                <p class="spf-label">Not indexed</p>
                <p class="spf-value" style="color: {{ $totals->notIndexed > 0 ? '#dc2626' : '#16a34a' }}">{{ $totals->notIndexed }}</p>
            </div>
        </div>

        <div class="spf-toolbar">
            <p>
                Page-level Google Search data{{ $fetchedAt ? ', synced '.\Carbon\Carbon::parse($fetchedAt)->diffForHumans() : '' }} ·
                refreshes daily via cron · index status covers the top pages by impressions (quota-aware).
            </p>
            <x-filament::button wire:click="syncNow" wire:loading.attr="disabled" icon="heroicon-o-arrow-path">
                Sync now
            </x-filament::button>
        </div>

        <div class="spf-panel">
            <div class="spf-table-wrap">
                <table class="spf-table">
                    <thead>
                        <tr>
                            <th>Page</th>
                            <th class="r">Clicks</th>
                            <th class="r">Impressions</th>
                            <th class="r">CTR</th>
                            <th class="r">Position</th>
                            <th class="r">Organic sessions (GA4)</th>
                            <th>Index status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php $status = $statuses[$row->url] ?? null; @endphp
                            <tr>
                                <td><a href="{{ $row->url }}" target="_blank">{{ \Illuminate\Support\Str::limit(parse_url($row->url, PHP_URL_PATH) ?: '/', 60) }}</a></td>
                                <td class="r" style="font-weight:700">{{ number_format($row->clicks) }}</td>
                                <td class="r">{{ number_format($row->impressions) }}</td>
                                <td class="r">{{ $row->ctr }}%</td>
                                <td class="r">{{ $row->position }}</td>
                                <td class="r">{{ $row->organic_sessions !== null ? number_format($row->organic_sessions) : '—' }}</td>
                                <td>
                                    @if($status)
                                        <span class="spf-badge {{ $status->verdict === 'PASS' ? 'spf-pass' : ($status->verdict === 'FAIL' ? 'spf-fail' : 'spf-warn') }}"
                                              title="{{ $status->coverage }}{{ $status->last_crawl_at ? ' · last crawl '.\Carbon\Carbon::parse($status->last_crawl_at)->diffForHumans() : '' }}">
                                            {{ $status->verdict === 'PASS' ? 'Indexed' : ($status->coverage ?: $status->verdict) }}
                                        </span>
                                    @else
                                        <span class="spf-badge spf-unknown">not checked</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="spf-empty">{{ $configured ? 'No data yet — click Sync now.' : 'Connect Search Console above to see which pages get clicks, rankings and index status.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
