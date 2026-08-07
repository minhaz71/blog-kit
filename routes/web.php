<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

// ── Storefront ─────────────────────────────────────────────────────
Route::get('/', HomeController::class)->name('home');
Route::get('/shop', [ShopController::class, 'index'])->middleware('ecommerce')->name('shop');
// Product/category bases are configurable (Admin → SEO settings). With a base
// set (default "category"/"product") they get their own prefixed route; when a
// base is cleared, those URLs live at the root and are served by the catch-all
// PermalinkController below. See App\Support\Permalinks.
// Markdown-for-agents `.md` variants are registered BEFORE their HTML routes
// so "/product/foo.md" matches here (slug "foo") instead of the HTML route
// binding a slug of "foo.md". See App\Http\Controllers\MarkdownController.
Route::get('/index.md', [\App\Http\Controllers\MarkdownController::class, 'home'])->name('home.md');
if (($categoryBase = \App\Support\Permalinks::base('category')) !== '') {
    Route::get($categoryBase.'/{slug}.md', [\App\Http\Controllers\MarkdownController::class, 'category']);
    Route::get($categoryBase.'/{category:slug}', [ShopController::class, 'category'])->middleware('ecommerce')->name('category.show');
}
if (($productBase = \App\Support\Permalinks::base('product')) !== '') {
    Route::get($productBase.'/{slug}.md', [\App\Http\Controllers\MarkdownController::class, 'product']);
    Route::get($productBase.'/{product:slug}', [ProductController::class, 'show'])->middleware('ecommerce')->name('product.show');
}
Route::get('/search', [ShopController::class, 'search'])->middleware(['throttle:search', 'ecommerce'])->name('search');
Route::get('/search/suggest', [\App\Http\Controllers\SearchController::class, 'suggest'])->middleware(['throttle:search-suggest', 'ecommerce'])->name('search.suggest');

// ── Cart / Checkout / Webhooks (ecommerce module) ──────────────────
// Registered always so route('cart.*'), route('checkout.*') etc. resolve
// everywhere; the 'ecommerce' guard 404s them while the store is off.
Route::middleware('ecommerce')->group(function (): void {

// ── Cart ───────────────────────────────────────────────────────────
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::get('/cart/drawer', [CartController::class, 'drawer'])->name('cart.drawer');
Route::get('/cart/count', [CartController::class, 'count'])->name('cart.count');
Route::post('/cart/add', [CartController::class, 'add'])->middleware('throttle:add-to-cart')->name('cart.add');
Route::post('/cart/identify', [CartController::class, 'identify'])->middleware('throttle:add-to-cart')->name('cart.identify');
// Abandoned-cart reminder recovery link — restores the exact cart (guest incl.)
// via a per-cart HMAC code, then redirects to the cart page.
Route::get('/cart/restore/{cart}/{code}', [CartController::class, 'restore'])
    ->where('code', '[a-f0-9]{64}')->middleware('throttle:30,1')->name('cart.restore');
Route::patch('/cart/items/{item}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/items/{item}', [CartController::class, 'remove'])->name('cart.remove');
Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->middleware('throttle:coupon')->name('cart.coupon');
Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');

// ── Checkout ───────────────────────────────────────────────────────
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout/shipping-options', [CheckoutController::class, 'shippingOptions'])->name('checkout.shipping-options');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
Route::get('/checkout/thank-you/{orderNumber}', [CheckoutController::class, 'thankYou'])->name('checkout.thank-you');
Route::get('/checkout/failed/{orderNumber}', [CheckoutController::class, 'failed'])->name('checkout.payment-failed');
Route::get('/checkout/paypal/return/{orderNumber}', [CheckoutController::class, 'paypalReturn'])->name('checkout.paypal-return');

// ── Payment webhooks (CSRF-exempt, signature-verified) ─────────────
Route::post('/webhooks/{gateway}', WebhookController::class)->middleware('throttle:webhooks')->name('webhooks.gateway');

}); // end ecommerce group (cart / checkout / webhooks)

