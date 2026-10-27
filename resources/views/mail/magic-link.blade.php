<x-mail::message>
# Hallo {{ $user->name }},

mit diesem Link meldest du dich bei {{ config('app.name') }} an:

<x-mail::button :url="$url">
Jetzt anmelden
</x-mail::button>

Der Link gilt {{ $minutes }} Minuten und lässt sich nur einmal verwenden.
Falls du keinen Link angefordert hast, kannst du diese Mail ignorieren.
</x-mail::message>
