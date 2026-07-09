<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="/images/favicon.svg">
    <!-- Fonts: Geist (Sans + Mono) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/geist@1.3.1/dist/font/css/geist-sans.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/geist@1.3.1/dist/font/css/geist-mono.min.css">
    <!-- Fonts: Playfair Display (Google Fonts) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap">
    <title inertia>{{ config('app.name', 'paShaman') }}</title>
    @vite(['resources/js/app.js'])
    @inertiaHead
</head>
<body class="h-full bg-warm-bg text-text-primary antialiased">
    @inertia
</body>
</html>