<x-layouts.app title="Admin">
    <div class="mx-auto max-w-3xl space-y-4">
        <div class="mb-2">
            <p class="eyebrow">Verwaltung</p>
            <h1 class="page-title mt-1">Freunde</h1>
        </div>

        <form method="POST" action="{{ route('admin.invitations.store') }}" class="card p-5">
            @csrf
            <p class="card-title">Freund einladen</p>
            <p class="mt-1 text-sm text-muted">Die Person bekommt eine E-Mail mit einem Link, der 7 Tage gilt.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_1.4fr_auto] sm:items-end">
                <div>
                    <label for="invite-name" class="label">Name</label>
                    <input id="invite-name" name="name" value="{{ old('name') }}" required maxlength="100" class="input">
                    @error('name', 'invite')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="invite-email" class="label">E-Mail-Adresse</label>
                    <input id="invite-email" name="email" type="email" value="{{ old('email') }}" required class="input">
                    @error('email', 'invite')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn btn-primary"><x-icon name="send" size="18" /> Einladen</button>
            </div>
        </form>

        @if ($invitations->isNotEmpty())
            <section class="card overflow-hidden">
                <p class="card-title px-5 pt-5 pb-2">Offene Einladungen · {{ $invitations->count() }}</p>
                <ul class="divide-y divide-line-soft">
                    @foreach ($invitations as $invitation)
                        <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                            <span class="inline-flex size-9 items-center justify-center rounded-full bg-line-soft text-muted"><x-icon name="mail" size="18" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[15px] font-bold">{{ $invitation->name }}</p>
                                <p class="truncate text-[13px] font-semibold text-muted">{{ $invitation->email }}</p>
                            </div>
                            @if ($invitation->isExpired())
                                <span class="chip bg-warn-soft text-warn-ink">Abgelaufen</span>
                            @else
                                <span class="chip bg-accent-soft text-accent-ink">Gültig bis {{ $invitation->expires_at->translatedFormat('j. M') }}</span>
                            @endif
                            <div class="flex gap-1">
                                <form method="POST" action="{{ route('admin.invitations.resend', $invitation) }}">
                                    @csrf
                                    <button type="submit" class="icon-btn" aria-label="Erneut senden" title="Erneut senden"><x-icon name="refresh" size="18" /></button>
                                </form>
                                <form method="POST" action="{{ route('admin.invitations.destroy', $invitation) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="icon-btn text-warn-ink" aria-label="Zurückziehen" title="Zurückziehen"><x-icon name="x" size="18" /></button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="card overflow-hidden">
            <p class="card-title px-5 pt-5 pb-2">Konten · {{ $users->count() }}</p>
            <ul class="divide-y divide-line-soft">
                @foreach ($users as $user)
                    @php($bag = 'delete-'.$user->id)
                    <li class="px-5 py-3" x-data="{ deleting: {{ $errors->getBag($bag)->any() ? 'true' : 'false' }} }">
                        <div class="flex flex-wrap items-center gap-3">
                            <x-avatar :user="$user" />
                            <div class="min-w-0 flex-1">
                                <p class="flex items-center gap-2 truncate text-[15px] font-bold">
                                    {{ $user->name }}
                                    @if ($user->isAdmin())<span class="chip bg-ink text-white">Admin</span>@endif
                                    @unless ($user->active)<span class="chip bg-warn-soft text-warn-ink">Gesperrt</span>@endunless
                                </p>
                                <p class="truncate text-[13px] font-semibold text-muted">
                                    {{ $user->email }} · {{ $user->last_login_at ? 'zuletzt '.$user->last_login_at->diffForHumans() : 'noch nie angemeldet' }}
                                </p>
                            </div>
                            @can('manage', $user)
                                <div class="flex gap-1">
                                    <form method="POST" action="{{ $user->active ? route('admin.users.block', $user) : route('admin.users.unblock', $user) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary min-h-10 px-3 text-sm">
                                            <x-icon :name="$user->active ? 'ban' : 'check'" size="16" /> {{ $user->active ? 'Sperren' : 'Entsperren' }}
                                        </button>
                                    </form>
                                    <button type="button" class="icon-btn text-warn-ink" aria-label="Konto löschen" title="Konto löschen" @click="deleting = !deleting"><x-icon name="trash" size="18" /></button>
                                </div>
                            @endcan
                        </div>
                        @can('manage', $user)
                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" x-show="deleting" x-cloak class="mt-3 rounded-chip bg-warn-soft p-4">
                                @csrf @method('DELETE')
                                <p class="text-sm font-semibold text-warn-ink">
                                    Löscht das Konto von {{ $user->name }} mit allen privaten Reisen. In gemeinsamen Reisen erscheint die Person als „Ehemaliges Mitglied“.
                                    Zur Bestätigung die E-Mail-Adresse eingeben:
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <input name="confirm_email" type="email" required placeholder="{{ $user->email }}" class="input max-w-xs flex-1" aria-label="E-Mail-Adresse zur Bestätigung">
                                    <button type="submit" class="btn bg-warn text-white hover:bg-warn-ink">Endgültig löschen</button>
                                </div>
                                @error('confirm_email', $bag)<p class="field-error">{{ $message }}</p>@enderror
                            </form>
                        @endcan
                    </li>
                @endforeach
            </ul>
        </section>
        <section class="card p-5" x-data="{ copied: false }">
            <p class="card-title flex items-center gap-2"><x-icon name="bell" size="18" class="text-accent" /> Erinnerungen per Push</p>
            @if ($pushReady && $cronUrl)
                <p class="mt-1 text-sm text-muted">In Plesk unter <strong>Geplante Aufgaben</strong> eine Aufgabe „URL abrufen“ mit diesem Link anlegen, <strong>stündlich</strong> (Vorabend, Abfahrtstag und Rückblick gehen dann zur passenden Uhrzeit raus). {{ $pushDevices }} {{ $pushDevices === 1 ? 'Gerät hat' : 'Geräte haben' }} die Erinnerung eingeschaltet.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <input readonly value="{{ $cronUrl }}" aria-label="Cron-Link" class="input min-w-0 flex-1 font-mono text-xs" @focus="$el.select()">
                    <button type="button" class="btn btn-secondary" @click="navigator.clipboard.writeText(@js($cronUrl)); copied = true"><x-icon name="copy" size="16" /> <span x-text="copied ? 'Kopiert' : 'Kopieren'">Kopieren</span></button>
                </div>
                <p class="mt-2 text-xs font-semibold text-muted">Der Link enthält einen geheimen Schlüssel – nicht weitergeben.</p>
            @else
                <p class="mt-1 text-sm text-muted">Noch nicht eingerichtet: Beim nächsten Deployment legt <code>deploy.sh</code> die Schlüssel in der <code>.env</code> an (<code>php artisan packen:push-einrichten</code>).</p>
            @endif
        </section>
    </div>
</x-layouts.app>
