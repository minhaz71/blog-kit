<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BackupResource\Pages\ListBackups;
use App\Models\Backup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

class BackupResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = Backup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 96;

    protected static ?string $label = 'Backup';

    protected static ?string $pluralLabel = 'Backups';

    public static function canCreate(): bool
    {
        return false; // backups are created via the header actions, not a form
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Taken')->dateTime()->sortable(),
                TextColumn::make('type')->badge()->color(fn (string $state) => match ($state) {
                    'full' => 'success',
                    'database' => 'info',
                    default => 'gray',
                }),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'completed' => 'success',
                    'running' => 'warning',
                    'failed' => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('size')
                    ->label('Size')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1024, 1).' KB' : '—'),
                TextColumn::make('path')->label('File')->limit(50)->copyable()->toggleable(),
                TextColumn::make('error')->limit(60)->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->options(['database' => 'Database', 'full' => 'Full', 'files' => 'Files']),
                SelectFilter::make('status')->options(['completed' => 'Completed', 'failed' => 'Failed', 'running' => 'Running']),
            ])
            ->headerActions([
                Action::make('backupDatabase')
                    ->label('Back up database now')
                    ->icon(Heroicon::OutlinedCircleStack)
                    ->color('primary')
                    ->action(fn () => static::runBackup('database')),
                Action::make('backupFull')
                    ->label('Back up everything')
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Backs up the database AND all uploaded files + AI import CSVs. May take longer on large stores.')
                    ->action(fn () => static::runBackup('full')),
                Action::make('import')
                    ->label('Import backup file')
                    ->icon(Heroicon::OutlinedCloudArrowUp)
                    ->color('warning')
                    ->form([
                        \Filament\Forms\Components\FileUpload::make('archive')
                            ->label('ShopKit backup (.zip)')
                            ->required()
                            ->disk('local')
                            ->directory('backups/uploads')
                            ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                            ->maxSize(512 * 1024) // KB → 512 MB
                            ->helperText('Upload a backup made by ShopKit on any server. Compatibility (PHP, Laravel, ShopKit, database, migrations, checksum) is verified BEFORE anything is overwritten.'),
                    ])
                    ->modalSubmitActionLabel('Check & restore')
                    ->action(function (array $data): void {
                        $path = (string) $data['archive'];
                        $check = \App\Support\BackupCompatibility::check(storage_path('app/'.$path));

                        if (! $check->ok) {
                            Notification::make()
                                ->title('Restore blocked — backup is not compatible')
                                ->body(implode("\n", array_map(fn ($e) => '✖ '.$e, $check->errors)))
                                ->danger()->persistent()->send();

                            return;
                        }

                        $exit = Artisan::call('backup:restore', ['--path' => $path, '--force' => true]);

                        Notification::make()
                            ->title($exit === 0 ? 'Backup imported & restored' : 'Restore failed')
                            ->body($exit === 0
                                ? 'Site restored from the uploaded archive.'
                                .($check->warnings ? "\n".implode("\n", array_map(fn ($w) => '⚠ '.$w, $check->warnings)) : '')
                                .($check->notes ? "\n".implode("\n", array_map(fn ($n) => 'ℹ '.$n, $check->notes)) : '')
                                : 'Check storage/logs/laravel.log for details.')
                            ->{$exit === 0 ? 'success' : 'danger'}()
                            ->persistent()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('download')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (Backup $record) => $record->status === 'completed' && is_file(storage_path('app/'.$record->path)))
                    // Direct link to a streaming GET route — NOT a Livewire
                    // action — so large archives download instead of hanging.
                    ->url(fn (Backup $record) => route('admin.backups.download', $record))
                    ->openUrlInNewTab(),
                Action::make('check')
                    ->label('Check')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->color('info')
                    ->visible(fn (Backup $record) => $record->status === 'completed' && is_file(storage_path('app/'.$record->path)))
                    ->action(function (Backup $record): void {
                        $check = \App\Support\BackupCompatibility::check(storage_path('app/'.$record->path));
                        $lines = array_merge(
                            array_map(fn ($e) => '✖ '.$e, $check->errors),
                            array_map(fn ($w) => '⚠ '.$w, $check->warnings),
                            array_map(fn ($n) => 'ℹ '.$n, $check->notes),
                        );

                        Notification::make()
                            ->title($check->ok ? 'Compatible — safe to restore here' : 'NOT compatible with this server')
                            ->body($lines === [] ? 'All compatibility checks passed.' : implode("\n", $lines))
                            ->{$check->ok ? 'success' : 'danger'}()
                            ->persistent()
                            ->send();
                    }),
                Action::make('restore')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (Backup $record) => in_array($record->type, ['database', 'full'], true) && $record->status === 'completed')
                    ->requiresConfirmation()
                    ->modalHeading('Restore this backup?')
                    ->modalDescription('Compatibility is verified first, and a safety backup of the CURRENT database is taken automatically — so this action is undoable. The current database (and archived files) will then be OVERWRITTEN with this backup\'s contents.')
                    ->modalSubmitActionLabel('Yes, check & restore')
                    ->action(function (Backup $record): void {
                        $check = \App\Support\BackupCompatibility::check(storage_path('app/'.$record->path));

                        if (! $check->ok && ! $check->legacy) {
                            Notification::make()
                                ->title('Restore blocked — backup is not compatible')
                                ->body(implode("\n", array_map(fn ($e) => '✖ '.$e, $check->errors)))
                                ->danger()->persistent()->send();

                            return;
                        }

                        $args = ['--path' => $record->path, '--force' => true];

                        if ($check->legacy) {
                            // Pre-manifest archive made by THIS site — known origin.
                            $args['--skip-checks'] = true;
                        }

                        $exit = Artisan::call('backup:restore', $args);

                        Notification::make()
                            ->title($exit === 0 ? 'Database restored' : 'Restore failed')
                            ->body($exit === 0 ? 'Restored from '.basename($record->path) : 'Check storage/logs/laravel.log for details.')
                            ->{$exit === 0 ? 'success' : 'danger'}()
                            ->send();
                    }),
                DeleteAction::make()
                    ->before(fn (Backup $record) => @unlink(storage_path('app/'.$record->path))),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected static function runBackup(string $type): void
    {
        $exit = Artisan::call('backup:run', ['--type' => $type]);
        $backup = Backup::latest()->first();

        Notification::make()
            ->title($exit === 0 ? 'Backup created' : 'Backup failed')
            ->body($exit === 0
                ? ucfirst($type).' backup saved'.($backup ? ' ('.number_format(($backup->size ?? 0) / 1024, 1).' KB)' : '').'.'
                : 'Check that mysqldump is available and storage/app/backups is writable.')
            ->{$exit === 0 ? 'success' : 'danger'}()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBackups::route('/'),
        ];
    }
}
