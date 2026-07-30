<?php

namespace App\Filament\Pages;

use App\Models\ProductImage;
use App\Services\Seo\ImageSeoRules;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Livewire\WithPagination;
use UnitEnum;

/**
 * Media library: every uploaded product image (gallery AND featured — the
 * observer/media:sync-featured keep featured images as media records) in a
 * tiles or list view. Everything about an image is editable except its
 * permalink — the URL is already referenced by products, so it stays fixed
 * (use the Image SEO page's "SEO rename" if a URL change is really wanted).
 */
class MediaLibrary extends Page
{
    use \App\Filament\Concerns\GatedByPermission;
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 40;

    protected static ?string $title = 'Media library';

    protected string $view = 'filament.pages.media-library';

    public string $search = '';

    public string $view_mode = 'tiles'; // tiles | list

    public bool $missingOnly = false;

    // ── Edit panel state ────────────────────────────────────────────
    public ?int $editingId = null;

    public string $editAlt = '';

    public string $editTitle = '';

    public string $editCaption = '';

    public string $editSort = '0';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedMissingOnly(): void
    {
        $this->resetPage();
    }

    public function setView(string $mode): void
    {
        $this->view_mode = in_array($mode, ['tiles', 'list'], true) ? $mode : 'tiles';
    }

    public function edit(int $id): void
    {
        $image = ProductImage::find($id);

        if (! $image) {
            return;
        }

        $this->editingId = $image->id;
        $this->editAlt = (string) $image->alt;
        $this->editTitle = (string) $image->title;
        $this->editCaption = (string) $image->caption;
        $this->editSort = (string) $image->sort_order;
    }

    public function closeEdit(): void
    {
        $this->editingId = null;
    }

    public function save(): void
    {
        $image = ProductImage::find($this->editingId);

        if (! $image) {
            return;
        }

        $image->update([
            'alt' => mb_substr(trim($this->editAlt), 0, ImageSeoRules::ALT_MAX) ?: null,
            'title' => mb_substr(trim($this->editTitle), 0, ImageSeoRules::TITLE_MAX) ?: null,
            'caption' => mb_substr(trim($this->editCaption), 0, ImageSeoRules::CAPTION_MAX) ?: null,
            'sort_order' => max(0, (int) $this->editSort),
        ]);

        $this->editingId = null;

        Notification::make()->title('Image details saved.')->success()->send();
    }

    /** Details for the edit panel — file facts computed only when open. */
    protected function editingImage(): ?object
    {
        if (! $this->editingId) {
            return null;
        }

        $image = ProductImage::with('product:id,name,slug,featured_image')->find($this->editingId);

        if (! $image) {
            return null;
        }

        $disk = Storage::disk('public');
        $exists = $disk->exists($image->path);
        $dimensions = null;

        if ($exists && ($info = @getimagesize($disk->path($image->path)))) {
            $dimensions = $info[0].' × '.$info[1].' px';
        }

        return (object) [
            'model' => $image,
            'url' => $image->url(),
            'filename' => basename($image->path),
            'isFeatured' => $image->product?->featured_image === $image->path,
            'sizeKb' => $exists ? (int) round($disk->size($image->path) / 1024) : null,
            'dimensions' => $dimensions,
            'lint' => ImageSeoRules::lint($image),
        ];
    }

    protected function getViewData(): array
    {
        $images = ProductImage::query()
            ->with('product:id,name,slug,featured_image')
            ->when(trim($this->search) !== '', function ($q) {
                $needle = '%'.trim($this->search).'%';
                $q->where(fn ($w) => $w
                    ->where('path', 'like', $needle)
                    ->orWhere('alt', 'like', $needle)
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', $needle)));
            })
            ->when($this->missingOnly, fn ($q) => $q->where(fn ($w) => $w->whereNull('alt')->orWhere('alt', '')))
            ->orderByDesc('id')
            ->paginate($this->view_mode === 'tiles' ? 36 : 20);

        return [
            'images' => $images,
            'totalImages' => ProductImage::count(),
            'editing' => $this->editingImage(),
        ];
    }
}
