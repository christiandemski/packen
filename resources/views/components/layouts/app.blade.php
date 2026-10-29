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
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" sizes="32x32" href="/app-icons/favicon-32.png">
    <link rel="apple-touch-icon" href="/app-icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Packen">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh" data-auth="1">
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
                @php($user = auth()->user())
                @php($unread = $user->unreadNotifications()->count())

                {{-- Benachrichtigungen: Popup, Inhalt wird beim ersten Öffnen geladen (gilt dann als gelesen) --}}
                <div class="relative" x-data="popover(@js(route('notifications.index', ['popup' => 1])))" @keydown.escape.window="close()" @click.outside="close()">
                    <button type="button" class="icon-btn relative" :class="open && 'bg-line-soft'" @click="toggle()" :aria-expanded="open" aria-haspopup="true"
                            aria-label="Benachrichtigungen{{ $unread ? ', '.$unread.' neu' : '' }}">
                        <x-icon name="bell" />
                        @if ($unread)
                            <span x-show="!loaded" class="absolute top-1.5 right-1.5 inline-flex min-w-4 items-center justify-center rounded-full bg-warn px-1 text-[10px] leading-4 font-extrabold text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
                        @endif
                    </button>
                    <div x-show="open" x-cloak x-transition.origin.top.right
                         class="fixed inset-x-4 top-[4.25rem] z-40 overflow-hidden rounded-card border border-line bg-surface shadow-xl sm:absolute sm:inset-x-auto sm:top-full sm:right-0 sm:mt-2 sm:w-96">
                        <div class="flex items-center justify-between border-b border-line-soft px-4 py-3">
                            <p class="card-title">Benachrichtigungen</p>
                            <span class="flex items-center gap-3">
                                <a href="{{ route('notifications.index') }}" class="text-sm font-bold text-accent-ink hover:underline">Alle anzeigen</a>
                                <a href="{{ route('notifications.settings') }}" class="icon-btn -mr-2 size-9 min-h-9 min-w-9" aria-label="Benachrichtigungen einstellen"><x-icon name="settings" size="16" /></a>
                            </span>
                        </div>
                        <div class="max-h-[min(28rem,70dvh)] overflow-y-auto" x-html="html"></div>
                    </div>
                </div>

                {{-- Profil-Menü --}}
                <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                    <button type="button" class="flex min-h-11 items-center rounded-full p-1 hover:bg-line-soft" :class="open && 'bg-line-soft'" @click="open = !open" :aria-expanded="open" aria-haspopup="true" aria-label="Profil-Menü">
                        <x-avatar :user="$user" />
                    </button>
                    <div x-show="open" x-cloak x-transition.origin.top.right
                         class="fixed inset-x-4 top-[4.25rem] z-40 overflow-hidden rounded-card border border-line bg-surface shadow-xl sm:absolute sm:inset-x-auto sm:top-full sm:right-0 sm:mt-2 sm:w-72">
                        <div class="flex items-center gap-3 border-b border-line-soft px-4 py-3">
                            <x-avatar :user="$user" />
                            <div class="min-w-0">
                                <p class="truncate text-[15px] font-bold">{{ $user->name }}</p>
                                <p class="truncate text-xs font-semibold text-muted">{{ $user->email }}</p>
                            </div>
                        </div>
                        <nav class="py-1" aria-label="Profil">
                            <a href="{{ route('profile.edit') }}" class="flex min-h-11 items-center gap-3 px-4 text-[15px] font-semibold hover:bg-line-soft"><x-icon name="user" size="18" class="text-muted" /> Profil &amp; Einstellungen</a>
                            <a href="{{ route('notifications.settings') }}" class="flex min-h-11 items-center gap-3 px-4 text-[15px] font-semibold hover:bg-line-soft"><x-icon name="bell" size="18" class="text-muted" /> Benachrichtigungen</a>
                            @can('admin')
                                <a href="{{ route('admin.index') }}" class="flex min-h-11 items-center gap-3 px-4 text-[15px] font-semibold hover:bg-line-soft"><x-icon name="shield" size="18" class="text-muted" /> Admin</a>
                            @endcan
                        </nav>
                        <form method="POST" action="{{ route('logout') }}" class="border-t border-line-soft py-1">
                            @csrf
                            <button type="submit" class="flex min-h-11 w-full items-center gap-3 px-4 text-left text-[15px] font-semibold hover:bg-line-soft"><x-icon name="log-out" size="18" class="text-muted" /> Abmelden</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- Offline-Status und noch nicht übertragene Änderungen --}}
    <div x-data x-cloak x-show="!$store.sync.online || $store.sync.pending > 0" role="status"
         class="sticky top-16 z-20 border-b border-line text-sm font-bold"
         :class="$store.sync.online ? 'bg-accent-soft text-accent-ink' : 'bg-warn-soft text-warn-ink'">
        <div class="mx-auto flex max-w-5xl items-center gap-2 px-4 py-2">
            <x-icon name="refresh" size="16" />
            <span x-show="!$store.sync.online">Offline – Abhaken geht weiter, der Rest braucht Verbindung.</span>
            <span x-show="$store.sync.online">Wird übertragen …</span>
            <span x-show="$store.sync.pending > 0" class="ml-auto" x-text="$store.sync.pending === 1 ? '1 Änderung ausstehend' : $store.sync.pending + ' Änderungen ausstehend'"></span>
        </div>
    </div>

    <div x-data x-cloak x-show="$store.sync.notice" x-transition role="alert"
         class="fixed inset-x-4 bottom-24 z-50 mx-auto max-w-md rounded-chip bg-ink px-4 py-3 text-sm font-bold text-white shadow-lg sm:bottom-8"
         x-text="$store.sync.notice"></div>

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
