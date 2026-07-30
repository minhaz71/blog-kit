<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Scheduled maintenance ────────────────────────────────────────────
// SEO sitemap regeneration — nightly.
Schedule::command('seo:sitemap-generate')->daily()->at('02:00')->onOneServer();

// Internal link index — weekly full rebuild (content edits already
// re-index incrementally via observers; weekly catches drift).
Schedule::command('seo:scan-links')->weekly()->sundays()->at('02:30')->onOneServer();

// Link agent: rebuild the phrase dictionary + regenerate suggestions
// (suggest-only — the admin applies each link by hand).
Schedule::command('seo:suggest-links')->weekly()->sundays()->at('02:45')->onOneServer();

// PageSpeed snapshots for key pages — weekly, quota-capped (~30 PSI calls).
Schedule::command('seo:pagespeed --strategy=both')->weekly()->mondays()->at('04:45')->onOneServer();

// Search Console + GA4 import + index-status checks — daily; skips itself
// silently until the service account is configured in SEO settings.
Schedule::command('seo:gsc-sync')->dailyAt('05:15')->onOneServer();

// Security — nightly scan, daily threat-blocklist refresh, weekly CVE audit.
Schedule::command('security:scan')->daily()->at('03:00')->onOneServer();
Schedule::command('security:update-blocklist')->daily()->at('03:30')->onOneServer();
Schedule::command('security:audit-dependencies')->weekly()->mondays()->at('03:45')->onOneServer();

// Housekeeping.
Schedule::command('ecommerce:clear-expired-carts --days=30')->daily()->at('04:00');
// Trash box: permanently delete products/orders/posts/pages trashed >90 days ago.
Schedule::command('trash:purge --days=90')->dailyAt('04:15')->onOneServer();
Schedule::command('auth:clear-resets')->hourly();
Schedule::command('model:prune', ['--model' => [\App\Models\AiActivityLog::class, \App\Models\AiFixPrompt::class]])
    ->daily()->at('04:30');

// AI publisher safety net — re-queue items abandoned by killed workers,
// finalize batches whose completion pass never fired.
Schedule::command('ai:sweep-stuck')->everyTenMinutes();

// Blog schedule: publish due "scheduled" posts on time — manual schedules,
// AI-batch staggered publishing and CSV publish dates all flow through this.
Schedule::command('blog:publish-scheduled')->everyMinute()->onOneServer();

// Customer engagement.
Schedule::command('email:abandoned-cart')->everyFifteenMinutes();
Schedule::command('email:review-request --days=7')->dailyAt('09:00');

// Backups. Daily database snapshot + weekly full (DB + uploaded files).
// A local retention window keeps disk usage bounded.
Schedule::command('backup:run --type=database')->dailyAt('01:00')->onOneServer();
Schedule::command('backup:run --type=full')->weekly()->sundays()->at('01:15')->onOneServer();
Schedule::command('backup:prune --keep-days=30')->dailyAt('02:15')->onOneServer();
// Off-machine: push the day's archives to the cloud remote (Google Drive via
// rclone) and prune old cloud copies. No-ops until BACKUP_CLOUD_REMOTE is set.
Schedule::command('backup:cloud-sync')->dailyAt('02:30')->onOneServer();
