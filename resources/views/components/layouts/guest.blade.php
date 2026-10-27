<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#F3F5F4">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh">
    <main class="mx-auto flex min-h-dvh max-w-[420px] flex-col justify-center px-4 py-10">
        <a href="{{ route('login') }}" class="mb-8 self-start rounded-chip"><x-logo /></a>
        <x-flash />
        {{ $slot }}
    </main>
</body>
</html>
