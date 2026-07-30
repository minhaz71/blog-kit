<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\SeoMeta;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * RankMath-style bulk/quick SEO editing: every product's SEO title, meta
 * description, focus keyword, canonical and robots in ONE table — edit
 * inline without opening each product. Bulk noindex/index actions, CSV
 * export and re-import for offline editing/migration.
 */
class SeoEditor extends Page implements HasTable
{
    use \App\Filament\Concerns\GatedByPermission;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'SEO editor';

    protected string $view = 'filament.pages.seo-editor';

    public function table(Table $table): Table
    {
        return $table
            ->query(Product::query()->with('seoMeta')->select(['id', 'name', 'slug', 'status']))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Product $record) => $record->url(), shouldOpenInNewTab: true)
                    ->description(fn (Product $record) => '/'.'product/'.$record->slug),
                TextInputColumn::make('seo_title')
                    ->label('SEO title (≤60)')
                    ->state(fn (Product $record) => $record->seoMeta?->title)
                    ->updateStateUsing(fn (Product $record, ?string $state) => $record->seoMeta()->updateOrCreate([], ['title' => mb_substr((string) $state, 0, 60)]))
                    ->rules(['nullable', 'string', 'max:60']),
                TextInputColumn::make('seo_description')
                    ->label('Meta description (≤155)')
                    ->state(fn (Product $record) => $record->seoMeta?->description)
                    ->updateStateUsing(fn (Product $record, ?string $state) => $record->seoMeta()->updateOrCreate([], ['description' => mb_substr((string) $state, 0, 155)]))
                    ->rules(['nullable', 'string', 'max:155']),
                TextInputColumn::make('focus_keyword')
                    ->label('Focus keyword')
                    ->state(fn (Product $record) => $record->seoMeta?->focus_keyword)
                    ->updateStateUsing(fn (Product $record, ?string $state) => $record->seoMeta()->updateOrCreate([], ['focus_keyword' => (string) $state])),
                TextInputColumn::make('canonical_url')
                    ->label('Canonical URL')
                    ->state(fn (Product $record) => $record->seoMeta?->canonical_url)
                    ->updateStateUsing(fn (Product $record, ?string $state) => $record->seoMeta()->updateOrCreate([], ['canonical_url' => (string) $state ?: null]))
                    ->rules(['nullable', 'url'])
                    ->toggleable(),
                ToggleColumn::make('noindex')
                    ->label('Noindex')
                    ->state(fn (Product $record) => (bool) $record->seoMeta?->noindex)
                    ->updateStateUsing(fn (Product $record, bool $state) => $record->seoMeta()->updateOrCreate([], ['noindex' => $state])),
                TextColumn::make('seoMeta.seo_score')
                    ->label('Score')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray',
                        $state >= 70 => 'success',
                        $state >= 40 => 'warning',
                        default => 'danger',
                    }),
            ])
            ->filters([
                TernaryFilter::make('missing_meta')
                    ->label('Missing SEO data')
                    ->queries(
                        true: fn (Builder $q) => $q->whereDoesntHave('seoMeta', fn ($m) => $m->whereNotNull('title')->where('title', '!=', '')),
                        false: fn (Builder $q) => $q->whereHas('seoMeta', fn ($m) => $m->whereNotNull('title')->where('title', '!=', '')),
                    ),
            ])
            ->toolbarActions([
                BulkAction::make('noindexSelected')
                    ->label('Set noindex')
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $records->each(fn (Product $p) => $p->seoMeta()->updateOrCreate([], ['noindex' => true]));
                        Notification::make()->title($records->count().' product(s) set to noindex.')->success()->send();
                    }),
                BulkAction::make('indexSelected')
                    ->label('Set index')
                    ->icon(Heroicon::OutlinedEye)
                    ->action(function (Collection $records) {
                        $records->each(fn (Product $p) => $p->seoMeta()->updateOrCreate([], ['noindex' => false]));
                        Notification::make()->title($records->count().' product(s) set to index.')->success()->send();
                    }),
            ])
            ->defaultSort('name')
            ->paginated([25, 50, 100]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function () {
                    return response()->streamDownload(function () {
                        $out = fopen('php://output', 'w');
                        fputcsv($out, ['slug', 'name', 'seo_title', 'meta_description', 'focus_keyword', 'canonical_url', 'noindex']);

                        Product::query()->with('seoMeta')->orderBy('id')->chunk(500, function ($products) use ($out) {
                            foreach ($products as $p) {
                                fputcsv($out, [
                                    $p->slug, $p->name,
                                    $p->seoMeta?->title, $p->seoMeta?->description,
                                    $p->seoMeta?->focus_keyword, $p->seoMeta?->canonical_url,
                                    $p->seoMeta?->noindex ? 1 : 0,
                                ]);
                            }
                        });

                        fclose($out);
                    }, 'seo-data-'.now()->format('Y-m-d').'.csv');
                }),
            Action::make('import')
                ->label('Import CSV')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->schema([
                    FileUpload::make('file')
                        ->label('SEO CSV (same columns as the export; rows matched by slug)')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->disk('local')
                        ->directory('seo-imports')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $updated = self::importCsv(\Illuminate\Support\Facades\Storage::disk('local')->path($data['file']));

                    Notification::make()->title("SEO data imported for {$updated} product(s).")->success()->send();
                }),
        ];
    }

    /** Import the export-format CSV; rows matched to products by slug. */
    public static function importCsv(string $path): int
    {
        $handle = fopen($path, 'r');
        $headers = null;
        $updated = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $row);

                continue;
            }

            $data = array_combine($headers, array_pad(array_slice($row, 0, count($headers)), count($headers), null));
            $product = Product::where('slug', (string) ($data['slug'] ?? ''))->first();

            if (! $product) {
                continue;
            }

            $product->seoMeta()->updateOrCreate([], array_filter([
                'title' => mb_substr((string) ($data['seo_title'] ?? ''), 0, 60) ?: null,
                'description' => mb_substr((string) ($data['meta_description'] ?? ''), 0, 155) ?: null,
                'focus_keyword' => (string) ($data['focus_keyword'] ?? '') ?: null,
                'canonical_url' => (string) ($data['canonical_url'] ?? '') ?: null,
            ], fn ($v) => $v !== null) + ['noindex' => (bool) ($data['noindex'] ?? false)]);

            $updated++;
        }

        fclose($handle);

        return $updated;
    }
}
