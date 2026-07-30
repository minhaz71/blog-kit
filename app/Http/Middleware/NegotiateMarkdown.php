<?php

namespace App\Http\Middleware;

use App\Http\Controllers\MarkdownController;
use App\Services\Seo\LlmsTxtGenerator;
use App\Services\Seo\MarkdownRenderer;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Markdown-for-agents via HTTP content negotiation: when a client sends
 * `Accept: text/markdown` for a storefront content page, return the clean
 * markdown representation instead of HTML (~80% fewer tokens, easier to cite).
 *
 * Runs early in the appended web stack — after MaintenanceMode (a closed site
 * still says 503) but before the guest page cache, so a markdown request is
 * never answered with cached HTML. Falls through to HTML for anything it can't
 * render, and adds `Vary: Accept` so shared caches keep the two apart.
 */
class NegotiateMarkdown
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->wantsMarkdown($request)) {
            return $next($request);
        }

        $markdown = $this->markdownFor($request);

        if ($markdown === null || $markdown === '') {
            // Not a renderable page — serve HTML, but tell caches it varied.
            $response = $next($request);
            $response->headers->set('Vary', 'Accept', false);

            return $response;
        }

        return response($markdown, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'X-Markdown-Tokens' => (string) (int) ceil(mb_strlen($markdown) / 4),
            'Vary' => 'Accept',
            // Markdown twin must never be indexed as duplicate content. (In
            // practice search crawlers send Accept: text/html and never reach
            // here — this is belt-and-suspenders for any that don't.)
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    protected function wantsMarkdown(Request $request): bool
    {
        return $request->isMethod('GET')
            && ! $request->is('admin', 'admin/*', 'api/*', 'livewire/*')
            && str_contains((string) $request->header('Accept'), 'text/markdown')
            && (bool) setting('seo.markdown_for_agents', true);
    }

    protected function markdownFor(Request $request): ?string
    {
        $route = $request->route();
        $name = (string) $route?->getName();
        $renderer = app(MarkdownRenderer::class);

        $model = match ($name) {
            'product.show' => $route->parameter('product'),
            'category.show' => $route->parameter('category'),
            'blog.show' => $route->parameter('post'),
            'page.show' => MarkdownController::resolveRootModel((string) $route->parameter('slug')),
            default => null,
        };

        if ($model instanceof Model) {
            return $renderer->render($model);
        }

        if ($name === 'home') {
            return app(LlmsTxtGenerator::class)->generate();
        }

        return null;
    }
}
