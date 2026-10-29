<?php

namespace App\Services;

use App\Enums\PacklistGroup;
use App\Enums\PushKind;
use App\Models\PacklistEntry;
use App\Models\Trip;
use App\Models\User;
use App\Support\WebPush;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Erinnerungen per Push: am Vorabend (18 Uhr), am Abfahrtstag (7 Uhr) und zum Rückblick
 * am Tag nach der Reise (10 Uhr), jeweils Ortszeit. Plesk ruft /cron/abfahrt stündlich auf;
 * je Reise, Person und Art geht eine Erinnerung nur einmal raus.
 *
 * Der Push selbst ist nur ein Signal – der Service Worker fragt danach messagesFor() ab.
 */
class Reminders
{
    public const TIMEZONE = 'Europe/Berlin';

    /** So lange zeigt der Service Worker eine verschickte Erinnerung bzw. einen Gruppen-Hinweis an. */
    private const RECENT_MINUTES = 360;

    private const RECENT_NOTIFICATION_MINUTES = 30;

    public function run(?Carbon $now = null): int
    {
        if (! WebPush::enabled()) {
            return 0;
        }

        $local = ($now ?? now())->copy()->setTimezone(self::TIMEZONE);
        $sent = 0;

        foreach ([PushKind::Eve, PushKind::Departure, PushKind::Review] as $kind) {
            if ($local->hour < $kind->hour()) {
                continue;
            }

            foreach ($this->tripsFor($kind, $local)->with('members')->get() as $trip) {
                foreach ($trip->members as $member) {
                    $sent += $this->remind($kind, $trip, $member);
                }
            }
        }

        return $sent;
    }

    /**
     * Was eine Person gerade angezeigt bekommen soll – der Service Worker fragt das nach jedem Push ab.
     *
     * @return list<array{title: string, body: string, url: string, tag: string}>
     */
    public function messagesFor(User $user): array
    {
        $messages = [];

        if (Cache::get("push-test:{$user->id}")) {
            $messages[] = ['title' => 'Packen', 'body' => 'Push funktioniert – Erinnerungen kommen hier an.', 'url' => route('notifications.settings'), 'tag' => 'packen-test'];
        }

        $recent = DB::table('reminders_sent')->where('user_id', $user->id)->where('sent_at', '>=', now()->subMinutes(self::RECENT_MINUTES))->get();
        foreach ($recent as $row) {
            $trip = Trip::find($row->trip_id);
            $kind = PushKind::from($row->kind);
            if ($trip && ($body = $this->body($kind, $trip, $user))) {
                $messages[] = ['title' => $this->title($kind, $trip), 'body' => $body, 'url' => $this->url($kind, $trip), 'tag' => "{$kind->value}-{$trip->id}"];
            }
        }

        $notifications = $user->notifications()->where('created_at', '>=', now()->subMinutes(self::RECENT_NOTIFICATION_MINUTES))->latest()->limit(5)->get();
        foreach ($notifications as $notification) {
            $messages[] = [
                'title' => $notification->data['trip_name'],
                'body' => $notification->data['message'],
                'url' => route('trips.show', $notification->data['trip_id']),
                'tag' => "n-{$notification->id}",
            ];
        }

        return $messages;
    }

    /** @return Builder<Trip> */
    private function tripsFor(PushKind $kind, Carbon $local)
    {
        return match ($kind) {
            PushKind::Eve => Trip::whereDate('start_date', $local->copy()->addDay()->toDateString()),
            PushKind::Departure => Trip::whereDate('start_date', $local->toDateString()),
            PushKind::Review => Trip::whereDate('end_date', $local->copy()->subDay()->toDateString()),
        };
    }

    private function remind(PushKind $kind, Trip $trip, User $user): int
    {
        if (! $user->wantsPush($kind) || $this->body($kind, $trip, $user) === null) {
            return 0;
        }

        $devices = $user->pushSubscriptions()->get();
        if ($devices->isEmpty()) {
            return 0;
        }

        // Zuerst vermerken: Der Service Worker fragt sofort nach dem Push, was er zeigen soll.
        $inserted = DB::table('reminders_sent')->insertOrIgnore(['trip_id' => $trip->id, 'user_id' => $user->id, 'kind' => $kind->value, 'sent_at' => now()]);
        if ($inserted === 0) {
            return 0; // schon verschickt
        }

        return $devices->filter(fn ($device) => WebPush::send($device))->count();
    }

    private function title(PushKind $kind, Trip $trip): string
    {
        return match ($kind) {
            PushKind::Eve => "Morgen geht's los: {$trip->name}",
            PushKind::Departure => "Heute geht's los: {$trip->name}",
            PushKind::Review => "Wie war's? {$trip->name}",
            PushKind::Group => $trip->name,
        };
    }

    private function url(PushKind $kind, Trip $trip): string
    {
        return $kind === PushKind::Review ? route('trips.show', ['trip' => $trip, 'tab' => 'review']) : route('trips.show', $trip);
    }

    /** Text der Erinnerung – null, wenn es nichts zu sagen gibt (alles gepackt, keine Liste). */
    private function body(PushKind $kind, Trip $trip, User $user): ?string
    {
        $entries = PacklistEntry::with('item')->where(['trip_id' => $trip->id, 'user_id' => $user->id])->visible()->get();
        if ($entries->isEmpty()) {
            return null;
        }

        $open = $entries->where('packed', false);

        if ($kind === PushKind::Eve) {
            $count = $open->count();

            return $count ? 'Noch '.($count === 1 ? '1 Ding' : "{$count} Dinge").' auf der Packliste offen.' : null;
        }

        if ($kind === PushKind::Review) {
            return 'Was hat gefehlt, was war zu viel? Zwei Minuten Rückblick – die nächste Liste wird besser.';
        }

        $departure = $open->filter(fn (PacklistEntry $e) => $e->packlistGroup()->is(PacklistGroup::DepartureDay))->count();
        $tasks = $open->filter(fn (PacklistEntry $e) => $e->isTask())->count();

        $parts = array_filter([
            $departure ? ($departure === 1 ? '1 Ding für den Abfahrtstag' : "{$departure} Dinge für den Abfahrtstag") : null,
            $tasks ? ($tasks === 1 ? '1 Aufgabe' : "{$tasks} Aufgaben") : null,
        ]);

        return $parts ? 'Noch '.implode(' und ', $parts).' offen.' : null;
    }
}
