<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Services\Seo\LlmsTxtGenerator;
use App\Services\Seo\MarkdownRenderer;
use App\Support\Permalinks;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the Markdown-for-agents representation of storefront pages at `.md`
 * URLs (e.g. /product/terea-amber.md). The same output is returned via HTTP
 * content negotiation by App\Http\Middleware\NegotiateMarkdown. Slugs are
 * looked up manually (not route-model-bound) so a ".md" suffix can never be
 * mistaken for part of the slug.
 */
class MarkdownController extends Controller
{
    public function __construct(protected MarkdownRenderer $renderer) {}

    public function home(LlmsTxtGenerator $llms): Response
    {
        // The homepage's markdown IS the site overview (llms.txt content).
        return $this->respond($llms->generate());
    }

    public function product(string $slug): Response
    {
        $product = Product::where('slug', $slug)->first();

        abort_unless($product && $product->status === 'published' && $product->visibility !== 'hidden', 404);

        return $this->respond($this->renderer->render($product));
    }

    public function category(string $slug): Response
    {
        $category = Category::where('slug', $slug)->first();

        abort_unless($category && $category->is_active, 404);

        return $this->respond($this->renderer->render($category));
    }

    public function post(string $slug): Response
    {
        $post = Post::where('slug', $slug)->first();

        abort_unless($post && $post->status === 'published', 404);

        return $this->respond($this->renderer->render($post));
    }

    /** Root-level `.md`: resolve like the permalink catch-all, then render. */
    public function root(string $slug): Response
    {
        $model = self::resolveRootModel($slug);

        abort_if($model === null, 404);

        return $this->respond($this->renderer->render($model));
    }

    /**
     * Resolve a bare slug to the entity the root catch-all would serve:
     * root-level product → root-level category → published page. Shared with
     * NegotiateMarkdown so both paths agree.
     */
    public static function resolveRootModel(string $slug): ?Model
    {
        if (Permalinks::base('product') === ''
            && $product = Product::where('slug', $slug)->where('status', 'published')->where('visibility', '!=', 'hidden')->first()) {
            return $product;
        }

        if (Permalinks::base('category') === ''
            && $category = Category::where('slug', $slug)->where('is_active', true)->first()) {
            return $category;
        }

        return Page::where('slug', $slug)->where('status', 'published')->first();
    }

    protected function respond(?string $markdown): Response
    {
        abort_unless((bool) setting('seo.markdown_for_agents', true), 404);
        abort_if($markdown === null || $markdown === '', 404);

        return response($markdown, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            // ~4 chars/token — lets agents estimate context cost (Cloudflare pattern).
            'X-Markdown-Tokens' => (string) (int) ceil(mb_strlen($markdown) / 4),
            'Vary' => 'Accept',
            // Keep the .md twin out of Google/Bing (it duplicates the HTML page).
            // Agents ignore X-Robots-Tag, so they still read markdown freely.
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
