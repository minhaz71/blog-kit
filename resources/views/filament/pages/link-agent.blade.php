<x-filament-panels::page>
    {{-- Self-contained styles — custom pages aren't covered by the panel's
         compiled Tailwind. --}}
    <style>
        .lga { display: flex; flex-direction: column; gap: 1.5rem; }
        .lga-cards { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        .lga-card { border-radius: 1rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); padding: 1.25rem; }
        .dark .lga-card { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .lga-label { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; }
        .lga-value { margin-top: .25rem; font-size: 1.9rem; font-weight: 800; color: #111827; }
        .dark .lga-value { color: #f9fafb; }

        .lga-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; border-radius: 1rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); padding: .9rem 1.25rem; }
        .dark .lga-toolbar { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .lga-input { border-radius: .6rem; border: 1px solid #d1d5db; padding: .5rem .75rem; font-size: .85rem; min-width: 16rem; background: #fff; color: #111827; }
        .dark .lga-input { background: rgba(255,255,255,.05); border-color: rgba(255,255,255,.12); color: #f9fafb; }
        .lga-note { font-size: .75rem; color: #9ca3af; }

        .lga-panel { border-radius: 1rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); overflow: hidden; }
        .dark .lga-panel { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
        .lga-group-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .85rem 1.5rem; background: #f9fafb; border-bottom: 1px solid rgba(0,0,0,.05); }
        .dark .lga-group-head { background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.08); }
        .lga-group-title { font-weight: 700; font-size: .9rem; color: #111827; }
        .dark .lga-group-title { color: #f9fafb; }
        .lga-kind { display: inline-block; border-radius: .35rem; background: #f0fdfa; color: #0f766e; font-size: .6rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; padding: .15rem .45rem; margin-right: .45rem; vertical-align: middle; }
        .dark .lga-kind { background: rgba(99,102,241,.15); color: #5eead4; }

        .lga-row { display: flex; gap: 1rem; align-items: flex-start; padding: 1rem 1.5rem; border-bottom: 1px solid rgba(0,0,0,.04); }
        .dark .lga-row { border-color: rgba(255,255,255,.05); }
        .lga-row:last-child { border-bottom: 0; }
        .lga-score { flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 999px; font-weight: 800; font-size: .85rem; }
        .lga-score-high { background: #dcfce7; color: #15803d; }
        .lga-score-mid { background: #fef3c7; color: #b45309; }
        .dark .lga-score-high { background: rgba(34,197,94,.15); color: #4ade80; }
        .dark .lga-score-mid { background: rgba(245,158,11,.15); color: #fbbf24; }
        .lga-body { flex: 1; min-width: 0; }
        .lga-sentence { font-size: .9rem; color: #374151; line-height: 1.55; }
        .dark .lga-sentence { color: #d1d5db; }
        .lga-sentence mark { background: #99f6e4; color: #134e4a; border-radius: .25rem; padding: 0 .25rem; font-weight: 600; }
        .dark .lga-sentence mark { background: rgba(99,102,241,.35); color: #ccfbf1; }
        .lga-target { margin-top: .35rem; font-size: .75rem; color: #6b7280; }
        .lga-target a { color: #0f766e; font-weight: 600; text-decoration: none; }
        .lga-target a:hover { text-decoration: underline; }
        .lga-actions { display: flex; flex-shrink: 0; gap: .5rem; }
        .lga-btn { border: 0; cursor: pointer; border-radius: .55rem; padding: .45rem .95rem; font-size: .78rem; font-weight: 700; }
        .lga-apply { background: #0f766e; color: #fff; }
        .lga-apply:hover { background: #115e59; }
        .lga-dismiss { background: #f3f4f6; color: #6b7280; }
        .lga-dismiss:hover { background: #e5e7eb; }
        .dark .lga-dismiss { background: rgba(255,255,255,.08); color: #d1d5db; }
        .lga-undo { background: #fee2e2; color: #b91c1c; }
        .lga-undo:hover { background: #fecaca; }
        .lga-empty { padding: 3rem; text-align: center; color: #9ca3af; }
        .lga-h3 { font-weight: 700; padding: 1rem 1.5rem .25rem; color: #111827; }
        .dark .lga-h3 { color: #f9fafb; }
    </style>

    <div class="lga">
        <div class="lga-cards">
            <div class="lga-card">
                <p class="lga-label">Pending suggestions</p>
                <p class="lga-value" style="color:#0f766e">{{ $stats->pending }}</p>
            </div>
            <div class="lga-card">
                <p class="lga-label">Links applied</p>
                <p class="lga-value" style="color:#16a34a">{{ $stats->applied }}</p>
            </div>
            <div class="lga-card">
                <p class="lga-label">Dictionary phrases</p>
                <p class="lga-value">{{ number_format($stats->phrases) }}</p>
            </div>
        </div>

        <div class="lga-toolbar">
            <input type="search" class="lga-input" wire:model.live.debounce.400ms="search" placeholder="Filter by product or post name…">
            <span class="lga-note">Suggest-only: nothing is ever applied without your click. Re-scans run weekly and after every content edit.</span>
            <div style="margin-left:auto">
                <x-filament::button wire:click="rebuild" wire:loading.attr="disabled" icon="heroicon-o-arrow-path">
                    <span wire:loading.remove wire:target="rebuild">Re-scan now</span>
                    <span wire:loading wire:target="rebuild">Scanning…</span>
                </x-filament::button>
            </div>
        </div>

        @forelse($groups as $suggestions)
            @php $first = $suggestions->first(); @endphp
            <div class="lga-panel">
                <div class="lga-group-head">
                    <span class="lga-group-title">
                        <span class="lga-kind">{{ class_basename($first->source_type) }}</span>
                        {{ $first->source->name ?? $first->source->title }}
                    </span>
                    <a href="{{ $first->source->url() }}" target="_blank" class="lga-note">view page ↗</a>
                </div>
                @foreach($suggestions as $s)
                    <div class="lga-row" wire:key="sg-{{ $s->id }}">
                        <span class="lga-score {{ $s->score >= 60 ? 'lga-score-high' : 'lga-score-mid' }}">{{ $s->score }}</span>
                        <div class="lga-body">
                            <p class="lga-sentence">
                                @php
                                    $pos = mb_stripos($s->sentence ?? '', $s->anchor);
                                @endphp
                                @if($pos !== false)
                                    {{ mb_substr($s->sentence, 0, $pos) }}<mark>{{ mb_substr($s->sentence, $pos, mb_strlen($s->anchor)) }}</mark>{{ mb_substr($s->sentence, $pos + mb_strlen($s->anchor)) }}
                                @else
                                    {{ $s->sentence }} → <mark>{{ $s->anchor }}</mark>
                                @endif
                            </p>
                            <p class="lga-target">
                                links to: <a href="{{ $s->target->url() }}" target="_blank">{{ $s->target->name ?? $s->target->title }}</a>
                                <span class="lga-kind" style="margin-left:.4rem">{{ class_basename($s->target_type) }}</span>
                            </p>
                        </div>
                        <div class="lga-actions">
                            <button type="button" class="lga-btn lga-apply" wire:click="apply({{ $s->id }})" wire:loading.attr="disabled">✓ Apply</button>
                            <button type="button" class="lga-btn lga-dismiss" wire:click="dismiss({{ $s->id }})" wire:loading.attr="disabled">✗ Dismiss</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="lga-panel"><div class="lga-empty">No pending suggestions{{ trim($search) !== '' ? ' matching your filter' : ' — click "Re-scan now" after adding content' }}.</div></div>
        @endforelse

        @if($applied->isNotEmpty())
            <div class="lga-panel">
                <h3 class="lga-h3">Recently applied ({{ $applied->count() }})</h3>
                @foreach($applied as $s)
                    <div class="lga-row" wire:key="ap-{{ $s->id }}">
                        <div class="lga-body">
                            <p class="lga-sentence"><mark>{{ $s->anchor }}</mark> in {{ $s->source->name ?? $s->source->title }}
                                → <a href="{{ $s->target->url() }}" target="_blank" style="color:#0f766e">{{ $s->target->name ?? $s->target->title }}</a></p>
                            <p class="lga-target">{{ $s->applied_at?->diffForHumans() }}</p>
                        </div>
                        <div class="lga-actions">
                            <button type="button" class="lga-btn lga-undo" wire:click="undo({{ $s->id }})" wire:loading.attr="disabled">Undo</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
