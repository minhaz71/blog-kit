<?php

use App\Support\AdminAccess;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Provision the new abandoned-cart admin permissions (additive & idempotent),
 * mirroring 2026_07_13_100000_seed_access_permissions. Super Admin already
 * bypasses every gate, but this creates the permission rows so the role matrix
 * can grant them to other roles, and grants them to Super Admin explicitly.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['access_abandoned_carts', 'access_abandoned_cart_settings'] as $key) {
            Permission::findOrCreate($key, 'web');
        }

        if ($superAdmin = Role::where('name', 'Super Admin')->where('guard_name', 'web')->first()) {
            $superAdmin->givePermissionTo(Permission::pluck('name')->all());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Leave permissions in place — removing them could lock admins out.
    }
};
