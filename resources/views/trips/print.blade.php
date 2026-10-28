@php use App\Services\PacklistExport; @endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/app-icons/favicon-32.png">
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 14mm 12mm; }
        @media print { body { background: #fff; } }
    </style>
</head>
<body class="bg-bg text-ink print:bg-white">
    <div class="sticky top-0 z-10 border-b border-line bg-surface print:hidden">
        <div class="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-2 px-4 py-3">
            <a href="{{ route('trips.show', $trip) }}" class="btn btn-ghost min-h-10 px-3 text-sm"><x-icon name="arrow-left" size="16" /> Zur Reise</a>
            <button type="button" onclick="window.print()" class="btn btn-primary min-h-10"><x-icon name="printer" size="18" /> Drucken oder als PDF sichern</button>
        </div>
    </div>

    <main class="mx-auto max-w-3xl px-4 py-6 print:max-w-none print:p-0">
        <h1 class="text-2xl font-extrabold">{{ $title }}</h1>
        <p class="mt-1 text-sm font-semibold text-muted">{{ $subtitle }}</p>

        @if ($sections->isEmpty())
            <p class="mt-6 text-sm text-muted">Nichts mehr offen – alles gepackt.</p>
        @endif

        <div class="mt-5 gap-8 sm:columns-2 print:columns-2">
            @foreach ($sections as $section)
                <section class="mb-5 break-inside-avoid">
                    <h2 class="mb-1.5 border-b border-line pb-1 text-[11px] font-bold tracking-[0.08em] text-muted uppercase">{{ $section['title'] }}</h2>
                    <ul>
                        @foreach ($section['entries'] as $entry)
                            @php($details = PacklistExport::details($entry, $byContainer))
                            <li class="flex items-start gap-2 py-[3px] text-[14px] leading-snug break-inside-avoid">
                                <span class="mt-[3px] inline-flex size-[13px] shrink-0 items-center justify-center rounded-[3px] border-[1.5px] border-ink">
                                    @if ($entry->packed)<x-icon name="check" size="10" stroke-width="3" />@endif
                                </span>
                                <span class="{{ $entry->packed ? 'text-muted line-through' : '' }}">
                                    <span class="font-semibold">{{ PacklistExport::label($entry) }}</span>@if ($details !== '')<span class="text-muted"> – {{ $details }}</span>@endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>

        <p class="mt-6 text-[11px] text-muted print:fixed print:bottom-0">Stand {{ now()->translatedFormat('j. F Y, H:i') }} Uhr · Packen</p>
    </main>
</body>
</html>
