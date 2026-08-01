<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * API keys + default models for the AI product publisher.
 *
 * @property-read Schema $form
 */
class AiSettings extends Page
{
    use \App\Filament\Concerns\GatedByPermission;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 95;

    protected static ?string $title = 'AI settings';

    protected string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    protected function group(): string
    {
        return 'ai';
    }

    protected function keys(): array
    {
        return [
            'anthropic_api_key', 'anthropic_model', 'anthropic_extra_models',
            'openai_api_key', 'openai_model', 'openai_extra_models',
            'gemini_api_key', 'gemini_model', 'gemini_extra_models',
            'google_drive_api_key',
            'image_provider', 'image_model', 'image_size', 'image_quality', 'image_style',
            'default_system_prompt',
        ];
    }

    public function mount(): void
    {
        $values = Setting::group($this->group());
        $data = [];
        foreach ($this->keys() as $key) {
            $data[$key] = $values[$key] ?? null;
        }
        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Claude (Anthropic)')
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedSparkles)
                ->iconColor('primary')
                ->description(new HtmlString('Get an API key at <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener" class="underline text-primary-600">console.anthropic.com → API keys</a> · <a href="https://docs.anthropic.com/en/api/getting-started" target="_blank" rel="noopener" class="underline text-primary-600">setup guide</a>'))
                ->columns(2)->schema([
                TextInput::make('anthropic_api_key')->password()->revealable()->label('API key'),
                \Filament\Forms\Components\Select::make('anthropic_model')
                    ->label('Default model')
                    ->options(\App\Models\AiImportBatch::MODELS['anthropic'])
                    ->native(false)
                    ->searchable()
                    ->placeholder('claude-sonnet-5 (recommended)'),
                \Filament\Forms\Components\Textarea::make('anthropic_extra_models')
                    ->label('Extra models (one per line)')
                    ->rows(2)
                    ->placeholder("model-id | Optional label")
                    ->helperText('New model shipped? Add its id here — it appears in every model dropdown, no code change.')
                    ->columnSpanFull(),
            ]),
            Section::make('GPT (OpenAI)')
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedSparkles)
                ->iconColor('success')
                ->description(new HtmlString('Get an API key at <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener" class="underline text-primary-600">platform.openai.com → API keys</a> · <a href="https://platform.openai.com/docs/quickstart" target="_blank" rel="noopener" class="underline text-primary-600">setup guide</a>'))
                ->columns(2)->schema([
                TextInput::make('openai_api_key')->password()->revealable()->label('API key'),
                \Filament\Forms\Components\Select::make('openai_model')
                    ->label('Default model')
                    ->options(\App\Models\AiImportBatch::MODELS['openai'])
                    ->native(false)
                    ->searchable()
                    ->placeholder('gpt-4o-mini (recommended)'),
                \Filament\Forms\Components\Textarea::make('openai_extra_models')
                    ->label('Extra models (one per line)')
                    ->rows(2)
                    ->placeholder("model-id | Optional label")
                    ->helperText('New model shipped? Add its id here — it appears in every model dropdown, no code change.')
                    ->columnSpanFull(),
            ]),
            Section::make('Gemini (Google)')
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedSparkles)
                ->iconColor('info')
                ->description(new HtmlString('Get a free API key at <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener" class="underline text-primary-600">aistudio.google.com → Get API key</a> · <a href="https://ai.google.dev/gemini-api/docs/api-key" target="_blank" rel="noopener" class="underline text-primary-600">setup guide</a>'))
                ->columns(2)->schema([
                TextInput::make('gemini_api_key')->password()->revealable()->label('API key'),
                \Filament\Forms\Components\Select::make('gemini_model')
                    ->label('Default model')
                    ->options(\App\Models\AiImportBatch::MODELS['gemini'])
                    ->native(false)
                    ->searchable()
                    ->placeholder('gemini-2.0-flash (recommended)'),
                \Filament\Forms\Components\Textarea::make('gemini_extra_models')
                    ->label('Extra models (one per line)')
                    ->rows(2)
                    ->placeholder("model-id | Optional label")
                    ->helperText('New model shipped? Add its id here — it appears in every model dropdown, no code change.')
                    ->columnSpanFull(),
            ]),
            Section::make('Default system prompt')
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedPencilSquare)
                ->iconColor('warning')
                ->description('Used by product-writing batches that don\'t set their own. Leave empty for the built-in default.')
                ->schema([
                    \Filament\Forms\Components\Textarea::make('default_system_prompt')
                        ->hiddenLabel()
                        ->rows(5)
                        ->placeholder(\App\Services\Ai\ProductWriter::DEFAULT_SYSTEM),
                ]),
            Section::make('AI thumbnail images')
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedPhoto)
                ->iconColor('success')
                ->description(new HtmlString('Generate a blog thumbnail from the article title with ONE image request (no revision). Uses the API key of the selected provider above. <strong>Recommended: OpenAI gpt-image-1.</strong>'))
                ->columns(2)
                ->schema([
                    \Filament\Forms\Components\Select::make('image_provider')
                        ->label('Image provider / model')
                        ->options(\App\Services\Ai\ImageGenerator::PROVIDER_LABELS)
                        ->default('openai')
                        ->native(false)
                        ->helperText('Uses that provider\'s API key set above.'),
                    TextInput::make('image_model')
                        ->label('Model id (optional)')
                        ->placeholder('gpt-image-1')
                        ->helperText('Leave blank for the provider default (gpt-image-1 / imagen-3.0-generate-002).'),
                    \Filament\Forms\Components\Select::make('image_size')
                        ->label('Size')
                        ->options([
                            '1536x1024' => 'Landscape 1536×1024 (thumbnail)',
                            '1024x1024' => 'Square 1024×1024',
                            '1024x1536' => 'Portrait 1024×1536',
                        ])
                        ->default('1536x1024')
                        ->native(false),
                    \Filament\Forms\Components\Select::make('image_quality')
                        ->label('Quality (OpenAI)')
                        ->options(['low' => 'Low (cheapest)', 'medium' => 'Medium', 'high' => 'High'])
                        ->default('medium')
                        ->native(false),
                    \Filament\Forms\Components\TextInput::make('image_style')
                        ->label('Default style')
                        ->placeholder('modern flat editorial illustration, soft lighting')
                        ->helperText('Appended to the title-based prompt. A per-batch or per-row style overrides this.')
                        ->columnSpanFull(),
                ]),
            Section::make('Google Drive')
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedPhoto)
                ->iconColor('gray')
                ->description(new HtmlString('Create a key at <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener" class="underline text-primary-600">Google Cloud Console → Credentials</a>, then <a href="https://console.cloud.google.com/apis/library/drive.googleapis.com" target="_blank" rel="noopener" class="underline text-primary-600">enable the Drive API</a>. The image folder must be shared as “anyone with the link can view”. <a href="https://developers.google.com/drive/api/guides/api-specific-auth" target="_blank" rel="noopener" class="underline text-primary-600">Full guide</a>'))
                ->schema([
                    TextInput::make('google_drive_api_key')->password()->revealable()->label('Drive API key'),
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
            'old_values' => ['redacted' => true],
            'new_values' => ['redacted' => true],
            'ip_address' => request()->ip(),
        ]);

        Notification::make()->title('AI settings saved')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnections')
                ->label('Test all endpoints')
                ->icon(\Filament\Support\Icons\Heroicon::OutlinedBolt)
                ->color('info')
                ->action(function (): void {
                    $results = [];

                    foreach (array_keys(\App\Models\AiImportBatch::PROVIDERS) as $provider) {
                        if (trim((string) setting("ai.{$provider}_api_key")) === '') {
                            $results[] = "⚪ ".ucfirst($provider).": no API key saved — skipped.";

                            continue;
                        }

                        try {
                            [$ok, $message] = \App\Services\Ai\LlmClient::for($provider)
                                ->withContext('healthcheck')
                                ->healthCheck();
                            $results[] = ($ok ? '✅ ' : '❌ ').ucfirst($provider).': '.$message;
                        } catch (\Throwable $e) {
                            $results[] = '❌ '.ucfirst($provider).': '.$e->getMessage();
                        }
                    }

                    $allOk = ! str_contains(implode('', $results), '❌');

                    \Filament\Notifications\Notification::make()
                        ->title($allOk ? 'All configured endpoints are OK' : 'Some endpoints failed')
                        ->body(implode("\n\n", $results))
                        ->{$allOk ? 'success' : 'danger'}()
                        ->persistent()
                        ->send();
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return [Action::make('save')->label('Save changes')->action('save')->color('primary')];
    }
}
