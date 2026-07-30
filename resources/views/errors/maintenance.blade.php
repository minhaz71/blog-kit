@php
    // Standalone by design: the maintenance page must render even if the app
    // layout's partials are mid-deploy. setting() is wrapped so a DB/cache
    // outage can't error the page itself.
    try { $site = (string) setting('general.site_name', config('app.name')); } catch (\Throwable) { $site = config('app.name'); }
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $site }} — coming soon</title>
    <style>
        :root { --brand: #0f766e; --brand-dark: #115e59; --ink: #111827; --muted: #6b7280; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #f8fafc; color: var(--ink);
            font: 16px/1.6 -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .card { max-width: 34rem; width: 100%; margin: 1.5rem; padding: 2.75rem 2rem; text-align: center; }
        .badge { display: inline-block; font-size: .75rem; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; color: var(--brand); background: rgba(15,118,110,.1);
            border-radius: 999px; padding: .35rem .9rem; margin-bottom: 1.25rem; }
        .logo { font-size: 1.75rem; font-weight: 800; letter-spacing: -.02em; margin: 0 0 1.5rem; color: var(--ink); }
        h1 { font-size: 1.9rem; font-weight: 800; letter-spacing: -.02em; margin: 0 0 .6rem; }
        p { color: var(--muted); margin: 0 auto 1rem; max-width: 26rem; }
        .staff { margin-top: 2rem; font-size: .8rem; }
        .staff a { color: var(--brand); text-decoration: none; }
        .staff a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">{{ $site }}</div>
        <div class="badge">Under construction</div>
        <h1>We'll be back soon</h1>
        <p>Our store is getting a little polish right now. Thanks for your patience — please check back shortly.</p>
        <div class="staff">
            {{-- Send staff to the ADMIN panel login (not the customer login):
                 signing in there authenticates them for the whole site, so the
                 maintenance page is then bypassed everywhere. --}}
            <a href="{{ \Illuminate\Support\Facades\Route::has('filament.admin.auth.login') ? route('filament.admin.auth.login') : route('login') }}">Staff sign in</a>
        </div>
    </div>
</body>
</html>
