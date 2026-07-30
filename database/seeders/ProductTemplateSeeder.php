<?php

namespace Database\Seeders;

use App\Models\ProductTemplate;
use Illuminate\Database\Seeder;

class ProductTemplateSeeder extends Seeder
{
    public function run(): void
    {
        ProductTemplate::updateOrCreate(
            ['is_default' => true],
            [
                'name' => 'Default single product',
                'settings' => ProductTemplate::defaultSettings(),
                'blocks' => ProductTemplate::defaultBlocks(),
            ],
        );
    }
}
