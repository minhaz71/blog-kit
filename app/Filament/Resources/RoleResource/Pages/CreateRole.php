<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Support\AdminAccess;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        // expand: ticking a screen also grants the coarse action permission
        // its policy checks, so the role can actually use the screen.
        $role->syncPermissions(AdminAccess::expand(RoleResource::selectedPermissions($data)));

        return $role;
    }
}
