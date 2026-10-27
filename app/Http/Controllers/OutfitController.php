<?php

namespace App\Http\Controllers;

use App\Enums\ItemCategory;
use App\Models\Item;
use App\Models\OutfitDay;
use App\Models\OutfitSlot;
use App\Models\Trip;
use App\Services\OutfitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Outfits sind persönlich: Jede Person plant nur ihre eigenen Tage. */
class OutfitController extends Controller
{
    public function __construct(private readonly OutfitService $outfits) {}

    public function updateDay(Request $request, Trip $trip, OutfitDay $day): RedirectResponse
    {
        $this->authorizeDay($request, $trip, $day);

        $data = $request->validate(['title' => ['nullable', 'string', 'max:120'], 'laundry_day' => ['boolean']]);
        $day->update([
            'title' => $request->has('title') ? (trim((string) $data['title']) ?: null) : $day->title,
            'laundry_day' => $request->has('laundry_day') ? $request->boolean('laundry_day') : $day->laundry_day,
        ]);

        return $this->back($trip, $day);
    }

    public function copyPrevious(Request $request, Trip $trip, OutfitDay $day): RedirectResponse
    {
        $this->authorizeDay($request, $trip, $day);

        $copied = $this->outfits->copyPreviousDay($day);

        return $this->back($trip, $day, $copied ? 'Outfits vom Vortag übernommen.' : null);
    }

    public function storeSlot(Request $request, Trip $trip, OutfitDay $day): RedirectResponse
    {
        $this->authorizeDay($request, $trip, $day);

        $data = $request->validate(['name' => ['required', 'string', 'max:40']], attributes: ['name' => 'Name']);
        $this->outfits->addSlot($day, $data['name']);

        return $this->back($trip, $day);
    }

    public function destroySlot(Request $request, Trip $trip, OutfitSlot $slot): RedirectResponse
    {
        $day = $slot->day;
        $this->authorizeDay($request, $trip, $day);

        $slot->delete();

        return $this->back($trip, $day);
    }

    /** Teil aus dem Katalog in einen Slot – oder neues Kleidungsstück anlegen und hinzufügen. */
    public function storeItem(Request $request, Trip $trip, OutfitSlot $slot): RedirectResponse
    {
        $day = $slot->day;
        $this->authorizeDay($request, $trip, $day);
        $user = $request->user();

        if ($request->filled('new_name')) {
            $data = $request->validate([
                'new_name' => ['string', 'max:120'],
                'new_category' => ['required', Rule::in(collect(ItemCategory::clothing())->map->value)],
            ], attributes: ['new_name' => 'Name', 'new_category' => 'Kategorie']);

            $name = trim($data['new_name']);
            $item = Item::accessibleBy($user)->whereRaw('lower(name) = ?', [Str::lower($name)])->first()
                ?? $user->items()->create(['name' => $name, 'category' => $data['new_category']]);
        } else {
            $data = $request->validate(['item_id' => ['required', 'integer', Rule::exists('items', 'id')->where('owner_id', $user->id)]]);
            $item = Item::find($data['item_id']);
        }

        $slot->items()->syncWithoutDetaching([$item->id]);

        return $this->back($trip, $day);
    }

    public function destroyItem(Request $request, Trip $trip, OutfitSlot $slot, Item $item): RedirectResponse
    {
        $day = $slot->day;
        $this->authorizeDay($request, $trip, $day);

        $slot->items()->detach($item->id);

        return $this->back($trip, $day);
    }

    public function apply(Request $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('pack', $trip);

        $count = $this->outfits->applyToPacklist($trip, $request->user());

        return redirect()->route('trips.show', $trip)
            ->with('status', $count === 0 ? 'Keine Outfits geplant – an der Packliste hat sich nichts geändert.' : "Kleidung übernommen ({$count} Teile).");
    }

    private function authorizeDay(Request $request, Trip $trip, OutfitDay $day): void
    {
        Gate::authorize('pack', $trip);
        abort_unless($day->trip_id === $trip->id && $day->user_id === $request->user()->id, 404);
    }

    private function back(Trip $trip, OutfitDay $day, ?string $status = null): RedirectResponse
    {
        $redirect = redirect()->route('trips.show', ['trip' => $trip, 'tab' => 'outfits', 'tag' => $day->date->toDateString()]);

        return $status ? $redirect->with('status', $status) : $redirect;
    }
}
