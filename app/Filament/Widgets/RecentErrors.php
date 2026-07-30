<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ErrorLogResource;
use App\Models\ErrorLog;
use App\Support\AdminAccess;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Latest unresolved application errors on the admin dashboard, so an admin
 * sees breakage at a glance and can jump straight to the full log to fix
 * it. Only shown to users who can access the error log.
 */
class RecentErrors extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Latest errors';

    public static function canView(): bool
    {
        return AdminAccess::allows(ErrorLogResource::class)
            && ErrorLog::where('resolved', false)->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ErrorLog::query()->where('resolved', false)->latest('last_seen_at')->limit(5))
            ->columns([
                TextColumn::make('exception_class')->label('Type')
                    ->formatStateUsing(fn (ErrorLog $r) => $r->shortClass())->badge()->color('gray'),
                TextColumn::make('message')->limit(70)->wrap(),
                TextColumn::make('status_code')->label('HTTP')->badge()
                    ->color(fn ($state) => $state >= 500 ? 'danger' : 'warning'),
                TextColumn::make('occurrences')->label('Count')->alignCenter()->badge()
                    ->color(fn ($state) => $state > 10 ? 'danger' : 'warning'),
                TextColumn::make('last_seen_at')->label('Last seen')->since(),
            ])
            ->recordUrl(fn () => ErrorLogResource::getUrl())
            ->paginated(false)
            ->heading('Latest errors')
            ->description('Unresolved application errors — open the Error log to view details and fix them.');
    }
}
