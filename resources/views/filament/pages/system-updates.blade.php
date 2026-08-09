<x-filament-panels::page>
    <style>
        .sup-grid { display: grid; gap: 1rem; }
        @media (min-width: 1024px) { .sup-grid { grid-template-columns: 1fr 1fr; } }
        .sup-card { background: #fff; border: 1px solid #e5e7eb; border-radius: .9rem; padding: 1.15rem 1.3rem; }
        .dark .sup-card { background: #18181b; border-color: #27272a; }
        .sup-card h3 { font-size: .74rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: #6b7280; margin: 0 0 .8rem; }
        .sup-ver { display: flex; align-items: baseline; gap: .6rem; }
        .sup-ver b { font-size: 2rem; font-weight: 800; color: #0f766e; line-height: 1; }
        .dark .sup-ver b { color: #5eead4; }
        .sup-ver span { font-size: .8rem; color: #6b7280; }
        .sup-meta { margin-top: .7rem; font-size: .82rem; color: #6b7280; line-height: 1.6; }
        .sup-meta code { background: #f1f5f9; padding: .05rem .35rem; border-radius: .3rem; font-size: .78rem; }
        .dark .sup-meta code { background: #27272a; }
        .sup-badge { display: inline-block; padding: .15rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 700; }
        .sup-badge--ok { background: #d1fae5; color: #065f46; }
        .sup-badge--new { background: #fef3c7; color: #92400e; }
        .dark .sup-badge--ok { background: #06402955; color: #6ee7b7; }
        .dark .sup-badge--new { background: #78350f55; color: #fcd34d; }
        .sup-tools { width: 100%; border-collapse: collapse; font-size: .86rem; }
        .sup-tools td { padding: .5rem .3rem; border-bottom: 1px solid #f1f5f9; }
        .dark .sup-tools td { border-color: #27272a; }
        .sup-tools td:last-child { text-align: right; font-variant-numeric: tabular-nums; color: #0f766e; font-weight: 700; }
        .dark .sup-tools td:last-child { color: #5eead4; }
        .sup-check { display: flex; align-items: flex-start; gap: .5rem; padding: .4rem 0; font-size: .84rem; border-bottom: 1px solid #f8fafc; }
        .dark .sup-check { border-color: #27272a; }
        .sup-check__ic { flex: none; margin-top: .1rem; }
        .sup-check__ic--ok { color: #16a34a; } .sup-check__ic--crit { color: #dc2626; } .sup-check__ic--warn { color: #d97706; }
        .sup-check small { display: block; color: #6b7280; margin-top: .1rem; }
        .sup-changelog { font-size: .85rem; line-height: 1.7; white-space: pre-wrap; color: #374151; max-height: 22rem; overflow-y: auto; }
        .dark .sup-changelog { color: #d4d4d8; }
    </style>

    @php($crit = collect($preflight['checks'])->where('ok', false)->where('severity', 'critical'))

    @if($behind)
        <div class="sup-card" style="border-color:#f59e0b">
            <strong>⬆ {{ $behind }} update(s) available.</strong>
            Click <em>Update Hemdox BlogKit</em> above. A full backup is taken first and the update rolls back automatically if anything fails.
        </div>
    @endif

    {{-- Last / running update — self-refreshes while an update is in progress --}}
    @php($u = $update ?? ['state' => 'idle'])
    <div @if(($u['state'] ?? '') === 'running') wire:poll.4s @endif>
        @if(($u['state'] ?? 'idle') !== 'idle')
            <div class="sup-card" style="margin-bottom:1rem;border-left:4px solid {{ $u['state']==='success' ? '#16a34a' : ($u['state']==='failed' ? '#dc2626' : '#d97706') }}">
                <h3>Last update
                    @php($st = $u['state'] ?? 'idle')
                    <span class="sup-badge {{ $st==='success' ? 'sup-badge--ok' : 'sup-badge--new' }}">{{ strtoupper($st) }}</span>
                    @if($st === 'running')<span style="font-size:.72rem;color:#6b7280">· refreshing…</span>@endif
                </h3>
                <div class="sup-meta">
                    @if(!empty($u['step']))<strong>Step:</strong> {{ $u['step'] }}<br>@endif
                    @if(!empty($u['message'])){{ $u['message'] }}<br>@endif
                    @if(!empty($u['from'])){{ $u['from'] }}@if(!empty($u['to'])) → <strong>{{ $u['to'] }}</strong>@endif<br>@endif
                    @if(!empty($u['finished_at']))Finished {{ \Illuminate\Support\Carbon::parse($u['finished_at'])->diffForHumans() }}@elseif(!empty($u['started_at']))Started {{ \Illuminate\Support\Carbon::parse($u['started_at'])->diffForHumans() }}@endif
                </div>
                @if(!empty($u['log']))
                    <pre style="margin-top:.8rem;max-height:16rem;overflow:auto;background:#0b1020;color:#cbd5e1;padding:.8rem 1rem;border-radius:.6rem;font-size:.74rem;line-height:1.5;white-space:pre-wrap">{{ $u['log'] }}</pre>
                @endif
            </div>
        @endif

        {{-- Host diagnostics: the common "update did nothing" causes --}}
        <div class="sup-card" style="margin-bottom:1rem">
            <h3>Update readiness (host)</h3>
            @foreach(($diagnostics ?? []) as $d)
                <div class="sup-check">
                    <span class="sup-check__ic sup-check__ic--{{ $d['ok'] ? 'ok' : 'crit' }}">{{ $d['ok'] ? '✓' : '✕' }}</span>
                    <span>{{ $d['label'] }}<small>{{ $d['detail'] }}</small></span>
                </div>
            @endforeach
            @unless($canSpawn ?? true)
                <div class="sup-meta" style="margin-top:.6rem">Run manually over SSH: <code>php artisan blogkit:update</code></div>
            @endunless
        </div>
    </div>

    <div class="sup-grid">
        {{-- Current version --}}
        <div class="sup-card">
            <h3>Hemdox BlogKit version</h3>
            <div class="sup-ver">
                <b>{{ $core }}</b>
                <span>@if($behind === 0)<span class="sup-badge sup-badge--ok">latest</span>@elseif($behind)<span class="sup-badge sup-badge--new">{{ $behind }} behind</span>@endif</span>
            </div>
            <div class="sup-meta">
                @if($released)Released {{ $released }}<br>@endif
                @if($isGit)
                    Branch <code>{{ $branch ?? '?' }}</code> · commit <code>{{ $commit ?? '?' }}</code>@if($committedAt) · {{ $committedAt }}@endif
                @else
                    <span style="color:#d97706">Not a git checkout — deploy via git to enable one-click updates (see docs/DEPLOYMENT.md).</span>
                @endif
            </div>
        </div>

        {{-- Tool versions --}}
        <div class="sup-card">
            <h3>Installed tools</h3>
            <table class="sup-tools">
                @foreach($components as $slug => $version)
                    <tr><td>{{ $labels[$slug] ?? $slug }}</td><td>v{{ $version }}</td></tr>
                @endforeach
            </table>
        </div>
    </div>

    <div class="sup-grid">
        {{-- Production readiness --}}
        <div class="sup-card">
            <h3>Production readiness
                @if($preflight['ok'])<span class="sup-badge sup-badge--ok">ready</span>
                @else<span class="sup-badge sup-badge--new">{{ $crit->count() }} to fix</span>@endif
            </h3>
            @foreach($preflight['checks'] as $c)
                <div class="sup-check">
                    <span class="sup-check__ic sup-check__ic--{{ $c['ok'] ? 'ok' : ($c['severity'] === 'critical' ? 'crit' : 'warn') }}">
                        {{ $c['ok'] ? '✓' : ($c['severity'] === 'critical' ? '✕' : '!') }}
                    </span>
                    <span>
                        {{ $c['label'] }}
                        @unless($c['ok'])<small>{{ $c['detail'] }}</small>@endunless
                    </span>
                </div>
            @endforeach
        </div>

        {{-- Changelog --}}
        <div class="sup-card">
            <h3>What's new</h3>
            <div class="sup-changelog">{{ $changelog ?: 'No changelog found.' }}</div>
        </div>
    </div>
</x-filament-panels::page>
