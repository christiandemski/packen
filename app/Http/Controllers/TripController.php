<?php

namespace App\Http\Controllers;

use App\Enums\TripMode;
use App\Http\Requests\TripRequest;
use App\Models\Trip;
use App\Models\User;
use App\Services\TripService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TripController extends Controller
{
    public function __construct(private readonly TripService $trips) {}

    public function create(Request $request): View
    {
        Gate::authorize('create', Trip::class);

        $trip = new Trip([
            'mode' => $request->query('mode') === TripMode::Group->value ? TripMode::Group : TripMode::Solo,
        ]);

        return view('trips.form', ['trip' => $trip, 'friends' => $this->friendsOf($request->user())]);
    }

    public function store(TripRequest $request): RedirectResponse
    {
        Gate::authorize('create', Trip::class);

        $trip = $this->trips->create(
            $request->user(),
            $request->validated(),
            $request->file('cover'),
            $request->memberIds(),
        );

        return redirect()->route('trips.show', $trip)->with('status', 'Reise angelegt.');
    }

    public function show(Trip $trip): View
    {
        Gate::authorize('view', $trip);

        return view('trips.show', ['trip' => $trip->load('members')]);
    }

    public function edit(Request $request, Trip $trip): View
    {
        Gate::authorize('update', $trip);

        return view('trips.form', ['trip' => $trip->load('members'), 'friends' => $this->friendsOf($request->user())]);
    }

    public function update(TripRequest $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('update', $trip);

        $this->trips->update(
            $trip,
            $request->validated(),
            $request->file('cover'),
            $request->boolean('remove_cover'),
            $request->memberIds(),
        );

        return redirect()->route('trips.show', $trip)->with('status', 'Gespeichert.');
    }

    public function destroy(Trip $trip): RedirectResponse
    {
        Gate::authorize('delete', $trip);

        $this->trips->delete($trip);

        return redirect()->route('home')->with('status', 'Reise gelöscht.');
    }

    /** Bilder liegen privat und werden nur an Mitglieder ausgeliefert. */
    public function cover(Trip $trip): StreamedResponse
    {
        Gate::authorize('view', $trip);

        $disk = Storage::disk(TripService::COVER_DISK);
        abort_if($trip->cover_path === null || ! $disk->exists($trip->cover_path), 404);

        return $disk->response($trip->cover_path, headers: [
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }

    /** @return Collection<int, User> */
    private function friendsOf(User $user): Collection
    {
        return User::where('active', true)->whereKeyNot($user->id)->orderBy('name')->get();
    }
}
