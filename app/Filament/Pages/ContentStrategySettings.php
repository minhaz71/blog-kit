<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Ai\FunnelPlanner;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Content Strategy — the knobs behind the Content Cluster & Funnel Builder that
 * used to be hardcoded: where decision-stage articles lead, the top/middle/
 * bottom mix, cluster sizing, the canonical-guard threshold, and (store on) the
 * comparison facet axes.
 *
 * @property-read Schema $form
 */
class ContentStrategySettings extends Page
{
    use \App\Filament\Concerns\GatedByPermission;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFunnel;

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 40;

    protected static ?string $title = 'Content strategy';

    protected string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    protected function group(): string
    {
        return 'funnel';
    }

    protected function keys(): array
    {
        return [
            'bottom_target',
            'articles_per_cluster',
            'similarity_threshold',
            'mix_top', 'mix_middle', 'mix_bottom',
            'min_cluster_links',
            'comparison_facets',
            'auto_categorize', 'max_categories', 'max_root_categories',
        ];
    }

    public function mount(): void
    {
        $values = Setting::group($this->group());
        $data = [];
        foreach ($this->keys() as $key) {
            $data[$key] = $values[$key] ?? null;
        }
        // Sensible defaults so the form is never blank on first open.
        $data['bottom_target'] ??= FunnelPlanner::bottomTarget();
        $data['articles_per_cluster'] ??= 12;
        $data['similarity_threshold'] ??= FunnelPlanner::SIMILARITY_LIMIT;
        $data['mix_top'] ??= 45;
        $data['mix_middle'] ??= 35;
        $data['mix_bottom'] ??= 20;
        $data['min_cluster_links'] ??= 2;
        $data['auto_categorize'] ??= true;
        $data['max_categories'] ??= 20;
        $data['max_root_categories'] ??= 6;

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Funnel')
                ->icon(Heroicon::OutlinedFunnel)
                ->iconColor('primary')
                ->description('How the Content Cluster & Funnel Builder shapes the top → middle → bottom journey.')
                ->columns(2)
                ->schema([
                    Select::make('bottom_target')
                        ->label('Bottom-funnel destination')
                        ->options([
                            'pillar' => 'Pillar guide + related articles (pure blog)',
                            'product' => 'Product / category pages (store on)',
                            'affiliate' => 'Affiliate links (disclosed, rel=sponsored)',
                            'newsletter' => 'Newsletter signup',
                        ])
                        ->native(false)
                        ->helperText('Where decision-stage ("best X", buying guide) articles send the reader.')
                        ->columnSpanFull(),
                    TextInput::make('mix_top')->label('% Top (awareness)')->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                    TextInput::make('mix_middle')->label('% Middle (consideration)')->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                    TextInput::make('mix_bottom')->label('% Bottom (decision)')->numeric()->minValue(0)->maxValue(100)->suffix('%')
                        ->helperText('Rough target spread across a research run. Normalized automatically; they need not sum to exactly 100.'),
                ]),
            Section::make('Clusters')
                ->icon(Heroicon::OutlinedShare)
                ->iconColor('info')
                ->description('Cluster sizing and the canonical guard that stops new titles cannibalizing existing ones.')
                ->columns(2)
                ->schema([
                    TextInput::make('articles_per_cluster')
                        ->label('Target articles per cluster')
                        ->numeric()->minValue(4)->maxValue(40)
                        ->helperText('A run of N titles is split into ~N ÷ this many clusters (clamped 3–12 clusters).'),
                    TextInput::make('similarity_threshold')
                        ->label('Canonical-guard similarity (0.3–0.9)')
                        ->numeric()->minValue(0.3)->maxValue(0.9)->step(0.05)
                        ->helperText('Higher = stricter: a title this similar to an existing one is dropped. Default 0.6.'),
                    TextInput::make('min_cluster_links')
                        ->label('Min internal links per cluster member')
                        ->numeric()->minValue(0)->maxValue(10)
                        ->helperText('Target used by the Cluster link-health report (spokes ↔ pillar).')
                        ->columnSpanFull(),
                ]),
            Section::make('Categories (auto-taxonomy)')
                ->icon(Heroicon::OutlinedRectangleGroup)
                ->iconColor('success')
                ->description(new HtmlString('Blog categories build themselves from your clusters: each cluster becomes a sub-category grouped under an AI-named mother category, filed and added to the menu automatically. Run <strong>Content → Content clusters → Build category tree</strong> to (re)build.'))
                ->columns(2)
                ->schema([
                    \Filament\Forms\Components\Toggle::make('auto_categorize')
                        ->label('Auto-categorize new posts')
                        ->default(true)
                        ->helperText('File each new AI post under its cluster category, creating one if needed.')
                        ->columnSpanFull(),
                    TextInput::make('max_categories')
                        ->label('Max total categories')
                        ->numeric()->minValue(1)->maxValue(50)
                        ->helperText('Hard cap. Over-cap clusters attach to their mother instead of a new sub-category.'),
                    TextInput::make('max_root_categories')
                        ->label('Max mother categories')
                        ->numeric()->minValue(1)->maxValue(20)
                        ->helperText('How many top-level sections clusters are grouped into.'),
                ]),
            Section::make('Comparisons (store)')
                ->icon(Heroicon::OutlinedScale)
                ->iconColor('warning')
                ->description(new HtmlString('Only used when the store module is on. The product attribute <strong>slugs</strong> used to pair products for "X vs Y" comparison articles. One per line or comma-separated. Leave empty to use the defaults (<code>flavor-family, cooling-level, tobacco-strength</code>).'))
                ->schema([
                    Textarea::make('comparison_facets')
                        ->hiddenLabel()
                        ->rows(3)
                        ->placeholder("flavor-family\ncooling-level\ntobacco-strength"),
                ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        $group = $this->group();
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            Setting::set("{$group}.{$key}", $value);
        }
        Cache::forget("settings.{$group}");

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'settings_changed',
            'subject' => "settings:{$group}",
            'old_values' => null,
            'new_values' => $data,
            'ip_address' => request()->ip(),
        ]);

        Notification::make()->title('Content strategy settings saved')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [Action::make('save')->label('Save changes')->action('save')->color('primary')];
    }
}
