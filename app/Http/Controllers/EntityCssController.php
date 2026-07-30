<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Http\Response;

/**
 * Serves per-entity custom CSS as a real stylesheet so it is cacheable
 * by the browser and CDN, and only loads on the page that owns it.
 */
class EntityCssController extends Controller
{
    private const TYPES = [
        'product' => Product::class,
        'category' => Category::class,
        'post' => Post::class,
        'page' => Page::class,
    ];

    public function show(string $type, int $id): Response
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        $model = self::TYPES[$type]::query()->find($id);

        abort_if($model === null || empty($model->custom_css), 404);

        return response($model->custom_css, 200, [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
            'ETag' => '"'.md5($model->updated_at?->timestamp.$model->custom_css).'"',
            'X-LiteSpeed-Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
