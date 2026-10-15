<!DOCTYPE html>
<html lang="hu"><head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#214d3f">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Buksza">
    <meta name="description" content="Buksza – a családi kassza egy helyen: napi költések, havi keretek és megtakarítások.">
    <script>try { if (localStorage.getItem('kassza-theme') === 'dark') document.documentElement.dataset.theme = 'dark'; } catch (_) {}</script>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head><body>@inertia</body></html>
