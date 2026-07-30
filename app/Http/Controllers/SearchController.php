<?php

namespace App\Http\Controllers;

use App\Services\Search\ProductSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Live AJAX search suggestions. Lives under /search* so the guest page
 * cache never stores it; results themselves are cached per term in the
 * service.
 */
class SearchController extends Controller
{
    public function __construct(protected ProductSearch $search) {}

    public function suggest(Request $request): JsonResponse
    {
        if (! ProductSearch::enabled()) {
            return response()->json(['enabled' => false, 'results' => [], 'total' => 0])
                ->header('Cache-Control', 'no-store');
        }

        $term = trim((string) $request->query('q', ''));
        $payload = $this->search->suggest($term);

        // Log only when the frontend says the query has settled (user
        // paused typing) — keeps analytics clean of partial keystrokes.
        if ($request->boolean('log') && $term !== '') {
            $this->search->log($term, $payload['total']);
        }

        return response()->json([
            'enabled' => true,
            'term' => $payload['term'],
            'total' => $payload['total'],
            'results' => $payload['results'],
            'view_all' => route('search', ['q' => $term]),
        ])->header('Cache-Control', 'no-store');
    }
}
