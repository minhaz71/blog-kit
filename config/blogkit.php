<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hemdox Blog Kit version
    |--------------------------------------------------------------------------
    |
    | Recorded in every backup manifest and compared during restore so a
    | snapshot from a newer build is never imported into older code. Kept in
    | sync with version.json (the single source of truth read by
    | App\Support\Version); this is the hard-coded fallback when version.json
    | is unreadable.
    |
    */

    'version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | Optional modules
    |--------------------------------------------------------------------------
    |
    | Hemdox Blog Kit is a blog-first CMS built on the same engine as the
    | ecommerce product. The full store (catalog, cart, checkout, payments,
    | shipping, tax, the AI product writer, product templates…) is retained
    | in the codebase but ships DISABLED. An admin can turn it back on from
    | Admin → System → Modules; the value below is only the default used when
    | that setting has never been saved. Read it via module_enabled('ecommerce').
    |
    */

    'modules' => [
        'ecommerce' => env('BLOGKIT_ECOMMERCE_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTML minification
    |--------------------------------------------------------------------------
    |
    | Collapses whitespace and strips comments from storefront HTML output
    | (admin + Livewire are always skipped). Improves page weight and the
    | text-to-HTML ratio. Disable if a third-party tool needs the raw markup.
    |
    */

    'minify_html' => env('BLOGKIT_MINIFY_HTML', env('SHOPKIT_MINIFY_HTML', true)),

    /*
    |--------------------------------------------------------------------------
    | Off-machine backup (rclone → Google Drive / any remote)
    |--------------------------------------------------------------------------
    |
    | When 'remote' is set (e.g. "gdrive:BlogKit-Backups"), the daily
    | backup:cloud-sync command uploads local archives there and deletes
    | cloud copies older than 'retain_days'. Empty remote = local-only.
    | Set these in .env; see docs/BACKUPS.md for the one-time rclone setup.
    |
    */

    'backup' => [
        'remote' => env('BACKUP_CLOUD_REMOTE', ''),
        'retain_days' => (int) env('BACKUP_CLOUD_RETAIN_DAYS', 30),
    ],

];
