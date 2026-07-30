<?php

namespace App\Http\Middleware;

use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Models\SlugHistory;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirect manager: exact + regex redirects, 410 gone, slug-history 301s,
 * and 404 monitoring. Runs before route resolution result is finalized.
 */
class HandleRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->method() !== 'GET' || $request->is('admin/*', 'admin', 'api/*', 'livewire/*')) {
            return $next($request);
        }

        $path = Redirect::normalizePath('/'.$request->path());

        if ($redirect = $this->match($path)) {
            $redirect->recordHit();

            if ($redirect->status_code === 410) {
                abort(410);
            }

            $target = str_starts_with((string) $redirect->target, 'http')
                ? $redirect->target
                : url($redirect->resolved_target ?? $redirect->target);

            return redirect()->away($target, $redirect->status_code);
        }

        $response = $next($request);

        if ($response->getStatusCode() === 404) {
            // Old slug? 301 to the new URL before logging a 404.
            if ($newUrl = $this->resolveSlugHistory($path)) {
                return redirect($newUrl, 301);
            }

            NotFoundLog::track(
                $request->getRequestUri(),
                $request->headers->get('referer'),
                $request->userAgent(),
                $request->ip(),
            );
        }

        return $response;
    }

    protected function match(string $path): ?Redirect
    {
        // Query directly — active redirects are cheap to fetch and caching a full
        // Eloquent collection into some cache stores (database) surfaces
        // __PHP_Incomplete_Class errors during unserialize.
        $redirects = Redirect::active()->get();

        foreach ($redirects as $redirect) {
            if (! $redirect->is_regex) {
                if (Redirect::normalizePath($redirect->source) === $path) {
                    return $redirect;
                }

                continue;
            }

            $pattern = '#'.str_replace('#', '\#', $redirect->source).'#i';

            if (@preg_match($pattern, $path, $matches)) {
                if ($matches !== [] && $matches[0] !== '') {
                    // Support $1-style backreferences in regex targets.
                    $redirect->resolved_target = $redirect->target !== null
                        ? preg_replace($pattern, $redirect->target, $path)
                        : null;

                    return $redirect;
                }
            }
        }

        return null;
    }

    protected function resolveSlugHistory(string $path): ?string
    {
        $segments = array_values(array_filter(explode('/', $path)));

        if (count($segments) < 1 || count($segments) > 2) {
            return null;
        }

        // The meaningful slug is the last segment: /{slug} or /{base}/{slug}.
        $slug = end($segments);

        // 1. Renamed slug → 301 to the new canonical URL (slug history).
        $history = SlugHistory::where('old_slug', $slug)->latest()->first();

        if ($history && ($model = $history->sluggable) && method_exists($model, 'url')) {
            if ($this->pathDiffers($model->url(), $path)) {
                return $model->url();
            }
        }

        // 2. Slug unchanged but its URL shape moved (permalink base changed):
        //    the old-shaped URL now 404s, so send it to the current canonical.
        foreach ([\App\Models\Product::class, \App\Models\Category::class, \App\Models\Post::class] as $class) {
            $model = $class::where('slug', $slug)->first();

            if ($model && method_exists($model, 'url') && $this->pathDiffers($model->url(), $path)) {
                return $model->url();
            }
        }

        return null;
    }

    /** True when the target URL's path is a different page than the request path. */
    protected function pathDiffers(string $targetUrl, string $requestPath): bool
    {
        $targetPath = parse_url($targetUrl, PHP_URL_PATH) ?: $targetUrl;

        return Redirect::normalizePath($targetPath) !== Redirect::normalizePath($requestPath);
    }
}
