<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#F3F5F4">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" sizes="32x32" href="/app-icons/favicon-32.png">
    <link rel="apple-touch-icon" href="/app-icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Packen">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh" data-auth="{{ auth()->check() ? 1 : 0 }}">
    <main class="mx-auto flex min-h-dvh max-w-[420px] flex-col justify-center px-4 py-10">
        <a href="{{ route('login') }}" class="mb-8 self-start rounded-chip"><x-logo /></a>
        <x-flash />
        {{ $slot }}
    </main>
</body>
</html>
