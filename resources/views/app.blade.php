<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="/images/favicon.svg">
    <title inertia>{{ config('app.name', 'paShaman') }}</title>
    @vite(['resources/js/app.js'])
    @inertiaHead
</head>
<body class="h-full bg-secondary-light dark:bg-primary-dark">
    @inertia
</body>
</html>