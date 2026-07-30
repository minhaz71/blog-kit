<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Provision the new `access_ai_queue` permission (AI call queue dashboard) and
 * grant it to the roles that already manage AI product batches — same
 * audience, since the queue is where those batches' calls land. Additive and
 * idempotent; Super Admin gets everything via Gate::before regardless.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate('access_ai_queue', 'web');

        foreach (Role::where('guard_name', 'web')->get() as $role) {
            if ($role->name === 'Super Admin' || $role->hasPermissionTo('access_ai_product_batches')) {
                $role->givePermissionTo('access_ai_queue');
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Leave the permission in place — removing it can't lock anyone out.
    }
};
