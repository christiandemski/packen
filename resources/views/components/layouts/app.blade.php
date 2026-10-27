@php
    $nav = [
        ['home', 'luggage', 'Reisen', ['home', 'trips.*']],
        ['items.index', 'package', 'Katalog', ['items.*']],
        ['templates.index', 'layers', 'Vorlagen', ['templates.*']],
    ];
@endphp
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

            <nav class="hidden items-center gap-1 sm:flex" aria-label="Hauptnavigation">
                @foreach ($nav as [$route, $icon, $label, $patterns])
                    <a href="{{ route($route) }}" @class(['btn btn-ghost min-h-10 px-3 text-sm', 'bg-line-soft' => request()->routeIs(...$patterns)])
                       @if (request()->routeIs(...$patterns)) aria-current="page" @endif>
                        <x-icon :name="$icon" size="18" /> {{ $label }}
                    </a>
                @endforeach
            </nav>

            <div class="flex items-center gap-1">
                @can('admin')
                    <a href="{{ route('admin.index') }}" class="icon-btn {{ request()->routeIs('admin.*') ? 'bg-line-soft' : '' }}" aria-label="Admin">
                        <x-icon name="shield" />
                    </a>
                @endcan
                <a href="{{ route('profile.edit') }}" class="flex min-h-11 items-center rounded-full p-1 hover:bg-line-soft" aria-label="Profil">
                    <x-avatar :user="auth()->user()" />
                </a>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 pt-6 pb-28 sm:pb-16">
        <x-flash />
        {{ $slot }}
    </main>

    {{-- Untere Navigation auf dem Handy --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface/95 pb-[env(safe-area-inset-bottom)] backdrop-blur sm:hidden" aria-label="Hauptnavigation">
        <div class="grid grid-cols-3">
            @foreach ($nav as [$route, $icon, $label, $patterns])
                <a href="{{ route($route) }}" @class([
                    'flex min-h-14 flex-col items-center justify-center gap-0.5 text-[11px] font-bold',
                    'text-accent' => request()->routeIs(...$patterns),
                    'text-muted' => ! request()->routeIs(...$patterns),
                ]) @if (request()->routeIs(...$patterns)) aria-current="page" @endif>
                    <x-icon :name="$icon" size="22" /> {{ $label }}
                </a>
            @endforeach
        </div>
    </nav>
</body>
</html>
