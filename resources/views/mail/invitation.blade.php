<x-mail::message>
# Hallo {{ $invitation->name }},

{{ $inviter }} lädt dich zu {{ config('app.name') }} ein – der Packliste mit Outfit-Planer für gemeinsame Reisen.

<x-mail::button :url="$url">
Einladung annehmen
</x-mail::button>

Ein Passwort brauchst du nicht: Du meldest dich später einfach mit einem Link per E-Mail an.
Die Einladung gilt {{ $days }} Tage.
</x-mail::message>
