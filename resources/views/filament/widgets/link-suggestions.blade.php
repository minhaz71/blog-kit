<x-filament-widgets::widget>
    @if($suggestions->isNotEmpty())
        <style>
            .lsw { border-radius: 1rem; background: #fff; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 4px 16px rgba(15,23,42,.05); overflow: hidden; }
            .dark .lsw { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08); box-shadow: none; }
            .lsw-head { display: flex; align-items: center; gap: .6rem; padding: .9rem 1.5rem; background: #f0fdfa; border-bottom: 1px solid rgba(79,70,229,.15); font-weight: 700; font-size: .9rem; color: #134e4a; }
            .dark .lsw-head { background: rgba(99,102,241,.1); color: #5eead4; }
            .lsw-row { display: flex; gap: 1rem; align-items: flex-start; padding: .9rem 1.5rem; border-bottom: 1px solid rgba(0,0,0,.04); }
            .dark .lsw-row { border-color: rgba(255,255,255,.05); }
            .lsw-row:last-child { border-bottom: 0; }
            .lsw-score { flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 999px; font-weight: 800; font-size: .78rem; background: #dcfce7; color: #15803d; }
            .lsw-score.mid { background: #fef3c7; color: #b45309; }
            .lsw-body { flex: 1; min-width: 0; font-size: .85rem; color: #374151; line-height: 1.5; }
            .dark .lsw-body { color: #d1d5db; }
            .lsw-body mark { background: #99f6e4; color: #134e4a; border-radius: .25rem; padding: 0 .25rem; font-weight: 600; }
            .lsw-target { margin-top: .25rem; font-size: .72rem; color: #6b7280; }
            .lsw-target a { color: #0f766e; font-weight: 600; text-decoration: none; }
            .lsw-actions { display: flex; flex-shrink: 0; gap: .5rem; }
            .lsw-btn { border: 0; cursor: pointer; border-radius: .55rem; padding: .4rem .85rem; font-size: .75rem; font-weight: 700; }
            .lsw-apply { background: #0f766e; color: #fff; }
            .lsw-dismiss { background: #f3f4f6; color: #6b7280; }
            .dark .lsw-dismiss { background: rgba(255,255,255,.08); color: #d1d5db; }
            .lsw-note { padding: .6rem 1.5rem .9rem; font-size: .7rem; color: #9ca3af; }
        </style>

        <div class="lsw">
            <div class="lsw-head">🔗 Internal link suggestions ({{ $suggestions->count() }})</div>
            @foreach($suggestions as $s)
                <div class="lsw-row" wire:key="lsw-{{ $s->id }}">
                    <span class="lsw-score {{ $s->score < 60 ? 'mid' : '' }}">{{ $s->score }}</span>
                    <div class="lsw-body">
                        @php $pos = mb_stripos($s->sentence ?? '', $s->anchor); @endphp
                        @if($pos !== false)
                            {{ mb_substr($s->sentence, 0, $pos) }}<mark>{{ mb_substr($s->sentence, $pos, mb_strlen($s->anchor)) }}</mark>{{ mb_substr($s->sentence, $pos + mb_strlen($s->anchor)) }}
                        @else
                            {{ $s->sentence }} → <mark>{{ $s->anchor }}</mark>
                        @endif
                        <p class="lsw-target">links to: <a href="{{ $s->target->url() }}" target="_blank">{{ $s->target->name ?? $s->target->title }}</a></p>
                    </div>
                    <div class="lsw-actions">
                        <button type="button" class="lsw-btn lsw-apply" wire:click="apply({{ $s->id }})" wire:loading.attr="disabled">✓ Apply</button>
                        <button type="button" class="lsw-btn lsw-dismiss" wire:click="dismiss({{ $s->id }})" wire:loading.attr="disabled">✗</button>
                    </div>
                </div>
            @endforeach
            <p class="lsw-note">Applying inserts the link into the saved content and reloads this page so the editor stays in sync. Manage all suggestions in SEO → Link agent.</p>
        </div>
    @endif
</x-filament-widgets::widget>
