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

                    if (! BackgroundProcess::canSpawn()) {
                        // Be honest: this host forbids background processes, so
                        // record it and show the exact SSH command rather than a
                        // false "started".
                        \App\Support\UpdateStatus::begin(Version::core());
                        \App\Support\UpdateStatus::finish(false,
                            'This server blocks background processes (proc_open disabled). Run the update over SSH: php artisan blogkit:update');

                        Notification::make()
                            ->title('Cannot start the updater on this host')
                            ->body('Run `php artisan blogkit:update` over SSH. See the status panel below for details.')
                            ->color('warning')->persistent()->send();

                        return;
                    }

                    $launched = BackgroundProcess::artisan(['blogkit:update']);

                    Notification::make()
                        ->title($launched ? 'Update started in the background' : 'Could not start the updater')
                        ->body($launched
                            ? 'Backing up, then updating. Progress and the log appear in the status panel below (it refreshes itself).'
                            : 'Run `php artisan blogkit:update` over SSH instead (this environment cannot spawn a background process).')
                        ->color($launched ? 'success' : 'warning')
                        ->send();
                }),
        ];
    }

    /**
     * Host diagnostics for the common "update did nothing / no log" causes, so
     * an owner sees WHY without SSH. Each entry: [ok, label, detail].
     *
     * @return array<int,array{ok:bool,label:string,detail:string}>
     */
    protected function diagnostics(): array
    {
        $base = base_path();
        $canSpawn = BackgroundProcess::canSpawn();

        return [
            [
                'ok' => Version::isGitRepo(),
                'label' => 'Git checkout',
                'detail' => Version::isGitRepo() ? 'Site is a git repo — self-update works.' : 'Not a git checkout — deploy via git to enable updates.',
            ],
            [
                'ok' => $canSpawn,
                'label' => 'Background process',
                'detail' => $canSpawn
                    ? 'This host can run the updater in the background.'
                    : 'proc_open is disabled — the button cannot spawn the updater. Run `php artisan blogkit:update` over SSH (or via a cron worker).',
            ],
            [
                'ok' => is_writable($base),
                'label' => 'Writable code dir',
                'detail' => is_writable($base) ? 'The code directory is writable (git pull can apply).' : "Not writable by the web user: {$base}",
            ],
            [
                'ok' => is_writable(storage_path('logs')),
                'label' => 'Writable logs',
                'detail' => is_writable(storage_path('logs')) ? 'Logs are writable — update output is captured.' : 'storage/logs is not writable — update output cannot be saved.',
            ],
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
            'update' => \App\Support\UpdateStatus::get(),
            'canSpawn' => BackgroundProcess::canSpawn(),
            'diagnostics' => $this->diagnostics(),
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
