<?php

namespace App\Http\Controllers;

use App\Models\HomepageSection;
use App\Services\Seo\SeoManager;

class HomeController extends Controller
{
    public function __invoke(SeoManager $seo)
    {
        // Sections drive the homepage layout. If the admin has defined any,
        // they render in order; otherwise the default template hero+categories
        // sections in home.blade.php fall through.
        $sections = HomepageSection::active()->ordered()->get();

        return view('home', [
            'sections' => $sections,
            'seo' => $seo->forHome(),
        ]);
    }
}
