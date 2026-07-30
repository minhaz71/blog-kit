<?php

namespace Database\Seeders;

use App\Support\AdminAccess;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Coarse "manage *" permissions — still used by the model policies
     * (app/Policies) and the storefront preview checks. The per-screen
     * access_* permissions (App\Support\AdminAccess) imply these, so ticking
     * a screen in the role matrix also grants the matching action ability.
     */
    public const COARSE = [
        'manage products', 'manage orders', 'manage customers', 'manage coupons',
        'manage reviews', 'manage shipping', 'manage payments', 'manage emails',
        'manage seo', 'manage content', 'manage security', 'manage settings',
        'manage users', 'view reports',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::COARSE as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        foreach (AdminAccess::allKeys() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allPermissionNames = Permission::pluck('name')->all();

        foreach (self::defaultRolePermissions() as $name => $accessKeys) {
            $role = Role::findOrCreate($name, 'web');

            $role->syncPermissions(
                $name === 'Super Admin'
                    ? $allPermissionNames
                    : AdminAccess::expand($accessKeys),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Default role → access_* screen keys (pre-expansion). Admins adjust these
     * freely afterward in Admin → Roles.
     *
     * @return array<string, list<string>>
     */
    public static function defaultRolePermissions(): array
    {
        $all = AdminAccess::allKeys();

        // Content/SEO people edit content, products and SEO, but NOT AI
        // batches, AI settings or general settings (per the store owner's rule).
        $editor = array_values(array_diff(
            array_merge(AdminAccess::keysForGroups(['Content', 'SEO']), ['access_products', 'access_categories']),
            ['access_ai_blog_batches'],
        ));

        return [
            'Super Admin' => $all,
            'Admin' => array_values(array_diff($all, ['access_roles', 'access_staff_users'])),
            'Store Manager' => AdminAccess::keysForGroups(['Catalog', 'Sales', 'Customers', 'Marketing']),
            'SEO Manager' => $editor,
            'Content Editor' => $editor,
            'Order Manager' => AdminAccess::keysForGroups(['Sales', 'Customers']),
            'Customer Support' => ['access_orders', 'access_customers', 'access_reviews'],
        ];
    }
}
