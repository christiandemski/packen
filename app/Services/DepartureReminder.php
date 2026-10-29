<?php

namespace App\Services;

use App\Enums\PacklistGroup;
use App\Models\PacklistEntry;
use App\Models\PushSubscription;
use App\Models\Trip;
use App\Models\User;
use App\Support\WebPush;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Erinnerung am Morgen des Abfahrtstags: „Heute geht's los – noch 3 Dinge für den
 * Abfahrtstag offen“. Läuft einmal täglich (Plesk: geplante Aufgabe, siehe README).
 */
class DepartureReminder
{
    /**
     * Was eine Person gerade angezeigt bekommen soll – der Service Worker fragt das nach jedem Push ab.
     *
     * @return list<array{title: string, body: string, url: string, tag: string}>
     */
    public function messagesFor(User $user): array
    {
        $messages = [];

        if (Cache::get("push-test:{$user->id}")) {
            $messages[] = ['title' => 'Packen', 'body' => 'Push funktioniert – am Abfahrtstag meldet sich die App hier.', 'url' => route('profile.edit'), 'tag' => 'packen-test'];
        }

        $trips = $user->trips()->whereDate('start_date', today())->get();
        foreach ($trips as $trip) {
            if ($body = $this->summary($trip, $user)) {
                $messages[] = ['title' => "Heute geht's los: {$trip->name}", 'body' => $body, 'url' => route('trips.show', $trip), 'tag' => "abfahrt-{$trip->id}"];
            }
        }

        return $messages;
    }

    /** Für alle Reisen, die heute beginnen; je Person und Reise höchstens einmal am Tag. */
    public function run(): int
    {
        if (! WebPush::enabled()) {
            return 0;
        }

        $sent = 0;
        $trips = Trip::whereDate('start_date', today())->with('members')->get();

        foreach ($trips as $trip) {
            foreach ($trip->members as $member) {
                $pivot = DB::table('trip_members')->where(['trip_id' => $trip->id, 'user_id' => $member->id]);
                if ($pivot->value('departure_reminded_on') === today()->toDateString() || $this->summary($trip, $member) === null) {
                    continue;
                }

                $devices = PushSubscription::where('user_id', $member->id)->get();
                if ($devices->isEmpty()) {
                    continue;
                }

                foreach ($devices as $device) {
                    $sent += WebPush::send($device) ? 1 : 0;
                }
                $pivot->update(['departure_reminded_on' => today()->toDateString()]);
            }
        }

        return $sent;
    }

    /** z. B. „Noch 3 Dinge für den Abfahrtstag und 2 Aufgaben offen.“ – null, wenn alles erledigt ist. */
    private function summary(Trip $trip, User $user): ?string
    {
        $open = PacklistEntry::with('item')->where(['trip_id' => $trip->id, 'user_id' => $user->id, 'packed' => false])->visible()->get();

        $departure = $open->filter(fn (PacklistEntry $e) => $e->packlistGroup()->is(PacklistGroup::DepartureDay))->count();
        $tasks = $open->filter(fn (PacklistEntry $e) => $e->isTask())->count();

        $parts = array_filter([
            $departure ? ($departure === 1 ? '1 Ding für den Abfahrtstag' : "{$departure} Dinge für den Abfahrtstag") : null,
            $tasks ? ($tasks === 1 ? '1 Aufgabe' : "{$tasks} Aufgaben") : null,
        ]);

        return $parts ? 'Noch '.implode(' und ', $parts).' offen.' : null;
    }
}
