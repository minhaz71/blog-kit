<?php

use App\Support\AdminAccess;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Provision the access_* permissions added by the Keyword Research work
 * (access_keyword_research, access_research_settings) — and, because it
 * re-provisions EVERY current AdminAccess key, any other newly added screen —
 * so a `git pull` + migrate on production makes the new menu items appear
 * (they were hidden because their permission record did not yet exist).
 *
 * Additive + idempotent (findOrCreate + additive grant); never revokes. Same
 * pattern as 2026_08_09_000000_seed_content_strategy_permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (AdminAccess::allKeys() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allPermissionNames = Permission::pluck('name')->all();

        foreach (RolePermissionSeeder::defaultRolePermissions() as $name => $accessKeys) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();

            if (! $role) {
                continue;
            }

            $grant = $name === 'Super Admin'
                ? $allPermissionNames
                : AdminAccess::expand($accessKeys);

            $role->givePermissionTo($grant); // additive
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Data provisioning only — leave permissions in place.
    }
};
