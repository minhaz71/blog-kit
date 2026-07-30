<?php

namespace App\Http\Controllers;

use App\Services\Seo\SitemapGenerator;

class SeoController extends Controller
{
    public function robots()
    {
        // "Discourage search engines" (Admin → SEO settings) blocks the whole
        // site regardless of any custom robots.txt override.
        if (setting('seo.discourage_indexing')) {
            return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $content = setting('seo.robots_txt') ?: $this->defaultRobots();

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemapIndex(SitemapGenerator $sitemaps)
    {
        return response($sitemaps->index(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function sitemapSection(string $section, SitemapGenerator $sitemaps)
    {
        // "products" → page 1; "products-2" → page 2. The section list is
        // fixed, so parse the trailing page number off known names only.
        $page = 1;

        if (! array_key_exists($section, SitemapGenerator::SECTIONS)
            && preg_match('/^(.+)-(\d+)$/', $section, $m)
            && array_key_exists($m[1], SitemapGenerator::SECTIONS)) {
            [$section, $page] = [$m[1], (int) $m[2]];
        }

        $xml = $sitemaps->section($section, $page);

        abort_if($xml === null, 404);

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** IndexNow spec: GET /{key}.txt returns the key as plain text. */
    public function indexNowKey(string $key)
    {
        abort_unless(hash_equals(\App\Services\Seo\IndexNow::key(), $key), 404);

        return response($key, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** llms.txt — AI answer-engine site map (also at /.well-known/llms.txt). */
    public function llmsTxt(\App\Services\Seo\LlmsTxtGenerator $generator)
    {
        return response($generator->generate(), 200, ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    /** llms-full.txt — key pages concatenated into one markdown document. */
    public function llmsFullTxt(\App\Services\Seo\LlmsTxtGenerator $generator)
    {
        return response($generator->generateFull(), 200, ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    /** /.well-known/agents.json — minimal read-only agent discovery manifest. */
    public function agentsJson(\App\Services\Seo\AgentsJsonGenerator $generator)
    {
        abort_unless((bool) setting('seo.agents_json', true), 404);

        return response()->json($generator->manifest(), 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * /.well-known/api-catalog (RFC 9727) — an application/linkset+json
     * catalogue of the store's read-only machine surfaces. Honest and
     * informational: no transactional/auth flows are advertised.
     */
    public function apiCatalog()
    {
        $anchor = url('/');

        $items = [
            ['href' => url('/feeds/products.xml'), 'type' => 'application/xml', 'title' => 'Product feed'],
            ['href' => route('sitemap.index'), 'type' => 'application/xml', 'title' => 'Sitemap'],
            ['href' => url('/search/suggest').'?q={query}', 'type' => 'application/json', 'title' => 'On-site product search'],
        ];

        $linkset = [
            'linkset' => [array_filter([
                'anchor' => $anchor,
                'service-desc' => [['href' => url('/.well-known/agents.json'), 'type' => 'application/json']],
                'service-doc' => [['href' => url('/llms.txt'), 'type' => 'text/markdown']],
                'item' => $items,
            ])],
        ];

        return response()->json($linkset, 200, [
            'Content-Type' => 'application/linkset+json',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /** Google Merchant / Bing Merchant product feed (free organic listings). */
    public function merchantFeed(\App\Services\Seo\MerchantFeedGenerator $feed)
    {
        abort_unless(\App\Services\Seo\MerchantFeedGenerator::enabled(), 404);

        return response($feed->xml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** RFC 9116 security.txt — the standard security-contact disclosure. */
    public function securityTxt()
    {
        $contact = setting('general.contact_email') ?: setting('emails.admin_recipient');

        abort_unless($contact, 404);

        $content = "Contact: mailto:{$contact}\n"
            .'Expires: '.now()->addYear()->startOfDay()->toIso8601String()."\n"
            .'Canonical: '.url('/.well-known/security.txt')."\n"
            .'Preferred-Languages: en'."\n";

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * Default robots.txt (the SEO settings override wins when set).
     *
     * Policy: welcome search engines AND AI answer engines (they cite us and
     * drive referral traffic), but block crawlers whose purpose is AI model
     * TRAINING. Expressed two ways for maximum coverage:
     *   1. Content-Signal directives (contentsignals.org / IETF draft) — the
     *      declarative "search=yes, ai-input=yes, ai-train=no" preference.
     *   2. Explicit per-bot groups — belt-and-suspenders for crawlers that read
     *      allow/deny but not (yet) content signals.
     *
     * A named user-agent group fully OVERRIDES the "*" group (a crawler obeys
     * only its most specific match), so every welcomed AI bot must REPEAT the
     * private-area disallows — else it would crawl cart/checkout/auth/faceted
     * URLs that "*" is told to skip. One shared $block list guarantees parity.
     */
    protected function defaultRobots(): string
    {
        $sitemap = route('sitemap.index');
        $llms = url('/llms.txt');

        // Single source of truth for paths no crawler should index.
        // NOTE: `.md` markdown twins are intentionally NOT disallowed — they
        // carry `X-Robots-Tag: noindex`, which a crawler can only honour if it
        // is allowed to fetch them. Blocking here would defeat the noindex.
        $disallow = [
            '/admin', '/admin/', '/hmmail/', '/livewire/', '/api/', '/webhooks/',
            '/cart', '/checkout', '/my-account', '/wishlist',
            '/login', '/register', '/password', '/two-factor', '/search',
            // Filtered/sorted URL noise (canonicals + noindex also set)
            '/*?attr*', '/*?sort=', '/*?min_price=', '/*?max_price=',
        ];
        $block = implode("\n", array_map(fn ($p) => "Disallow: {$p}", $disallow));

        // Product/category images must stay crawlable for Google Images.
        $allowImages = "Allow: /storage/products/\nAllow: /storage/categories/";

        // AI crawlers used mainly for SEARCH / real-time citation — welcomed,
        // because they cite the store and send buyers back to it.
        $aiSearchBots = [
            'OAI-SearchBot', 'ChatGPT-User',        // OpenAI: SearchGPT + user fetches
            'Claude-SearchBot', 'Claude-User',      // Anthropic: search + user fetches
            'PerplexityBot', 'Perplexity-User',     // Perplexity answer engine
            'Amazonbot', 'Applebot',                // Alexa / Siri / Spotlight
        ];

        // AI crawlers whose purpose is MODEL TRAINING / dataset harvesting —
        // blocked to match ai-train=no (Content-Signal above).
        $aiTrainingBots = [
            'GPTBot',                 // OpenAI training crawler
            'ClaudeBot', 'anthropic-ai', 'Claude-Web',
            'Google-Extended',        // Gemini training/grounding opt-out token
            'Applebot-Extended',      // Apple Intelligence training opt-out
            'CCBot',                  // Common Crawl (feeds most training sets)
            'Bytespider',             // ByteDance
            'meta-externalagent', 'FacebookBot',
            'cohere-ai', 'cohere-training-data-crawler',
            'Diffbot', 'Omgilibot', 'Timpibot', 'PanguBot', 'ImagesiftBot',
        ];

        $out = "# robots.txt — ".\App\Support\StoreBranding::name()."\n";
        $out .= "# AI-readable overview & links: {$llms}\n\n";

        $out .= "# ── Content usage signals (contentsignals.org) ──\n";
        $out .= "#   search=yes    search engines may index and link to pages\n";
        $out .= "#   ai-input=yes  AI answer engines may read and CITE pages\n";
        $out .= "#   ai-train=no   content may NOT be used to train AI models\n\n";

        // Everyone: index everything except private/operational areas.
        $out .= "User-agent: *\n";
        $out .= "Content-Signal: search=yes, ai-input=yes, ai-train=no\n";
        $out .= "{$block}\n# Product images stay crawlable for Google Images\n{$allowImages}\n\n";

        $out .= "# ── AI search & citation crawlers: welcome ──\n";
        foreach ($aiSearchBots as $bot) {
            $out .= "User-agent: {$bot}\nContent-Signal: search=yes, ai-input=yes, ai-train=no\nAllow: /\n{$block}\n\n";
        }

        $out .= "# ── AI model-training crawlers: blocked ──\n";
        foreach ($aiTrainingBots as $bot) {
            $out .= "User-agent: {$bot}\nDisallow: /\n\n";
        }

        $out .= "Sitemap: {$sitemap}\n";

        return $out;
    }
}
