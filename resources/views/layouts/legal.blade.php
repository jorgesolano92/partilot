<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') — PARTILOT</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            color: #333;
            margin: 0;
            padding: 40px 16px 64px;
            line-height: 1.6;
        }
        .legal-header {
            max-width: 800px;
            margin: 0 auto 24px;
            text-align: center;
        }
        .legal-header img { height: 40px; }
        .legal-content {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            padding: 40px 48px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
        }
        h1 { font-size: 1.75rem; margin: 0 0 1.5rem; color: #222; }
        h2 { font-size: 1.25rem; margin: 2rem 0 1rem; color: #222; }
        h3 { font-size: 1.05rem; margin: 1.5rem 0 .75rem; color: #333; }
        p { margin: 0 0 1rem; }
        a { color: #0d6efd; }
        em { color: #666; font-size: .9rem; }
        @media (max-width: 600px) {
            .legal-content { padding: 24px 20px; }
        }
    </style>
</head>
<body>
    <header class="legal-header">
        <a href="{{ url('/') }}">
            <img src="{{ url('/logo.svg') }}" alt="PARTILOT">
        </a>
    </header>
    <main class="legal-content">
        @yield('content')
    </main>
    @include('partials.cookie-consent-banner')
</body>
</html>
