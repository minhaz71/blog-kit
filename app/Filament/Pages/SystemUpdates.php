<?php

namespace App\Filament\Pages;

use App\Support\BackgroundProcess;
use App\Support\Preflight;
use App\Support\Version;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

/**
 * System → Updates: shows the running Hemdox BlogKit version, every tool's version,
 * git state, production-readiness checks, the changelog, and a guarded
 * one-click "Update Hemdox BlogKit" that runs `blogkit:update` detached in the
 * background (mandatory backup + auto-rollback live inside that command).
 * Super Admin only.
 */
class SystemUpdates extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Security';

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'Updates';

    protected string $view = 'filament.pages.system-updates';

    public ?int $behind = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }

    public function mount(): void
    {
        // Cheap read (no network) on load; "Check for updates" does the fetch.
        $this->behind = Version::commitsBehind(fetch: false);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('check')
                ->label('Check for updates')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(function (): void {
                    $this->behind = Version::commitsBehind(fetch: true);
                    Notification::make()
                        ->title($this->behind === null ? 'Could not check (not a git repo or no upstream)'
                            : ($this->behind === 0 ? 'You are on the latest version' : "{$this->behind} update(s) available"))
                        ->success()
                        ->send();
                }),
            Action::make('preflight')
                ->label('Run readiness check')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('gray')
                ->action(function (): void {
                    Artisan::call('blogkit:preflight');
                    Notification::make()->title('Readiness check complete')
                        ->body(trim(Artisan::output()))->success()->send();
                }),
            Action::make('update')
                ->label('Update Hemdox BlogKit')
                ->icon(Heroicon::OutlinedArrowUpCircle)
                ->color('primary')
                ->visible(fn () => Version::isGitRepo())
                ->requiresConfirmation()
                ->modalHeading('Update Hemdox BlogKit now?')
                ->modalDescription('This takes a full backup first, then pulls the new version, runs migrations and rebuilds. '
                    .'If anything fails it rolls back automatically to the current version and data. The site briefly enters maintenance mode.')
                ->modalSubmitActionLabel('Back up & update')
                ->action(function (): void {
                    $preflight = Preflight::summary();

                    if (! $preflight['ok']) {
                        Notification::make()
                            ->title('Update blocked')
                            ->body("Fix {$preflight['critical']} critical readiness issue(s) first (see the checklist below).")
                            ->danger()->send();

                        return;
                    }

                    $launched = BackgroundProcess::artisan(['blogkit:update']);

                    Notification::make()
                        ->title($launched ? 'Update started in the background' : 'Could not start the updater')
                        ->body($launched
                            ? 'It is backing up, then updating. Watch storage/logs/background.log, or run `php artisan blogkit:update` over SSH. The page will reflect the new version once it finishes.'
                            : 'Run `php artisan blogkit:update` over SSH instead (this environment cannot spawn a background process).')
                        ->color($launched ? 'success' : 'warning')
                        ->send();
                }),
        ];
    }

    public function getViewData(): array
    {
        return [
            'core' => Version::core(),
            'released' => Version::releasedAt(),
            'branch' => Version::gitBranch(),
            'commit' => Version::gitCommit(),
            'committedAt' => Version::gitCommittedAt(),
            'isGit' => Version::isGitRepo(),
            'behind' => $this->behind,
            'components' => Version::components(),
            'labels' => Version::COMPONENT_LABELS,
            'preflight' => Preflight::summary(),
            'changelog' => $this->changelog(),
        ];
    }

    /** First ~2 changelog entries, raw markdown → simple lines for display. */
    protected function changelog(): string
    {
        $path = base_path('CHANGELOG.md');

        if (! is_file($path)) {
            return '';
        }

        $md = (string) file_get_contents($path);
        // Keep everything up to the 3rd "## [" heading (2 releases).
        $parts = preg_split('/(?=^## \[)/m', $md);

        return trim(implode('', array_slice($parts, 0, 3)));
    }
}
