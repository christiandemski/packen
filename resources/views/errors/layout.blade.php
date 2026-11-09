@php
    // Angemeldet mit normaler Navigation, damit man von hier einfach weiterkommt.
    // Bei Serverfehlern lieber schlicht: das App-Layout fragt selbst die Datenbank.
    $inApp = $code < 500 && auth()->check();
@endphp
@if ($inApp)
    <x-layouts.app :title="$title">
        <div class="mx-auto max-w-md py-8">
            @include('errors._card')
        </div>
    </x-layouts.app>
@else
    <x-layouts.guest :title="$title">
        @include('errors._card')
    </x-layouts.guest>
@endif
