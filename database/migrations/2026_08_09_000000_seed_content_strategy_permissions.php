<?php

use App\Support\AdminAccess;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Provision the new access_* permissions added by the content-strategy work
 * (access_content_strategy, access_content_clusters) and grant them to the
 * roles that should have them, so a `git pull` + migrate on production gives
 * non-Super-Admin staff the new screens automatically.
 *
 * Same additive + idempotent pattern as 2026_07_13_100000: only ADDS, never
 * revokes — no signed-in user loses access. Re-provisioning every AdminAccess
 * key is safe (findOrCreate + additive grant) and future-proofs newly added
 * screens too.
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
        // Data provisioning only — leave permissions in place (removing them
        // could lock admins out).
    }
};
