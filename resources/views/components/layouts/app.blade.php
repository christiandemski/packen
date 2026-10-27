<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#F3F5F4">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh">
    <header class="sticky top-0 z-30 border-b border-line bg-surface/95 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-5xl items-center justify-between gap-3 px-4">
            <a href="{{ route('home') }}" class="rounded-chip"><x-logo /></a>

            <nav class="flex items-center gap-1" aria-label="Hauptnavigation">
                @can('admin')
                    <a href="{{ route('admin.index') }}" class="icon-btn {{ request()->routeIs('admin.*') ? 'bg-line-soft' : '' }}" aria-label="Admin">
                        <x-icon name="shield" />
                    </a>
                @endcan
                <a href="{{ route('profile.edit') }}" class="flex min-h-11 items-center rounded-full p-1 hover:bg-line-soft" aria-label="Profil">
                    <x-avatar :user="auth()->user()" />
                </a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 pt-6 pb-24">
        <x-flash />
        {{ $slot }}
    </main>
</body>
</html>
