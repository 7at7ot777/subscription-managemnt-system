{{--
    Deliberately plain Blade, not a Filament layout. These pages render from
    exceptions thrown in early middleware, before a panel is booted and sometimes
    before a session exists, so calling Filament::getCurrentOrDefaultPanel() or a
    render hook here would be fragile. No tenant or connection detail is ever shown.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') &middot; {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center;
            padding: 2rem; background: #f8fafc; color: #0f172a;
            font: 16px/1.6 ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
        }
        .card {
            max-width: 34rem; width: 100%; background: #fff; border: 1px solid #e2e8f0;
            border-radius: .75rem; padding: 2.5rem; text-align: center;
            box-shadow: 0 1px 3px rgb(0 0 0 / .08);
        }
        .code { font-size: .75rem; letter-spacing: .12em; text-transform: uppercase; color: #64748b; }
        h1 { margin: .75rem 0 .5rem; font-size: 1.5rem; }
        p { margin: 0 0 1.5rem; color: #475569; }
        a.button {
            display: inline-block; padding: .625rem 1.25rem; border-radius: .5rem;
            background: #4f46e5; color: #fff; text-decoration: none; font-weight: 600;
        }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e2e8f0; }
            .card { background: #1e293b; border-color: #334155; }
            p { color: #94a3b8; }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        @hasSection('action') @yield('action') @endif
    </div>
</body>
</html>
