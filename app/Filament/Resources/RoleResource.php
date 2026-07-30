<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GatedByPermission;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Support\AdminAccess;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use UnitEnum;

/**
 * Role manager with a per-area checkbox matrix. Each checkbox is one admin
 * screen (from App\Support\AdminAccess); ticking it grants that role access
 * to the screen. Super Admin is protected: its boxes are read-only and it
 * cannot be renamed or deleted (its power comes from the Gate::before bypass,
 * so it can never be locked out).
 */
class RoleResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $label = 'Role';

    protected static ?string $pluralLabel = 'Roles & permissions';

    public const SUPER_ADMIN = 'Super Admin';

    /** Per-group field name used by the matrix (stable slug). */
    public static function groupField(string $group): string
    {
        return 'perms_'.Str::slug($group);
    }

    public static function form(Schema $schema): Schema
    {
        $sections = [];

        foreach (AdminAccess::groupedForMatrix() as $group => $items) {
            $sections[] = Section::make($group)
                ->description("Screens in the {$group} area")
                ->collapsible()
                ->schema([
                    // Not a model column — the Create/Edit pages read these
                    // out of the form data and sync them to the role's
                    // permissions (see RoleResource::selectedPermissions).
                    CheckboxList::make(self::groupField($group))
                        ->hiddenLabel()
                        ->options(collect($items)->pluck('label', 'key')->all())
                        ->columns(2)
                        ->bulkToggleable()
                        ->disabled(fn (?Role $record): bool => $record?->name === self::SUPER_ADMIN),
                ]);
        }

        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->disabled(fn (?Role $record): bool => $record?->name === self::SUPER_ADMIN)
                ->helperText('Assign this role to staff in Staff users.'),
            ...$sections,
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('semibold'),
                TextColumn::make('permissions_count')->counts('permissions')->label('Screens allowed')->alignCenter(),
                TextColumn::make('users_count')->counts('users')->label('Staff')->alignCenter(),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make()
                    ->visible(fn (Role $record): bool => $record->name !== self::SUPER_ADMIN),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }

    /** All ticked permission keys across every group field in the submitted form data. */
    public static function selectedPermissions(array $formState): array
    {
        $keys = [];

        foreach (AdminAccess::groupedForMatrix() as $group => $items) {
            $field = self::groupField($group);
            if (! empty($formState[$field]) && is_array($formState[$field])) {
                $keys = array_merge($keys, $formState[$field]);
            }
        }

        return array_values(array_unique($keys));
    }
}
