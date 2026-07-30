<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactMessageResource\Pages\ListContactMessages;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class ContactMessageResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 8;

    protected static ?string $label = 'Contact message';

    public static function getNavigationBadge(): ?string
    {
        $unread = ContactMessage::unread()->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('is_read')
                    ->label('')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedEnvelopeOpen)
                    ->falseIcon(Heroicon::Envelope)
                    ->trueColor('gray')
                    ->falseColor('warning'),
                TextColumn::make('name')->searchable()->weight(\Filament\Support\Enums\FontWeight::SemiBold),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('subject')->limit(30)->placeholder('—')->toggleable(),
                TextColumn::make('message')->limit(50)->wrap(),
                TextColumn::make('created_at')->dateTime()->sortable()->label('Received'),
            ])
            ->filters([
                TernaryFilter::make('is_read')->label('Read'),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('view')
                    ->label('Read')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading(fn (ContactMessage $r) => $r->subject ?: 'Message from '.$r->name)
                    ->modalContent(fn (ContactMessage $r) => new HtmlString(
                        '<div class="space-y-3 text-sm">'
                        .'<p><strong>'.e($r->name).'</strong> &lt;'.e($r->email).'&gt;'
                        .($r->phone ? ' · '.e($r->phone) : '').'</p>'
                        .'<p class="whitespace-pre-line">'.e($r->message).'</p>'
                        .'<p class="text-gray-400">'.$r->created_at->format('M j, Y g:ia').' · IP '.e((string) $r->ip_address).'</p>'
                        .'</div>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->after(fn (ContactMessage $r) => $r->update(['is_read' => true])),
                \Filament\Actions\Action::make('reply')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->url(fn (ContactMessage $r) => 'mailto:'.$r->email.'?subject=Re: '.rawurlencode($r->subject ?: 'Your message to us')),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('markRead')
                        ->label('Mark as read')
                        ->icon(Heroicon::OutlinedEnvelopeOpen)
                        ->action(fn ($records) => $records->each->update(['is_read' => true]))
                        ->deselectRecordsAfterCompletion(),
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactMessages::route('/'),
        ];
    }
}
