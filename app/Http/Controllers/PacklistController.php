<?php

namespace App\Http\Controllers;

use App\Enums\EntrySource;
use App\Models\Category;
use App\Models\PacklistEntry;
use App\Models\Trip;
use App\Services\ContainerService;
use App\Services\GroupItemService;
use App\Services\PacklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PacklistController extends Controller
{
    public const MAX_QUANTITY = 999;

    public function __construct(private readonly PacklistService $packlists) {}

    /** Module (Vorlagen) auswählen; die Liste wird neu zusammengesetzt. */
    public function templates(Request $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('pack', $trip);

        $data = $request->validate(['template_ids' => ['array'], 'template_ids.*' => ['integer']]);

        $this->packlists->setTemplates($trip, $request->user(), $data['template_ids'] ?? []);

        return redirect()->route('trips.show', $trip)->with('status', 'Packliste aktualisiert.');
    }

    /** Welche Behältnisse kommen mit? */
    public function containers(Request $request, Trip $trip, ContainerService $containers): RedirectResponse
    {
        Gate::authorize('pack', $trip);

        $data = $request->validate(['container_ids' => ['array'], 'container_ids.*' => ['integer']]);
        $containers->setForTrip($trip, $request->user(), $data['container_ids'] ?? []);

        return redirect()->route('trips.show', ['trip' => $trip, 'ansicht' => 'behaeltnisse'])->with('status', 'Behältnisse aktualisiert.');
    }

    /** Mehrere Einträge in ein Behältnis legen (oder herausnehmen). */
    public function assign(Request $request, Trip $trip, ContainerService $containers): RedirectResponse
    {
        Gate::authorize('pack', $trip);

        $data = $request->validate([
            'entry_ids' => ['required', 'array'],
            'entry_ids.*' => ['integer'],
            'container_id' => ['nullable', 'integer'],
        ], ['entry_ids.required' => 'Bitte mindestens einen Eintrag auswählen.']);

        $count = $containers->assign($trip, $request->user(), $data['entry_ids'], $data['container_id'] ?? null);

        return redirect()->route('trips.show', ['trip' => $trip, 'ansicht' => 'behaeltnisse'])
            ->with('status', $count === 1 ? '1 Eintrag zugeordnet.' : "{$count} Einträge zugeordnet.");
    }

    public function store(Request $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('pack', $trip);

        $data = $request->validate([
            'item_id' => ['nullable', 'integer', Rule::exists('items', 'id')->where('owner_id', $request->user()->id)],
            'custom_name' => ['required_without:item_id', 'nullable', 'string', 'max:120'],
            'group' => ['nullable', 'string', Rule::in(Category::packlistChoices($request->user(), withHidden: true)->pluck('key'))],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],
            'note' => ['nullable', 'string', 'max:200'],
        ], ['custom_name.required_without' => 'Bitte einen Namen eingeben oder etwas aus dem Katalog wählen.']);

        $entry = $this->packlists->addEntry($trip, $request->user(), $data);

        return redirect()->route('trips.show', $trip)->with('status', "„{$entry->name()}“ steht auf der Liste.");
    }

    public function update(Request $request, Trip $trip, PacklistEntry $entry): RedirectResponse
    {
        Gate::authorize('update', $entry);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        if ($data['quantity'] !== $entry->quantity) {
            $this->packlists->updateQuantity($entry, $data['quantity']);
        }
        if ($request->has('note')) {
            $entry->update(['note' => $data['note']]);
        }
        if ($request->has('container_id')) {
            app(ContainerService::class)->assign($trip, $request->user(), [$entry->id], $request->integer('container_id') ?: null);
        }

        return redirect()->route('trips.show', ['trip' => $trip, 'ansicht' => $request->input('ansicht') === 'behaeltnisse' ? 'behaeltnisse' : null]);
    }

    /** Abhaken – per fetch (JSON) oder als normales Formular. */
    public function toggle(Request $request, Trip $trip, PacklistEntry $entry): JsonResponse|RedirectResponse
    {
        Gate::authorize('toggle', $entry);

        $request->validate([
            'packed' => ['required', 'boolean'],
            'changed_at' => ['nullable', 'date'],
        ]);

        $applied = $entry->setPacked(
            $request->boolean('packed'),
            $request->filled('changed_at') ? Carbon::parse($request->input('changed_at')) : null,
            $request->user(),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'applied' => $applied,
                'packed' => $entry->fresh()->packed,
                'progress' => $this->packlists->progress($trip, $entry->user),
            ]);
        }

        return redirect()->route('trips.show', $trip);
    }

    /** Entfernen – als Formular oder per fetch aus „Liste anpassen“. */
    public function destroy(Request $request, Trip $trip, PacklistEntry $entry): RedirectResponse|JsonResponse
    {
        Gate::authorize('delete', $entry);

        $name = $entry->load('item')->name();

        // Gemeinsamer Gegenstand: Entfernen heißt „wieder abgeben“.
        if ($entry->groupItem) {
            app(GroupItemService::class)->release($entry->groupItem, $request->user());
            $status = "„{$name}“ wieder an die Gruppe abgegeben.";
        } else {
            $this->packlists->removeEntry($entry);
            $status = "„{$name}“ entfernt.";
        }

        if ($request->expectsJson()) {
            // Nur ausgeblendete Vorlagen-Einträge lassen sich zurückholen; der Rest ist gelöscht.
            return response()->json(['restorable' => (bool) $entry->fresh()?->excluded, 'progress' => $this->packlists->progress($trip, $request->user())]);
        }

        return redirect()->route('trips.show', $trip)->with('status', $status);
    }

    /** Weggelassenen Vorlagen-Eintrag doch mitnehmen. */
    public function restore(Request $request, Trip $trip, PacklistEntry $entry): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $entry);
        abort_unless($entry->excluded && $entry->source === EntrySource::Template, 404);

        $this->packlists->restoreEntry($entry);

        if ($request->expectsJson()) {
            return response()->json(['packed' => false]);
        }

        return redirect()->route('trips.show', $trip)->with('status', "„{$entry->load('item')->name()}“ kommt wieder mit.");
    }

    public function copy(Request $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('pack', $trip);

        $data = $request->validate(['source_trip_id' => ['required', 'integer', Rule::notIn([$trip->id])]]);
        $source = Trip::findOrFail($data['source_trip_id']);
        Gate::authorize('pack', $source);

        $copied = $this->packlists->copyFrom($source, $trip, $request->user());

        return redirect()->route('trips.show', $trip)->with('status', "Liste von „{$source->name}“ übernommen ({$copied} eigene Einträge).");
    }
}
