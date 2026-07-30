<?php

namespace App\Services\Ai;

use App\Models\Category;
use Illuminate\Support\Collection;

/**
 * Sample import file shown to the user as a template. Images are NOT part
 * of the CSV — set a Google Drive folder on the batch form instead; each
 * product is matched to the folder image whose filename best matches the
 * product name (e.g. "amber kazakhstan.jpg" → "IQOS TEREA Amber Kazakhstan").
 *
 * Category assignment (two ways, combinable):
 * - category: names, multiple separated by | — created automatically when
 *   they don't exist yet. The simple path.
 * - category_id: existing category IDs — pins the product to EXACT
 *   categories, immune to typos and renames.
 *
 * The file opens with a "#" reference block listing every active category
 * (id + name) so the admin can see exactly which IDs/names exist and pick
 * the ones to keep. Lines starting with "#" are ignored on upload, so the
 * admin can edit in place and either keep or delete the reference.
 */
class SampleCsv
{
    public const FILENAME = 'sample-products-iqos-terea.csv';

    public const HEADER = ['name', 'regular_price', 'sale_price', 'short_description', 'specifications', 'brand', 'category', 'category_id', 'keywords'];

    public static function content(): string
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderByRaw('parent_id is null desc') // roots first
            ->orderBy('parent_id')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        return self::referenceBlock($categories).self::csvBody(self::exampleRows($categories));
    }

    /** Leading "#" comment block — the store's live category list. */
    protected static function referenceBlock(Collection $categories): string
    {
        $lines = ['# ===== YOUR CATEGORIES ====='];

        if ($categories->isEmpty()) {
            $lines[] = '# No categories yet. Type a name in the "category" column and it will be created on import.';
        } else {
            $lines[] = '# Put the id below in "category_id" for an exact match, or the name in "category".';
            $lines[] = '# Separate multiple categories with a pipe: e.g. 3|7  or  Heated Tobacco|TEREA UAE';
            $lines[] = '# id | name';

            $byId = $categories->keyBy('id');

            foreach ($categories as $category) {
                $parent = $category->parent_id ? ($byId[$category->parent_id]->name ?? null) : null;
                $lines[] = '# '.$category->id.' | '.$category->name.($parent ? '  (under '.$parent.')' : '');
            }
        }

        $lines[] = '# Lines starting with # are ignored on upload — keep or delete them.';
        $lines[] = '# =============================';

        return implode("\n", $lines)."\n";
    }

    /** @return array<int, array<int, string>> header row + example product rows */
    protected static function exampleRows(Collection $categories): array
    {
        $samples = [
            ['IQOS TEREA Amber', '32', '28', 'Rich, full-bodied roasted tobacco blend for IQOS ILUMA.', 'Flavor: Rich roasted tobacco | Pack: full carton only (10 packs x 20 sticks = 200 sticks) | Compatibility: IQOS ILUMA series | Strength: Regular | Origin: UAE edition', 'terea amber uae, buy iqos terea amber dubai, terea amber price'],
            ['IQOS TEREA Sienna', '32', '28', 'Balanced woody tobacco with subtle tea aroma for IQOS ILUMA.', 'Flavor: Woody tobacco, tea notes | Pack: full carton only (10 packs x 20 sticks = 200 sticks) | Compatibility: IQOS ILUMA series | Strength: Regular', 'terea sienna dubai, iqos sienna flavor'],
            ['IQOS TEREA Yellow', '32', '27', 'Smooth, mellow tobacco with delicate herbal notes.', 'Flavor: Mellow tobacco, herbal | Pack: full carton only (10 packs x 20 sticks = 200 sticks) | Compatibility: IQOS ILUMA series | Strength: Light', 'terea yellow uae, light terea flavor'],
            ['IQOS TEREA Bright Wave', '33', '29', 'Crisp menthol freshness over a light tobacco base.', 'Flavor: Menthol | Pack: full carton only (10 packs x 20 sticks = 200 sticks) | Compatibility: IQOS ILUMA series | Strength: Regular | Cooling: High', 'terea bright wave menthol, buy terea menthol dubai'],
            ['IQOS TEREA Purple Wave', '33', '29', 'Cooling menthol with a juicy berry finish.', 'Flavor: Menthol, berry | Pack: full carton only (10 packs x 20 sticks = 200 sticks) | Compatibility: IQOS ILUMA series | Strength: Regular | Cooling: High', 'terea purple wave uae, berry menthol terea'],
            ['IQOS TEREA Green Zing', '33', '29', 'Zesty citrus lift with a cooling menthol body.', 'Flavor: Citrus, menthol | Pack: full carton only (10 packs x 20 sticks = 200 sticks) | Compatibility: IQOS ILUMA series | Strength: Regular | Cooling: Medium', 'terea green zing uae, citrus menthol terea'],
        ];

        // Demonstrate assignment against up to 3 REAL categories so the admin
        // sees valid ids/names in context; fall back to name examples (which
        // get created on import) when the store has no categories yet.
        $demo = $categories->take(3)->values();

        $rows = [self::HEADER];

        foreach ($samples as $i => $s) {
            [$name, $regular, $sale, $desc, $specs, $keywords] = $s;

            if ($demo->isNotEmpty()) {
                $category = $demo[$i % $demo->count()];
                $categoryName = $category->name;
                $categoryId = (string) $category->id;
            } else {
                // No categories yet — the first row shows multi-assignment by name.
                $categoryName = $i === 0 ? 'Heated Tobacco|TEREA UAE' : 'Heated Tobacco';
                $categoryId = '';
            }

            $rows[] = [$name, $regular, $sale, $desc, $specs, 'IQOS', $categoryName, $categoryId, $keywords];
        }

        return $rows;
    }

    /** @param  array<int, array<int, string>>  $rows */
    protected static function csvBody(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
