<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Provision the `access_checkout_settings` permission (the new Checkout editor
 * page) and grant it to roles that already manage payment/store settings.
 * Additive + idempotent; Super Admin passes via Gate::before regardless.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate('access_checkout_settings', 'web');

        foreach (Role::where('guard_name', 'web')->get() as $role) {
            if ($role->name === 'Super Admin'
                || $role->hasPermissionTo('access_payment_settings')
                || $role->hasPermissionTo('access_general_settings')) {
                $role->givePermissionTo('access_checkout_settings');
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Leave the permission in place — removing it can't lock anyone out.
    }
};
