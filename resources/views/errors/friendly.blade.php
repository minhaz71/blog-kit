@php
    // Standalone by design: an error page must render even when the app
    // layout's own partials (header queries, cart, settings) are what broke.
    // setting() is wrapped so a DB/cache outage can't error the error page.
    try { $site = (string) setting('general.site_name', config('app.name')); } catch (\Throwable) { $site = config('app.name'); }
    $status = $status ?? 500;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Something went wrong — {{ $site }}</title>
    <style>
        :root { --brand: #0f766e; --brand-dark: #115e59; --ink: #111827; --muted: #6b7280; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #f8fafc; color: var(--ink);
            font: 16px/1.6 -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .card { max-width: 30rem; width: 100%; margin: 1.5rem; padding: 2.5rem 2rem; text-align: center; }
        .badge { width: 4rem; height: 4rem; margin: 0 auto 1.5rem; border-radius: 999px;
            background: #fef2f2; color: #ef4444; display: flex; align-items: center; justify-content: center; }
        h1 { font-size: 1.6rem; font-weight: 800; letter-spacing: -.02em; margin: 0 0 .6rem; }
        p { color: var(--muted); margin: 0 0 1.5rem; }
        .actions { display: flex; flex-wrap: wrap; gap: .6rem; justify-content: center; }
        .btn { border-radius: 999px; padding: .6rem 1.4rem; font-size: .875rem; font-weight: 600;
            text-decoration: none; cursor: pointer; border: 1px solid transparent; }
        .btn-primary { background: var(--brand); color: #fff; }
        .btn-primary:hover { background: var(--brand-dark); }
        .btn-ghost { background: #fff; color: #374151; border-color: #d1d5db; }
        .report { margin-top: 2rem; }
        .report summary { cursor: pointer; font-size: .875rem; font-weight: 600; color: var(--brand); list-style: none; }
        .report[open] summary { margin-bottom: 1rem; }
        form { text-align: left; display: grid; gap: .75rem; }
        label { font-size: .8rem; font-weight: 600; }
        input, textarea { width: 100%; border: 1px solid #d1d5db; border-radius: .5rem; padding: .55rem .7rem; font: inherit; font-size: .875rem; }
        input:focus, textarea:focus { outline: none; border-color: var(--brand); }
        .ok { background: #ecfdf5; color: #065f46; border-radius: .5rem; padding: .8rem 1rem; font-size: .875rem; font-weight: 500; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">
            <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M10.34 3.94l-8.4 14.55A1.5 1.5 0 003.24 21h17.52a1.5 1.5 0 001.3-2.51L13.66 3.94a1.5 1.5 0 00-2.6 0z"/>
            </svg>
        </div>
        <h1>Something went wrong</h1>
        <p>Sorry, an unexpected error stopped this page from loading. Our team has been notified automatically. Please try again shortly, or let us know what happened.</p>
        <div class="actions">
            <a href="{{ url('/') }}" class="btn btn-primary">Back to home</a>
            <a href="javascript:history.back()" class="btn btn-ghost">Go back</a>
        </div>

        @if(session('success'))
            <div class="report"><p class="ok">Thanks — your report has been sent. We will get it fixed.</p></div>
        @else
            <details class="report">
                <summary>Tell us what happened</summary>
                <form method="POST" action="{{ url('/contact') }}">
                    @csrf
                    <input type="hidden" name="subject" value="Website error report">
                    <input type="hidden" name="message" value="A visitor hit a server error (status {{ $status }}) on {{ url()->current() }}. Check the admin error log for the technical detail.">
                    <div><label>Your name</label><input type="text" name="name" required maxlength="100"></div>
                    <div><label>Email</label><input type="email" name="email" required maxlength="255"></div>
                    <div><label>What were you doing? (optional)</label><textarea name="note" rows="2" maxlength="1000"></textarea></div>
                    <button type="submit" class="btn btn-primary">Send report</button>
                </form>
            </details>
        @endif
    </div>
</body>
</html>
