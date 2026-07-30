<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Support\AdminAccess;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => $this->record->name !== RoleResource::SUPER_ADMIN),
        ];
    }

    /** Bucket the role's current permissions into the per-group checkbox fields. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $names = $this->record->permissions->pluck('name')->all();

        foreach (AdminAccess::groupedForMatrix() as $group => $items) {
            $keys = collect($items)->pluck('key')->all();
            $data[RoleResource::groupField($group)] = array_values(array_intersect($names, $keys));
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Super Admin is immutable — its power comes from Gate::before, so
        // never rename it or strip its permissions.
        if ($record->name === RoleResource::SUPER_ADMIN) {
            return $record;
        }

        $record->update(['name' => $data['name']]);
        $record->syncPermissions(AdminAccess::expand(RoleResource::selectedPermissions($data)));

        return $record;
    }
}
