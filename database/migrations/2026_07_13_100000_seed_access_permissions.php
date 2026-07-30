<?php

use App\Support\AdminAccess;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Provision the per-screen access_* permissions and grant sensible defaults to
 * the built-in roles, so production picks up granular RBAC automatically on
 * `shopkit:update`. Additive and idempotent: it only ADDS permissions to
 * existing roles (never revokes), so no signed-in staff member loses access —
 * they simply gain the granular equivalents of what their role already did.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RolePermissionSeeder::COARSE as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        foreach (AdminAccess::allKeys() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allPermissionNames = Permission::pluck('name')->all();

        foreach (RolePermissionSeeder::defaultRolePermissions() as $name => $accessKeys) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();

            if (! $role) {
                continue; // don't invent roles an install chose to remove
            }

            $grant = $name === 'Super Admin'
                ? $allPermissionNames
                : AdminAccess::expand($accessKeys);

            $role->givePermissionTo($grant); // additive — never strips existing access
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Leave permissions in place; removing them could lock admins out and
        // this is a data-provisioning migration, not a schema change.
    }
};
