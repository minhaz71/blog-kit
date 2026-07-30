@php
    // Standalone by design (same rule as friendly.blade.php): the 404 page
    // must render even when the app layout's own partials are what broke.
    // setting() is wrapped so a DB/cache outage can't error the error page.
    try { $site = (string) setting('general.site_name', config('app.name')); } catch (\Throwable) { $site = config('app.name'); }
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Page not found — {{ $site }}</title>
    <style>
        :root { --brand: #0f766e; --brand-dark: #115e59; --ink: #111827; --muted: #6b7280; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #f8fafc; color: var(--ink);
            font: 16px/1.6 -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .card { max-width: 32rem; width: 100%; margin: 1.5rem; padding: 2.5rem 2rem; text-align: center; }
        .code { font-size: 4.5rem; font-weight: 800; letter-spacing: -.04em; line-height: 1;
            color: var(--brand); margin-bottom: 1rem; }
        h1 { font-size: 1.5rem; font-weight: 800; letter-spacing: -.02em; margin: 0 0 .6rem; }
        p { color: var(--muted); margin: 0 0 1.75rem; }
        .search { display: flex; gap: .5rem; max-width: 24rem; margin: 0 auto 1.25rem; }
        .search input { flex: 1; min-width: 0; border: 1px solid #d1d5db; border-radius: 999px;
            padding: .65rem 1.1rem; font: inherit; font-size: .9rem; }
        .search input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(15, 118, 110, .15); }
        .btn { border-radius: 999px; padding: .65rem 1.4rem; font-size: .875rem; font-weight: 600;
            text-decoration: none; cursor: pointer; border: 1px solid transparent; font-family: inherit; }
        .btn-primary { background: var(--brand); color: #fff; }
        .btn-primary:hover { background: var(--brand-dark); }
        .btn-ghost { background: #fff; color: #374151; border-color: #d1d5db; }
        .btn-ghost:hover { border-color: var(--brand); color: var(--brand); }
        .actions { display: flex; flex-wrap: wrap; gap: .6rem; justify-content: center; }
    </style>
</head>
<body>
    <div class="card">
        <div class="code">404</div>
        <h1>We can't find that page</h1>
        <p>The link may be outdated, or the address was mistyped. Search our products or head back to the shop.</p>

        <form class="search" action="{{ url('/search') }}" method="get">
            <input type="search" name="q" placeholder="Search products…" aria-label="Search products" autofocus>
            <button type="submit" class="btn btn-primary">Search</button>
        </form>

        <div class="actions">
            <a href="{{ url('/') }}" class="btn btn-primary">Go to homepage</a>
            <a href="{{ url('/shop') }}" class="btn btn-ghost">Browse all products</a>
        </div>
    </div>
</body>
</html>
