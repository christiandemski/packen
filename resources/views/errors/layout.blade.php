<x-layouts.guest :title="$title">
    <div class="card p-6">
        <p class="eyebrow">Fehler {{ $code }}</p>
        <h1 class="page-title mt-1">{{ $title }}</h1>
        <p class="mt-3 text-[15px] leading-relaxed text-muted">{{ $text }}</p>
        <a href="{{ url('/') }}" class="btn btn-primary mt-6 w-full">Zur Startseite</a>
    </div>
</x-layouts.guest>
