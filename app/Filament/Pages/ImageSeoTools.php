<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\ProductImage;
use App\Models\Setting;
use App\Services\Ai\LlmClient;
use App\Services\Seo\ImageSeoRules;
use App\Services\Seo\ImageSeoWriter;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use UnitEnum;

/**
 * Image SEO Pro: a gallery of every product image with its thumbnail,
 * owning product, filename, and inline-editable alt/title/caption —
 * plus AI generation (provider + model selectable), rulebook lint,
 * SEO filename rename, find & replace, and auto-fill.
 */
class ImageSeoTools extends Page
{
    use \App\Filament\Concerns\GatedByPermission;
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 40;

    protected static ?string $title = 'Image SEO';

    protected string $view = 'filament.pages.image-seo-tools';

    // ── Gallery filters ─────────────────────────────────────────────
    public string $search = '';

    public bool $missingOnly = false;

    // ── AI settings (persisted) ─────────────────────────────────────
    public string $aiProvider = 'anthropic';

    public string $aiModel = '';

    // ── Find & replace ──────────────────────────────────────────────
    public string $findText = '';

    public string $replaceText = '';

    /** @var array<string> */
    public array $fields = ['alt', 'title', 'caption'];

    public ?int $previewCount = null;

    public function mount(): void
    {
        $this->aiProvider = (string) setting('seo.image_ai_provider', 'anthropic');
        $this->aiModel = (string) setting('seo.image_ai_model', '');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedMissingOnly(): void
    {
        $this->resetPage();
    }

    // ── Inline editing ──────────────────────────────────────────────
    public function updateMeta(int $id, string $field, ?string $value): void
    {
        if (! in_array($field, ['alt', 'title', 'caption'], true)) {
            return;
        }

        $max = match ($field) {
            'alt' => ImageSeoRules::ALT_MAX,
            'title' => ImageSeoRules::TITLE_MAX,
            'caption' => ImageSeoRules::CAPTION_MAX,
        };

        ProductImage::whereKey($id)->update([$field => mb_substr(trim((string) $value), 0, $max) ?: null]);
    }

    // ── AI generation ───────────────────────────────────────────────
    public function generateForImage(int $id): void
    {
        $image = ProductImage::with('product')->find($id);

        if (! $image) {
            return;
        }

        $this->runAi(collect([$image]), overwrite: true);
    }

    public function generateMissing(): void
    {
        $images = ProductImage::query()
            ->with('product')
            ->where(fn ($q) => $q->whereNull('alt')->orWhere('alt', '')->orWhereNull('title')->orWhere('title', ''))
            ->orderBy('id')
            ->limit(ImageSeoWriter::BATCH)
            ->get();

        if ($images->isEmpty()) {
            Notification::make()->title('Nothing missing — every image already has alt and title text.')->success()->send();

            return;
        }

        $this->runAi($images, overwrite: false);
    }

    protected function runAi($images, bool $overwrite): void
    {
        // Persist the chosen provider/model so the next visit remembers it.
        Setting::set('seo.image_ai_provider', $this->aiProvider);
        Setting::set('seo.image_ai_model', trim($this->aiModel));

        try {
            @set_time_limit(300);

            $updated = app(ImageSeoWriter::class)->generate(
                $images,
                $this->aiProvider,
                trim($this->aiModel) ?: null,
                overwrite: $overwrite,
            );

            Notification::make()
                ->title("AI wrote metadata for {$updated} image(s) via {$this->aiProvider}.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('AI generation failed')
                ->body(mb_substr($e->getMessage(), 0, 300))
                ->danger()
                ->send();
        }
    }

    // ── Find & replace ──────────────────────────────────────────────
    public function preview(): void
    {
        if (trim($this->findText) === '' || $this->allowedFields() === []) {
            $this->previewCount = null;

            return;
        }

        $this->previewCount = ProductImage::query()
            ->where(function ($q) {
                foreach ($this->allowedFields() as $field) {
                    $q->orWhereRaw("{$field} LIKE ? ESCAPE '!'", [$this->likeNeedle()]);
                }
            })
            ->count();
    }

    public function apply(): void
    {
        $fields = $this->allowedFields();

        if (trim($this->findText) === '' || $fields === []) {
            Notification::make()->title('Enter the text to find and pick at least one field.')->warning()->send();

            return;
        }

        $updated = 0;

        foreach ($fields as $field) {
            $updated += ProductImage::query()
                ->whereRaw("{$field} LIKE ? ESCAPE '!'", [$this->likeNeedle()])
                ->update([$field => DB::raw('REPLACE('.$field.', '.DB::getPdo()->quote($this->findText).', '.DB::getPdo()->quote($this->replaceText).')')]);
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'image_seo_replace',
            'subject' => 'product_images',
            'new_values' => ['search' => $this->findText, 'replace' => $this->replaceText, 'fields' => $fields, 'updated' => $updated],
            'ip_address' => request()->ip(),
        ]);

        $this->previewCount = null;

        Notification::make()->title("Replaced across {$updated} image field value(s).")->success()->send();
    }

    /** Fill EMPTY alt (and title) from the owning product's name. */
    public function autoFill(): void
    {
        $filledAlt = DB::update(
            "UPDATE product_images SET alt = (SELECT name FROM products WHERE products.id = product_images.product_id)
             WHERE (alt IS NULL OR alt = '') AND product_id IN (SELECT id FROM products)"
        );

        $filledTitle = DB::update(
            "UPDATE product_images SET title = (SELECT name FROM products WHERE products.id = product_images.product_id)
             WHERE (title IS NULL OR title = '') AND product_id IN (SELECT id FROM products)"
        );

        Notification::make()
            ->title("Auto-filled {$filledAlt} alt text(s) and {$filledTitle} title(s) from product names.")
            ->success()
            ->send();
    }

    protected function allowedFields(): array
    {
        return array_values(array_intersect($this->fields, ['alt', 'title', 'caption']));
    }

    /** LIKE needle with portable escaping (! as the escape character). */
    protected function likeNeedle(): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $this->findText).'%';
    }

    protected function getViewData(): array
    {
        $images = ProductImage::query()
            ->with('product:id,name,slug')
            ->when(trim($this->search) !== '', function ($q) {
                $needle = '%'.trim($this->search).'%';
                $q->where(fn ($w) => $w
                    ->where('path', 'like', $needle)
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', $needle)));
            })
            ->when($this->missingOnly, fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('alt')->orWhere('alt', '')->orWhereNull('title')->orWhere('title', '')))
            ->orderByDesc('id')
            ->paginate(20);

        return [
            'images' => $images,
            'lint' => $images->getCollection()->mapWithKeys(
                fn (ProductImage $image) => [$image->id => ImageSeoRules::lint($image)],
            ),
            'totalImages' => ProductImage::count(),
            'missingAlt' => ProductImage::where(fn ($q) => $q->whereNull('alt')->orWhere('alt', ''))->count(),
            'missingTitle' => ProductImage::where(fn ($q) => $q->whereNull('title')->orWhere('title', ''))->count(),
            'providers' => \App\Models\AiImportBatch::PROVIDERS,
            'defaultModel' => LlmClient::defaultModel($this->aiProvider),
        ];
    }
}
