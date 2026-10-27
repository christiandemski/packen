<?php

namespace App\Http\Controllers;

use App\Enums\PacklistGroup;
use App\Models\PacklistEntry;
use App\Models\Trip;
use App\Services\PacklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('pack', $trip);

        $data = $request->validate([
            'item_id' => ['nullable', 'integer', Rule::exists('items', 'id')->where('owner_id', $request->user()->id)],
            'custom_name' => ['required_without:item_id', 'nullable', 'string', 'max:120'],
            'group' => ['nullable', Rule::enum(PacklistGroup::class)],
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

        return redirect()->route('trips.show', $trip);
    }

    /** Abhaken – per fetch (JSON) oder als normales Formular. */
    public function toggle(Request $request, Trip $trip, PacklistEntry $entry): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $entry);

        $data = $request->validate(['packed' => ['required', 'boolean']]);
        $entry->setPacked($request->boolean('packed'));

        if ($request->expectsJson()) {
            return response()->json([
                'packed' => $entry->packed,
                'progress' => $this->packlists->progress($trip, $request->user()),
            ]);
        }

        return redirect()->route('trips.show', $trip);
    }

    public function destroy(Request $request, Trip $trip, PacklistEntry $entry): RedirectResponse
    {
        Gate::authorize('delete', $entry);

        $name = $entry->load('item')->name();
        $this->packlists->removeEntry($entry);

        return redirect()->route('trips.show', $trip)->with('status', "„{$name}“ entfernt.");
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
