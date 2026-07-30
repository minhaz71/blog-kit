<x-filament-panels::page>
    {{-- Scoped styles (project convention: no arbitrary Tailwind in admin pages). --}}
    <style>
        .prv-grid { display: grid; gap: 1rem; }
        @media (min-width: 1024px) { .prv-grid { grid-template-columns: 320px 1fr; } }
        .prv-card { background: #fff; border: 1px solid #e5e7eb; border-radius: .75rem; overflow: hidden; }
        .dark .prv-card { background: #18181b; border-color: #27272a; }
        .prv-card h3 { font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; padding: .8rem 1rem; border-bottom: 1px solid #e5e7eb; }
        .dark .prv-card h3 { border-color: #27272a; }
        .prv-list { max-height: 34rem; overflow-y: auto; }
        .prv-item { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .65rem 1rem; border-bottom: 1px solid #f3f4f6; font-size: .82rem; }
        .dark .prv-item { border-color: #27272a; }
        .prv-item time { font-weight: 600; display: block; }
        .prv-item span { color: #6b7280; font-size: .74rem; }
        .prv-restore { color: #0f766e; font-weight: 600; font-size: .75rem; cursor: pointer; white-space: nowrap; }
        .prv-restore:hover { text-decoration: underline; }
        .prv-selects { display: flex; flex-wrap: wrap; gap: .75rem; padding: 1rem; border-bottom: 1px solid #e5e7eb; }
        .dark .prv-selects { border-color: #27272a; }
        .prv-selects label { font-size: .75rem; font-weight: 600; color: #6b7280; display: block; margin-bottom: .25rem; }
        .prv-selects select { border-radius: .5rem; border-color: #d1d5db; font-size: .82rem; min-width: 15rem; }
        .dark .prv-selects select { background: #27272a; border-color: #3f3f46; color: #e4e4e7; }
        .prv-diff { padding: 1rem 1.25rem; font-size: .9rem; line-height: 1.8; }
        .prv-diff h4 { font-size: .78rem; font-weight: 700; text-transform: uppercase; color: #6b7280; margin: 1.1rem 0 .35rem; }
        .prv-diff h4:first-child { margin-top: 0; }
        .prv-diff del { background: #fee2e2; color: #991b1b; text-decoration: line-through; border-radius: .2rem; padding: 0 .15rem; }
        .prv-diff ins { background: #d1fae5; color: #065f46; text-decoration: none; border-radius: .2rem; padding: 0 .15rem; }
        .dark .prv-diff del { background: #7f1d1d55; color: #fca5a5; }
        .dark .prv-diff ins { background: #06402955; color: #6ee7b7; }
        .prv-same { color: #9ca3af; font-style: italic; font-size: .84rem; }
        .prv-meta { font-size: .74rem; color: #6b7280; padding: 0 1.25rem .9rem; }
    </style>

    <div class="prv-grid">
        {{-- Left: the history --}}
        <div class="prv-card">
            <h3>History ({{ $revisions->count() }} snapshot{{ $revisions->count() === 1 ? '' : 's' }})</h3>
            <div class="prv-list">
                <div class="prv-item">
                    <div>
                        <time>Current live version</time>
                        <span>Last edited by {{ $record->lastEditor?->name ?: 'AI writer / system' }} · {{ $record->updated_at->format('M j, g:ia') }}</span>
                    </div>
                </div>
                @forelse($revisions as $revision)
                    <div class="prv-item">
                        <div>
                            <time>{{ $revision->created_at->format('M j, Y g:ia') }}</time>
                            <span>replaced by {{ $revision->editorLabel() }}</span>
                        </div>
                        <a class="prv-restore" wire:click="restore({{ $revision->id }})"
                           wire:confirm="Restore this version? The current live version is snapshotted first.">
                            Restore
                        </a>
                    </div>
                @empty
                    <div class="prv-item"><span>No snapshots yet — they appear when the title, excerpt or content changes.</span></div>
                @endforelse
            </div>
        </div>

        {{-- Right: the comparer --}}
        <div class="prv-card">
            <div class="prv-selects">
                <div>
                    <label for="prv-from">Compare from</label>
                    <select id="prv-from" wire:model.live="from">
                        <option value="0">Current live version</option>
                        @foreach($revisions as $revision)
                            <option value="{{ $revision->id }}">{{ $revision->created_at->format('M j, Y g:ia') }} · {{ $revision->editorLabel() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="prv-to">Compare to</label>
                    <select id="prv-to" wire:model.live="to">
                        <option value="0">Current live version</option>
                        @foreach($revisions as $revision)
                            <option value="{{ $revision->id }}">{{ $revision->created_at->format('M j, Y g:ia') }} · {{ $revision->editorLabel() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="prv-meta" style="padding-top:.9rem">
                <del style="background:#fee2e2;color:#991b1b;padding:0 .3rem;border-radius:.2rem;text-decoration:line-through">removed</del>
                = only in "{{ $fromVersion['label'] }}" ·
                <ins style="background:#d1fae5;color:#065f46;padding:0 .3rem;border-radius:.2rem;text-decoration:none">added</ins>
                = only in "{{ $toVersion['label'] }}"
            </p>
            <div class="prv-diff">
                <h4>Title</h4>
                @if($diff['title'])<p>{!! $diff['title'] !!}</p>@else<p class="prv-same">No change.</p>@endif

                <h4>Excerpt</h4>
                @if($diff['excerpt'])<p>{!! $diff['excerpt'] !!}</p>@else<p class="prv-same">No change.</p>@endif

                <h4>Content</h4>
                @if($diff['content'])<p>{!! $diff['content'] !!}</p>@else<p class="prv-same">No change.</p>@endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
