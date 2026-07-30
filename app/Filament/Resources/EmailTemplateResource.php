<?php

namespace App\Filament\Resources;

use App\Filament\Support\ResourceActions;
use App\Filament\Resources\EmailTemplateResource\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplateResource\Pages\ListEmailTemplates;
use App\Models\EmailTemplate;
use BackedEnum;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class EmailTemplateResource extends Resource
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static ?string $model = EmailTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('key')->required()->disabled()->dehydrated(),
                TextInput::make('name')->required(),
                TextInput::make('subject')->required()->columnSpanFull()
                    ->helperText('Use {{customer_name}}, {{order_number}}, {{order_total}}, etc.'),
                TextInput::make('heading'),
                Select::make('recipient')
                    ->label('Send to')
                    ->options(['customer' => 'Customer', 'admin' => 'Admin', 'custom' => 'Custom email(s)'])
                    ->default('customer')
                    ->required()
                    ->native(false)
                    ->live()
                    ->helperText('Who this email is primarily for.'),
                TextInput::make('custom_recipients')
                    ->label('Custom / extra recipients')
                    ->placeholder('warehouse@store.com, accounts@store.com')
                    ->helperText('Comma-separated addresses that ALSO receive this email (or the only recipients when "Send to" is Custom).')
                    ->required(fn ($get) => $get('recipient') === 'custom')
                    ->rule(static function (): \Closure {
                        return static function (string $attribute, $value, \Closure $fail): void {
                            foreach (preg_split('/[,;\s]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $addr) {
                                if (! filter_var(trim($addr), FILTER_VALIDATE_EMAIL)) {
                                    $fail("\"{$addr}\" is not a valid email address.");
                                }
                            }
                        };
                    }),
            ]),
            RichEditor::make('body')->required()->columnSpanFull()
                ->helperText('HTML content. Placeholders like {{customer_name}} will be replaced when the email is sent.'),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('key')->toggleable(),
                TextColumn::make('recipient')->badge(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (EmailTemplate $record) => route('admin.email-templates.preview', $record))
                    ->openUrlInNewTab(),
                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailTemplates::route('/'),
            'edit' => EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