// ── Auth ───────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware(['throttle:login', 'recaptcha']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware(['throttle:register', 'recaptcha']);
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware(['throttle:password-reset', 'recaptcha'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware(['throttle:password-reset', 'recaptcha'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Admin backup download — a plain authenticated GET that streams the archive
// (the Filament action links here instead of returning a Livewire download,
// which base64-inlined large files and hung). Gated inside the controller by
// the same permission as the Backups screen.
Route::get('/admin/backups/{backup}/download', \App\Http\Controllers\Admin\BackupDownloadController::class)
    ->middleware('auth')->name('admin.backups.download');

// Preview a rendered email template (what the recipient actually receives).
Route::get('/admin/email-templates/{template}/preview', \App\Http\Controllers\Admin\EmailTemplatePreviewController::class)
    ->middleware('auth')->name('admin.email-templates.preview');

// Public invoice download — login-free, guarded by the per-order HMAC code so
// it works straight from the order email (guest orders included) and records a
// trackable download event. See App\Http\Controllers\InvoiceDownloadController.
Route::get('/invoice/{orderNumber}/{code}', \App\Http\Controllers\InvoiceDownloadController::class)
    ->where('code', '[a-f0-9]{64}')
    ->middleware('throttle:30,1')
    ->name('invoice.download');

// ── Two-factor authentication ─────────────────────────────────────
Route::middleware('auth')->prefix('two-factor')->name('two-factor.')->group(function () {
    Route::get('/', [TwoFactorController::class, 'show'])->name('show');
    Route::post('/confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
    Route::delete('/', [TwoFactorController::class, 'disable'])->name('disable');
    Route::get('/challenge', [TwoFactorController::class, 'challenge'])->name('challenge');
    Route::post('/challenge', [TwoFactorController::class, 'verify'])->name('verify');
});

Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['auth', 'signed'])->name('verification.verify');
Route::post('/verify-email/resend', [AuthController::class, 'resendVerification'])
    ->middleware(['auth', 'throttle:3,1'])->name('verification.send');

// ── Customer account ───────────────────────────────────────────────
Route::prefix('my-account')->middleware('auth')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'dashboard'])->name('dashboard');
    Route::get('/orders', [AccountController::class, 'orders'])->name('orders');
    Route::get('/orders/{orderNumber}', [AccountController::class, 'order'])->name('order');
    Route::get('/orders/{orderNumber}/invoice', [AccountController::class, 'invoice'])->name('invoice');
    Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
    Route::patch('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::get('/addresses', [AddressController::class, 'index'])->name('addresses');
    Route::post('/addresses', [AddressController::class, 'store'])->name('addresses.store');
    Route::patch('/addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist');
    Route::get('/data-export', [AccountController::class, 'exportData'])->name('data-export');
});

Route::post('/wishlist/{product}', [WishlistController::class, 'toggle'])->middleware(['auth', 'ecommerce'])->name('wishlist.toggle');
Route::post('/products/{product}/reviews', [ReviewController::class, 'store'])->middleware(['throttle:reviews', 'ecommerce'])->name('reviews.store');
Route::post('/newsletter', [NewsletterController::class, 'subscribe'])->middleware('throttle:newsletter')->name('newsletter.subscribe');

// Fresh CSRF token for this session. Guest pages are served from the full-page
// cache with another visitor's token baked in; the frontend fetches this once
// on load and swaps every token on the page so forms never 419 on cached HTML.
// Always available (independent of the store's /cart/count endpoint), so a
// blog install keeps working forms without loading any ecommerce route.
// Returns JSON, so it is never itself stored by the page cache.
Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token()]))->name('csrf.token');

