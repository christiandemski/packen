<?php

namespace App\Http\Controllers\Api;

use App\Enums\TripMode;
use App\Enums\TripType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TripRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Services\ContainerService;
use App\Services\PacklistService;
use App\Services\TripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** Schnittstelle für „unterwegs“: Reisen anlegen, ändern, löschen, Stand abfragen. */
class TripController extends Controller
{
    public function __construct(private readonly TripService $trips) {}

    /**
     * Legt eine Reise an. Gibt es zur external_id schon eine eigene Reise, wird diese
     * aktualisiert (200 statt 201) – wiederholtes Senden verdoppelt also nichts.
     */
    public function store(TripRequest $request): JsonResponse
    {
        Gate::authorize('create', Trip::class);
        $user = $request->user();
        $data = $request->tripData();

        $existing = filled($data['external_id'] ?? null)
            ? Trip::where(['owner_id' => $user->id, 'external_id' => $data['external_id']])->first()
            : null;

        if ($existing !== null) {
            $trip = $this->trips->update($existing, $data);

            return (new TripResource($trip->load('stages')))->response()->setStatusCode(200);
        }

        $trip = $this->trips->create($user, [
            'type' => TripType::Other->value,
            ...$data,
            'mode' => TripMode::Solo->value,
        ]);

        return (new TripResource($trip->load('stages')))->response()->setStatusCode(201);
    }

    public function show(Trip $trip): TripResource
    {
        Gate::authorize('view', $trip);

        return new TripResource($trip->load('stages'));
    }

    public function update(TripRequest $request, Trip $trip): TripResource
    {
        Gate::authorize('update', $trip);

        return new TripResource($this->trips->update($trip, $request->tripData())->load('stages'));
    }

    /** Löscht die Reise samt Packlisten, Outfits und Etappen – auch für Mitreisende. Nur der Owner. */
    public function destroy(Trip $trip): Response
    {
        Gate::authorize('delete', $trip);

        $this->trips->delete($trip);

        return response()->noContent();
    }

    /** Packstand der eigenen Liste, z. B. „18/42“, und Gesamtgewicht. */
    public function summary(Request $request, Trip $trip, PacklistService $packlists, ContainerService $containers): JsonResponse
    {
        Gate::authorize('view', $trip);
        $user = $request->user();

        $progress = $packlists->progress($trip, $user);
        $entries = $trip->entries()->with('item')->where('user_id', $user->id)->visible()->get();
        $weight = ContainerService::totalWeight($entries, $containers->summary($trip, $user, $entries));

        return response()->json(['data' => [
            'packed' => $progress['packed'],
            'total' => $progress['total'],
            'progress' => $progress['packed'].'/'.$progress['total'],
            'weight_g' => $weight['total'],
            'weight_complete' => $weight['missing'] === 0,
            'url' => route('trips.show', $trip),
        ]]);
    }
}
