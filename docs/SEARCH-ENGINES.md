# Search Engine Setup — Google, Bing, Yandex (organic only)

Everything code-side is already live: sitemaps, IndexNow, llms.txt, schema, the
product feed. This guide covers the **account-side steps only you can do**,
once, when the site is on its production domain.

## 1. Google Search Console (GSC)

1. https://search.google.com/search-console → Add property → **Domain** → your domain.
2. Verify via the DNS TXT record it shows you (add at your registrar).
3. Sitemaps → submit `https://YOURDOMAIN/sitemap.xml` (index of all sections).
4. Optional but recommended: create a **service account** (Cloud Console →
   IAM → Service accounts → JSON key), add its email as a GSC user, paste the
   JSON into **Admin → SEO settings → Integrations** — then `seo:gsc-sync`
   pulls rank/index data nightly into your admin.

## 2. Bing Webmaster Tools (also covers Yahoo + DuckDuckGo)

1. https://www.bing.com/webmasters → **Import from Google Search Console**
   (one click, reuses your GSC verification) — or verify via DNS.
2. Sitemaps → submit `https://YOURDOMAIN/sitemap.xml`.
3. IndexNow: nothing to do — Bing reads the key file the site already serves
   and accepts the automatic pings on every publish/update.

## 3. Yandex Webmaster

1. https://webmaster.yandex.com → Add site → verify via DNS TXT or the meta
   tag (if meta: paste into Admin → SEO settings → robots/head as directed).
2. Sitemaps → submit `https://YOURDOMAIN/sitemap.xml`.
3. IndexNow works here automatically too (Yandex co-created the protocol).
4. Region: set United Arab Emirates. Yandex UAE traffic is mostly
   Russian-speaking expats — a future `/ru/` locale would compound this.

## 4. IndexNow (already automatic)

- Key file: served at `/{key}.txt` — see the key in Admin → SEO settings.
- Auto-pings on every product/post/category/page publish, update and delete.
- One-time bulk push after launch or a domain move:

```bash
php artisan seo:indexnow-submit            # submit every published URL
php artisan seo:indexnow-submit --dry-run  # preview the URL list first
```

## 5. Product feed — Google Merchant Center + Bing Merchant

Feed URL (Google Shopping format, Bing accepts it unchanged):

```
https://YOURDOMAIN/feeds/products.xml
```

**Google:** https://merchants.google.com → Add products → Add products from a
file → paste the feed URL → schedule daily fetch. Free listings are enabled
under Growth → Manage programs.

**Bing:** https://www.bing.com/webmasters → Merchant Center → create store →
Feeds → add the same URL.

> ⚠️ **Tobacco policy warning:** heated-tobacco sticks are a restricted
> category in Google Shopping in most countries and may be disapproved.
> Submitting costs nothing and per-item disapprovals don't harm the site's
> organic rankings. Accessories/devices have better approval odds. Exclude
> any product from the feed via Admin → SEO settings → Product feed →
> "Exclude product IDs".

## 6. Business profiles (entity trust)

- **Google Business Profile** (https://business.google.com): create a profile
  as a *service-area business* (delivery-only, hide address), service area =
  the 7 emirates, link the website. Note: tobacco retail may be restricted in
  GBP categories — pick "E-commerce service"/"Delivery service" if "Tobacco
  shop" is rejected.
- **Bing Places** (https://www.bingplaces.com): Import from GBP (one click).

## 7. Fill your real business data (5 minutes, big schema payoff)

In **Admin → General settings**: real contact email + phone (currently demo
values). In **Admin → SEO settings → Social profiles**: your real profile
URLs — they feed the Organization `sameAs` schema. In **Admin → Staff → your
user**: check the author name/bio shown on blog posts.
