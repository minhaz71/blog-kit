<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            EmailTemplateSeeder::class,
        ]);

        // Super admin
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );
        if (! $admin->hasRole('Super Admin')) {
            $admin->assignRole('Super Admin');
        }

        // Store manager
        $manager = User::updateOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name' => 'Store Manager',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );
        if (! $manager->hasRole('Store Manager')) {
            $manager->assignRole('Store Manager');
        }

        // Sample customer
        User::updateOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Sample Customer',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        // Product templates are cheap and only surface when the store is on;
        // seed them either way so enabling ecommerce later "just works".
        $this->call([ProductTemplateSeeder::class]);

        if (ecommerce_enabled()) {
            // Full store demo (catalog, attributes, ecommerce homepage).
            $this->call([
                DemoCatalogSeeder::class,
                TereaAttributeSeeder::class,
                HomepageSeeder::class,
            ]);
        } else {
            // Blog-first demo: sample posts/categories + a blog homepage.
            $this->call([
                BlogDemoSeeder::class,
                BlogHomepageSeeder::class,
            ]);
        }

        $this->call([ContentBlockSeeder::class]);
    }
}
