<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\Seo\SeoManager;

class PageController extends Controller
{
    public function show(Page $page, SeoManager $seo)
    {
        $isPreview = $page->status !== 'published'
            && (request()->user()?->can('manage content') ?? false);

        abort_unless($page->status === 'published' || $isPreview, 404);

        $page->load('faqs');

        // System pages with dedicated routes redirect there.
        $systemRoutes = [
            'cart' => 'cart.index',
            'checkout' => 'checkout.index',
            'my-account' => 'account.dashboard',
            'shop' => 'shop',
        ];

        if (isset($systemRoutes[$page->slug])) {
            return redirect()->route($systemRoutes[$page->slug]);
        }

        return view('pages.show', [
            'isPreview' => $isPreview,
            'page' => $page,
            'seo' => $seo->forPage($page),
        ]);
    }
}
