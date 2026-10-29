@php use App\Enums\PushKind; @endphp
<x-layouts.app title="Benachrichtigungen">
    <div class="mx-auto max-w-2xl space-y-4">
        <div class="mb-1 flex items-start gap-2">
            <a href="{{ route('notifications.index') }}" class="icon-btn -ml-2" aria-label="Zurück zu den Benachrichtigungen"><x-icon name="arrow-left" /></a>
            <div>
                <h1 class="page-title">Einstellungen</h1>
                <p class="mt-2 text-[15px] text-muted">Was dich wie erreicht. In der App (Glocke oben) siehst du Hinweise aus der Gruppe immer.</p>
            </div>
        </div>

        {{-- Push auf diesem Gerät --}}
        <section class="card p-5">
            <p class="card-title flex items-center gap-2"><x-icon name="bell" size="18" class="text-accent" /> Push auf diesem Gerät</p>
            @if ($pushKey)
                <div x-data="pushToggle(@js($pushKey), @js(route('push.store')), @js(route('push.test')))">
                    <p class="mt-1 text-sm text-muted">
                        Nachrichten aufs Handy oder in den Browser, auch wenn Packen geschlossen ist. Gilt für jedes Gerät einzeln
                        – eingeschaltet auf {{ $devices }} {{ $devices === 1 ? 'Gerät' : 'Geräten' }}.
                    </p>
                    <template x-if="!supported">
                        <p class="mt-3 rounded-chip bg-line-soft p-3 text-sm font-semibold text-muted">Dieser Browser kann keine Push-Nachrichten. Auf dem iPhone geht es, wenn du Packen über „Teilen → Zum Home-Bildschirm“ hinzufügst und von dort öffnest.</p>
                    </template>
                    <div x-show="supported" class="mt-4 flex flex-wrap items-center gap-2">
                        <button type="button" role="switch" :aria-checked="active" @click="toggle" :disabled="busy" class="flex min-h-11 items-center gap-3 text-[15px] font-bold">
                            <span class="relative inline-flex h-6 w-10 rounded-full transition" :class="active ? 'bg-accent' : 'bg-line'">
                                <span class="absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow transition" :class="active && 'translate-x-4'"></span>
                            </span>
                            <span x-text="active ? 'Eingeschaltet' : 'Ausgeschaltet'">Ausgeschaltet</span>
                        </button>
                        <button type="button" x-show="active" x-cloak @click="test" :disabled="busy" class="btn btn-ghost min-h-11 px-3 text-sm"><x-icon name="send" size="16" /> Probe-Nachricht</button>
                    </div>
                    <p x-show="message" x-cloak x-text="message" class="mt-2 text-sm font-semibold text-muted" role="status"></p>
                </div>
            @else
                <p class="mt-1 text-sm text-muted">Push ist auf dem Server noch nicht eingerichtet.</p>
            @endif
        </section>

        <form method="POST" action="{{ route('notifications.settings.update') }}" class="space-y-4">
            @csrf @method('PUT')

            <section class="card overflow-hidden">
                <div class="px-5 pt-5 pb-2">
                    <p class="card-title">Was soll als Push kommen?</p>
                    <p class="mt-1 text-sm text-muted">Gilt für alle deine Geräte mit eingeschaltetem Push.</p>
                </div>
                <div class="divide-y divide-line-soft">
                    @foreach (PushKind::cases() as $kind)
                        <label class="flex min-h-[64px] cursor-pointer items-center gap-4 px-5 py-3">
                            <x-icon :name="$kind->icon()" size="18" class="shrink-0 text-muted" />
                            <span class="flex-1">
                                <span class="block text-[15px] font-bold">{{ $kind->label() }}</span>
                                <span class="block text-sm text-muted">{{ $kind->description() }}</span>
                            </span>
                            <input type="checkbox" name="push[{{ $kind->value }}]" value="1" @checked($user->wantsPush($kind)) class="size-6 accent-[var(--accent)]">
                        </label>
                    @endforeach
                </div>
            </section>

            <section class="card">
                <label class="flex min-h-[64px] cursor-pointer items-center gap-4 px-5 py-4">
                    <x-icon name="mail" size="18" class="shrink-0 text-muted" />
                    <span class="flex-1">
                        <span class="block text-[15px] font-bold">Hinweise aus der Gruppe auch per E-Mail</span>
                        <span class="block text-sm text-muted">An {{ $user->email }} – z. B. wenn jemand fragt oder etwas für dich mitbringt.</span>
                    </span>
                    <input type="checkbox" name="email_notifications" value="1" @checked($user->email_notifications) class="size-6 accent-[var(--accent)]">
                </label>
            </section>

            <button type="submit" class="btn btn-primary">Speichern</button>
        </form>
    </div>
</x-layouts.app>
