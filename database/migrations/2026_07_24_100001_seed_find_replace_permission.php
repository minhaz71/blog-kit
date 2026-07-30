<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Provision the Find & Replace admin permission (additive & idempotent).
 * Super Admin already bypasses every gate; this creates the row so the role
 * matrix can grant it to other roles, and grants it to Super Admin explicitly.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate('access_find_replace', 'web');

        if ($superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first()) {
            $superAdmin->givePermissionTo('access_find_replace');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Leave the permission in place — removing it could lock admins out.
    }
};
