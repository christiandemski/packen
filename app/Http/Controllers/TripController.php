<?php

namespace App\Http\Controllers;

use App\Enums\EntrySource;
use App\Enums\PacklistGroup;
use App\Enums\TripMode;
use App\Http\Requests\TripRequest;
use App\Models\Container;
use App\Models\GroupItem;
use App\Models\PacklistEntry;
use App\Models\Template;
use App\Models\Trip;
use App\Models\User;
use App\Policies\PacklistEntryPolicy;
use App\Services\ContainerService;
use App\Services\OutfitService;
use App\Services\PacklistService;
use App\Services\TripService;
use App\Services\WeatherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    public function show(Request $request, Trip $trip, PacklistService $packlists, ContainerService $containers, OutfitService $outfits, WeatherService $weather): View
    {
        Gate::authorize('view', $trip);
        $user = $request->user();

        // Liste einer mitreisenden Person, die ihre Packliste für mich freigegeben hat
        $listOwner = $user;
        if ($request->filled('liste') && (int) $request->query('liste') !== $user->id) {
            $listOwner = $trip->members()->whereKey((int) $request->query('liste'))->first();
            abort_unless($listOwner && PacklistEntryPolicy::hasGrant($trip->id, $listOwner->id, $user->id), 403);
        }
        $canEdit = $listOwner->is($user);

        $entries = $trip->entries()->with(['item', 'packer', 'groupItem'])->where('user_id', $listOwner->id)->visible()->get();
        $groups = $entries
            ->sortBy(fn (PacklistEntry $entry) => mb_strtolower($entry->name()))
            ->groupBy(fn (PacklistEntry $entry) => $entry->packlistGroup()->value)
            ->sortBy(fn ($group, $key) => PacklistGroup::from($key)->position());

        $templates = Template::accessibleBy($user)->withCount('items')->orderBy('name')->get();
        $selected = $trip->templatesFor($user)->pluck('templates.id');

        // Vorschlag für eine neue Liste: „Basis“ plus die Vorlage, die wie die Reiseart heißt.
        $suggested = $templates->filter(fn (Template $t) => in_array(mb_strtolower($t->name), ['basis', mb_strtolower($trip->type->label())], true))->pluck('id');

        $otherTrips = $user->trips()->whereKeyNot($trip->id)
            ->whereHas('entries', fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('start_date')->get();

        $outfitDays = $outfits->days($trip, $user);
        $selectedDay = $outfitDays->first(fn ($day) => $day->date->toDateString() === $request->query('tag'))
            ?? $outfitDays->first(fn ($day) => $day->date->isToday())
            ?? $outfitDays->first();

        $tripContainers = $trip->containersFor($listOwner)->orderBy('name')->get();
        $summary = $containers->summary($trip, $listOwner, $entries);
        $members = $trip->members()->get();

        return view('trips.show', [
            'trip' => $trip->load('members'),
            'groups' => $groups,
            'listOwner' => $listOwner,
            'canEdit' => $canEdit,
            'grantedToMe' => $members->whereIn('id', DB::table('packlist_grants')->where(['trip_id' => $trip->id, 'grantee_id' => $user->id])->pluck('owner_id'))->values(),
            'myGrantees' => DB::table('packlist_grants')->where(['trip_id' => $trip->id, 'owner_id' => $user->id])->pluck('grantee_id'),
            'groupItems' => $trip->isGroup() ? GroupItem::with(['requester', 'assignee', 'creator'])->where('trip_id', $trip->id)->active()->orderBy('status')->orderBy('name')->get() : collect(),
            'sharedOutfits' => $members->filter(fn ($m) => $m->pivot->outfits_shared && $m->isNot($user))->map(fn ($m) => ['user' => $m, 'days' => $outfits->existingDays($trip, $m)])->values(),
            'myOutfitsShared' => (bool) $members->firstWhere('id', $user->id)?->pivot->outfits_shared,
            'userContainers' => $canEdit ? Container::accessibleBy($user)->orderBy('name')->get() : collect(),
            'tripContainers' => $tripContainers,
            'containerSummary' => $summary,
            'neededLabel' => ContainerService::neededLabel($summary),
            'weight' => ContainerService::totalWeight($entries, $summary),
            'unassigned' => $entries->whereNull('container_id')->sortBy(fn (PacklistEntry $e) => mb_strtolower($e->name()))->values(),
            'initialView' => $request->query('ansicht') === 'behaeltnisse' ? 'containers' : 'groups',
            'initialTab' => in_array($request->query('tab'), ['outfits', 'review'], true) ? $request->query('tab') : 'packlist',
            'outfitDays' => $outfitDays,
            'weather' => $weather->forTrip($trip),
            'selectedDay' => $selectedDay?->date->toDateString(),
            'outfitOverview' => $outfits->overview($outfitDays),
            'outfitQuantities' => $outfits->quantities($outfitDays),
            'clothingItems' => $outfits->clothingItems($user),
            'hasOutfitEntries' => $entries->contains(fn (PacklistEntry $e) => $e->source === EntrySource::Outfit),
            'progress' => $packlists->progress($trip, $listOwner),
            'templates' => $templates,
            'selected' => $selected,
            'suggested' => $suggested,
            'otherTrips' => $otherTrips,
            'addable' => $packlists->addableItems($trip, $user),
        ]);
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
