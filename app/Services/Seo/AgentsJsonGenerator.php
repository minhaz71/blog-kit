<?php

namespace App\Services\Seo;

/**
 * A minimal, read-only /.well-known/agents.json — an honest discovery manifest
 * pointing AI agents at the store's machine-readable surfaces (llms.txt, full
 * export, sitemap, product feed, on-site search) and advertising the
 * markdown-for-agents capability.
 *
 * Deliberately NOT the full wild-card-ai "flows" transactional contract (that
 * needs a documented commerce API and has ~zero adoption today). This is
 * informational only; the `x-informational` flag makes that explicit.
 */
class AgentsJsonGenerator
{
    public function manifest(): array
    {
        $name = (string) setting('general.site_name', config('app.name'));
        $summary = (string) str(strip_tags((string) setting('seo.default_description', setting('general.site_tagline', ''))))->squish();

        $endpoints = [
            [
                'name' => 'search',
                'description' => 'On-site product search (JSON autocomplete).',
                'method' => 'GET',
                'url' => url('/search/suggest').'?q={query}',
                'response_format' => 'application/json',
            ],
        ];

        if (MerchantFeedGenerator::enabled()) {
            $endpoints[] = [
                'name' => 'product_feed',
                'description' => 'Full product catalogue (Google/Bing Merchant XML) — names, prices, availability, links.',
                'method' => 'GET',
                'url' => url('/feeds/products.xml'),
                'response_format' => 'application/xml',
            ];
        }

        return array_filter([
            'version' => '0.1',
            'x-informational' => true,
            'name' => $name,
            'description' => $summary ?: null,
            'url' => url('/'),
            'contact' => setting('general.contact_email') ?: setting('emails.admin_recipient') ?: null,
            'capabilities' => [
                'markdown' => [
                    'description' => 'Every content page is available as clean markdown for lower-token, higher-accuracy reading.',
                    'content_negotiation' => 'Send header: Accept: text/markdown',
                    'url_suffix' => '.md',
                ],
            ],
            'discovery' => array_filter([
                'llms_txt' => url('/llms.txt'),
                'llms_full_txt' => url('/llms-full.txt'),
                'sitemap' => route('sitemap.index'),
                'robots' => url('/robots.txt'),
            ]),
            'endpoints' => $endpoints,
            'notes' => 'Read-only discovery manifest. Prices in '.store_currency().' and stock may change — always fetch the linked page (append .md for markdown) for current values.',
        ], fn ($v) => $v !== null);
    }
}
