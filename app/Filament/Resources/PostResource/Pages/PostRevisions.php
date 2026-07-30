<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use App\Models\Post;
use App\Models\PostRevision;
use App\Support\TextDiff;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

/**
 * WordPress-style revision browser: every content edit is snapshotted with
 * who made it; pick any two versions (or a version vs the live post) and
 * see a word-level diff, then restore with one click. Restoring snapshots
 * the current version first, so nothing is ever lost.
 */
class PostRevisions extends Page
{
    use \Filament\Resources\Pages\Concerns\InteractsWithRecord;

    protected static string $resource = PostResource::class;

    protected string $view = 'filament.resources.post-revisions';

    protected static ?string $title = 'Revisions';

    /** Revision id being compared FROM ('0' = the live version). */
    public string $from = '';

    /** Revision id being compared TO ('0' = the live version). */
    public string $to = '0';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        // Default: newest snapshot vs the live post.
        $this->from = (string) ($this->record->revisions()->value('id') ?? '');
    }

    public function getTitle(): string
    {
        return 'Revisions — '.$this->record->title;
    }

    /** @return array{title: string, excerpt: ?string, content: ?string, label: string} */
    protected function version(string $id): array
    {
        if ($id === '0' || $id === '') {
            return [
                'title' => (string) $this->record->title,
                'excerpt' => $this->record->excerpt,
                'content' => $this->record->content,
                'label' => 'Current live version',
            ];
        }

        $revision = $this->record->revisions()->whereKey((int) $id)->firstOrFail();

        return [
            'title' => $revision->title,
            'excerpt' => $revision->excerpt,
            'content' => $revision->content,
            'label' => $revision->created_at->format('M j, Y g:ia').' · replaced by '.$revision->editorLabel(),
        ];
    }

    public function getViewData(): array
    {
        $revisions = $this->record->revisions()->with('editor')->get();

        $fromVersion = $this->version($this->from ?: '0');
        $toVersion = $this->version($this->to ?: '0');

        return [
            'revisions' => $revisions,
            'fromVersion' => $fromVersion,
            'toVersion' => $toVersion,
            'diff' => [
                'title' => TextDiff::changed($fromVersion['title'], $toVersion['title'])
                    ? TextDiff::html($fromVersion['title'], $toVersion['title']) : null,
                'excerpt' => TextDiff::changed($fromVersion['excerpt'], $toVersion['excerpt'])
                    ? TextDiff::html($fromVersion['excerpt'], $toVersion['excerpt']) : null,
                'content' => TextDiff::changed($fromVersion['content'], $toVersion['content'])
                    ? TextDiff::html($fromVersion['content'], $toVersion['content']) : null,
            ],
        ];
    }

    public function restore(int $revisionId): void
    {
        $revision = $this->record->revisions()->whereKey($revisionId)->firstOrFail();

        $this->record->restoreRevision($revision);
        $this->record->refresh();

        // Point the comparer at what just happened: old live vs new live.
        $this->from = (string) ($this->record->revisions()->value('id') ?? '');
        $this->to = '0';

        Notification::make()
            ->title('Revision restored')
            ->body('The previous live version was snapshotted first — nothing is lost.')
            ->success()
            ->send();
    }
}
