<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Canonical vocabulary the AI product writer classifies against (semantic
 * SEO structured attributes) instead of only describing these facts in
 * free-text copy. Admins can add more values later in Catalog > Attributes;
 * ProductPublisher::resolveAttributes() flags anything the AI proposes that
 * isn't in this list yet, rather than silently dropping it.
 */
class TereaAttributeSeeder extends Seeder
{
    public function run(): void
    {
        $this->seed('flavor-family', 'Flavor Family', 'select', [
            'Regular / Tobacco', 'Menthol', 'Balanced Regular', 'Bold Regular', 'Aromatic', 'Fruit-Infused',
        ]);

        $this->seed('cooling-level', 'Cooling Level', 'select', [
            'None', 'Light', 'Medium', 'Strong',
        ]);

        $this->seed('tobacco-strength', 'Tobacco Strength', 'select', [
            'Light', 'Medium', 'Regular', 'Strong', 'Extra Strong',
        ]);

        $this->seed('nicotine-format', 'Nicotine Format', 'select', [
            'Standard Heated Tobacco', 'Reduced Nicotine',
        ]);

        $this->seed('origin-country', 'Origin / Country', 'select', [
            'Kazakhstan', 'Poland', 'Italy', 'Romania', 'Malaysia', 'Japan', 'Indonesia',
        ]);

        $this->seed('device-compatibility', 'Device Compatibility', 'button', [
            'IQOS ILUMA', 'IQOS ILUMA i', 'IQOS ILUMA i PRIME', 'IQOS ILUMA PRIME',
        ]);

        $this->seed('pack-size', 'Pack Size', 'select', [
            '1 Pack (20 sticks)', '1 Carton (10 packs / 200 sticks)',
        ]);
    }

    protected function seed(string $slug, string $name, string $type, array $values): void
    {
        $attribute = Attribute::updateOrCreate(['slug' => $slug], ['name' => $name, 'type' => $type]);

        foreach ($values as $i => $value) {
            AttributeValue::updateOrCreate(
                ['attribute_id' => $attribute->id, 'slug' => Str::slug($value)],
                ['value' => $value, 'sort_order' => $i],
            );
        }
    }
}