// ── Blog ───────────────────────────────────────────────────────────
// The blog base is renameable (Admin → SEO settings, default "blog"); its
// index/category/author sub-routes mean it always keeps a non-empty prefix.
// Named routes carry the prefix, so every route('blog.*') URL updates for free.
Route::prefix(\App\Support\Permalinks::blogBase())->group(function (): void {
    Route::get('/', [BlogController::class, 'index'])->name('blog.index');
    Route::get('/category/{postCategory:slug}', [BlogController::class, 'category'])->name('blog.category');
    Route::get('/author/{author:public_slug}', [BlogController::class, 'author'])->name('blog.author');
    Route::get('/{slug}.md', [\App\Http\Controllers\MarkdownController::class, 'post']);
    Route::get('/{post:slug}', [BlogController::class, 'show'])->name('blog.show');
});

// ── Per-entity custom CSS served as a cacheable stylesheet ─────────
Route::get('/custom-css/{type}/{id}.css', [\App\Http\Controllers\EntityCssController::class, 'show'])
    ->where(['type' => 'product|category|post|page', 'id' => '[0-9]+'])
    ->name('entity.css');

// ── SEO endpoints ──────────────────────────────────────────────────
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemapIndex'])->name('sitemap.index');
Route::get('/sitemap-{section}.xml', [SeoController::class, 'sitemapSection'])
    ->where('section', '[a-z-]+(?:-\d+)?')->name('sitemap.section');
// IndexNow ownership proof: /{key}.txt must return the key (spec).
Route::get('/{key}.txt', [SeoController::class, 'indexNowKey'])
    ->where('key', '[a-z0-9]{32}')->name('indexnow.key');
// Google Merchant / Bing Merchant product feed (free organic listings).
Route::get('/feeds/products.xml', [SeoController::class, 'merchantFeed'])->middleware('ecommerce')->name('feed.products');

// AI answer-engine site map (llmstxt.org) + RFC 9116 security contact.
Route::get('/llms.txt', [SeoController::class, 'llmsTxt'])->name('llms.txt');
Route::get('/.well-known/llms.txt', [SeoController::class, 'llmsTxt']);
Route::get('/llms-full.txt', [SeoController::class, 'llmsFullTxt'])->name('llms-full.txt');
Route::get('/.well-known/llms-full.txt', [SeoController::class, 'llmsFullTxt']);
// Minimal read-only agent discovery manifest.
Route::get('/.well-known/agents.json', [SeoController::class, 'agentsJson'])->name('agents.json');
Route::get('/agents.json', [SeoController::class, 'agentsJson']);
Route::get('/.well-known/security.txt', [SeoController::class, 'securityTxt'])->name('security.txt');
// RFC 9727 API catalogue — read-only machine surfaces for agent discovery.
Route::get('/.well-known/api-catalog', [SeoController::class, 'apiCatalog'])->name('api-catalog');

// ── Gmail OAuth (1-click mail setup) — staff only ───────────────────
Route::middleware('auth')->group(function () {
    Route::get('/hmmail/connect', [\App\Http\Controllers\GmailOAuthController::class, 'connect'])->name('hmmail.connect');
    Route::get('/hmmail/callback', [\App\Http\Controllers\GmailOAuthController::class, 'callback'])->name('hmmail.callback');
});

// Contact form (page itself is a CMS page with the {{contact_form}} shortcode).
Route::post('/contact', [\App\Http\Controllers\ContactController::class, 'store'])
    ->middleware(['throttle:10,10', 'recaptcha'])
    ->name('contact.submit');

// ── Root-level permalinks (keep last: catch-all slug) ──────────────
// Resolves CMS pages, plus root-level products/categories when their base is
// cleared. Kept as ONE route so a model-binding miss can't hard-404 a page.
// The `.md` variant (registered first) serves the markdown representation.
Route::get('/{slug}.md', [\App\Http\Controllers\MarkdownController::class, 'root']);
Route::get('/{slug}', [\App\Http\Controllers\PermalinkController::class, 'resolve'])->name('page.show');
