<?php

namespace App\Http\Controllers;

use App\Enums\EntrySource;
use App\Enums\PacklistGroup;
use App\Models\Category;
use App\Models\Item;
use App\Models\PacklistEntry;
use App\Models\Template;
use App\Models\Trip;
use App\Services\PacklistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Rückblick nach der Reise: Was war unnötig, was hat gefehlt? „Hat gefehlt“ lässt sich
 * per Klick in eine Vorlage übernehmen, „nicht gebraucht“ aus Vorlagen streichen.
 */
class ReviewController extends Controller
{
    public const UNUSED = 'unused';

    public const MISSING = 'missing';

    public function update(Request $request, Trip $trip, PacklistEntry $entry): RedirectResponse
    {
        $this->authorizeEntry($trip, $entry);

        $data = $request->validate(['review' => ['nullable', Rule::in([self::UNUSED, self::MISSING])]]);
        $entry->update(['review' => $data['review'] ?? null]);

        return $this->back($trip);
    }

    /** Etwas, das gar nicht auf der Liste stand, aber gefehlt hat. */
    public function missing(Request $request, Trip $trip, PacklistService $packlists): RedirectResponse
    {
        Gate::authorize('pack', $trip);

        $data = $request->validate(['name' => ['required', 'string', 'max:120']], attributes: ['name' => 'Gegenstand']);
        $name = trim($data['name']);
        $item = Item::accessibleBy($request->user())->whereRaw('lower(name) = ?', [Str::lower($name)])->first();

        $entry = $packlists->addEntry($trip, $request->user(), $item ? ['item_id' => $item->id] : ['custom_name' => $name, 'group' => PacklistGroup::Misc->value]);
        $entry->update(['review' => self::MISSING]);

        return $this->back($trip);
    }

    /** „Hat gefehlt“ → in eine Vorlage. Freitext wird dabei zum Katalog-Gegenstand. */
    public function addToTemplate(Request $request, Trip $trip, PacklistEntry $entry, PacklistService $packlists): RedirectResponse
    {
        $this->authorizeEntry($trip, $entry);

        $data = $request->validate(['template_id' => ['required', 'integer', Rule::exists('templates', 'id')->where('owner_id', $request->user()->id)]]);
        $template = Template::find($data['template_id']);

        $item = $entry->item ?? Item::accessibleBy($request->user())->whereRaw('lower(name) = ?', [Str::lower($entry->name())])->first()
            ?? $request->user()->items()->create([
                'name' => $entry->name(),
                ...Category::columnsFor($entry->group, $request->user()),
                'pack_last' => $entry->group === PacklistGroup::DepartureDay->value,
            ]);

        if ($entry->item_id === null && $entry->group_item_id === null) {
            $entry->update(['item_id' => $item->id, 'custom_name' => null, 'source' => EntrySource::Manual]);
        }

        $template->items()->syncWithoutDetaching([$item->id => ['quantity' => max(1, $entry->quantity)]]);
        $packlists->syncTemplate($template);

        return $this->back($trip, "„{$item->name}“ steht jetzt in der Vorlage „{$template->name}“.");
    }

    /** „Nicht gebraucht“ → aus einer Vorlage streichen (der Katalog bleibt). */
    public function removeFromTemplate(Request $request, Trip $trip, PacklistEntry $entry, Template $template, PacklistService $packlists): RedirectResponse
    {
        $this->authorizeEntry($trip, $entry);
        Gate::authorize('update', $template);
        abort_if($entry->item_id === null, 404);

        $template->items()->detach($entry->item_id);
        $packlists->syncTemplate($template); // betrifft nur kommende Reisen

        $status = "„{$entry->item->name}“ aus „{$template->name}“ gestrichen.";

        // Aus „Diesmal nicht dabei“ in der Packliste
        if ($request->input('zurueck') === 'packliste') {
            return redirect()->route('trips.show', $trip)->with('status', $status);
        }

        return $this->back($trip, $status);
    }

    private function authorizeEntry(Trip $trip, PacklistEntry $entry): void
    {
        abort_unless($entry->trip_id === $trip->id, 404);
        Gate::authorize('update', $entry);
    }

    private function back(Trip $trip, ?string $status = null): RedirectResponse
    {
        $redirect = redirect()->route('trips.show', ['trip' => $trip, 'tab' => 'review']);

        return $status ? $redirect->with('status', $status) : $redirect;
    }
}
