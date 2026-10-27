<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Was ich in einer gemeinsamen Reise mit Mitreisenden teile. */
class SharingController extends Controller
{
    /** Meine Packliste für einzelne Mitreisende zum Abhaken freigeben (nur in eine Richtung). */
    public function packlist(Request $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('pack', $trip);
        $user = $request->user();

        $data = $request->validate(['grantee_ids' => ['array'], 'grantee_ids.*' => ['integer']]);
        $ids = $trip->members()->whereKeyNot($user->id)->whereIn('users.id', $data['grantee_ids'] ?? [])->pluck('users.id');

        DB::transaction(function () use ($trip, $user, $ids) {
            DB::table('packlist_grants')->where(['trip_id' => $trip->id, 'owner_id' => $user->id])->whereNotIn('grantee_id', $ids)->delete();
            foreach ($ids as $id) {
                DB::table('packlist_grants')->insertOrIgnore([
                    'trip_id' => $trip->id, 'owner_id' => $user->id, 'grantee_id' => $id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('trips.show', $trip)->with('status', 'Freigabe gespeichert.');
    }

    /** Meine Outfits für alle Mitreisenden lesbar machen. */
    public function outfits(Request $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('pack', $trip);

        $shared = $request->boolean('shared');
        $trip->members()->updateExistingPivot($request->user()->id, ['outfits_shared' => $shared]);

        return redirect()->route('trips.show', ['trip' => $trip, 'tab' => 'outfits'])
            ->with('status', $shared ? 'Deine Outfits sind für Mitreisende sichtbar.' : 'Deine Outfits sind wieder privat.');
    }
}
