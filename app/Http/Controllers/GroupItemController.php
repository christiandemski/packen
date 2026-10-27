<?php

namespace App\Http\Controllers;

use App\Models\GroupItem;
use App\Models\PacklistEntry;
use App\Models\Trip;
use App\Models\User;
use App\Services\GroupItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Gruppen-Gegenstände sehen und bearbeiten alle Mitglieder einer Reise. */
class GroupItemController extends Controller
{
    public function __construct(private readonly GroupItemService $groupItems) {}

    public function store(Request $request, Trip $trip): RedirectResponse
    {
        Gate::authorize('pack', $trip);

        $data = $request->validate([
            'kind' => ['required', Rule::in([GroupItem::PLANNED, GroupItem::REQUEST])],
            'name' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:200'],
            'assigned_user_id' => ['nullable', 'integer'],
            'source_entry_id' => ['nullable', 'integer'],
        ], attributes: ['name' => 'Gegenstand', 'note' => 'Notiz']);

        if ($data['kind'] === GroupItem::REQUEST) {
            $source = isset($data['source_entry_id']) ? PacklistEntry::where(['id' => $data['source_entry_id'], 'trip_id' => $trip->id])->first() : null;
            $this->groupItems->request($trip, $request->user(), $data['name'], $data['note'] ?? null, $source);
            $status = 'Anfrage an die Gruppe geschickt.';
        } else {
            $assignee = isset($data['assigned_user_id']) ? $trip->members()->whereKey($data['assigned_user_id'])->first() : null;
            $this->groupItems->plan($trip, $request->user(), $data['name'], $data['note'] ?? null, $assignee);
            $status = 'Gemeinsamen Gegenstand angelegt.';
        }

        return redirect()->route('trips.show', $trip)->with('status', $status);
    }

    public function claim(Request $request, Trip $trip, GroupItem $groupItem): RedirectResponse
    {
        $this->authorizeItem($request->user(), $trip, $groupItem);

        if (! $this->groupItems->claim($groupItem, $request->user())) {
            return redirect()->route('trips.show', $trip)->with('status', 'Wurde schon übernommen.');
        }

        return redirect()->route('trips.show', $trip)->with('status', "Danke! „{$groupItem->name}“ steht jetzt auf deiner Liste.");
    }

    public function release(Request $request, Trip $trip, GroupItem $groupItem): RedirectResponse
    {
        $this->authorizeItem($request->user(), $trip, $groupItem);
        abort_unless($this->groupItems->release($groupItem, $request->user()), 403);

        return redirect()->route('trips.show', $trip)->with('status', "„{$groupItem->name}“ wieder abgegeben.");
    }

    public function withdraw(Request $request, Trip $trip, GroupItem $groupItem): RedirectResponse
    {
        $this->authorizeItem($request->user(), $trip, $groupItem);
        abort_unless($this->groupItems->withdraw($groupItem, $request->user()), 403);

        return redirect()->route('trips.show', $trip)->with('status', $groupItem->isRequest() ? 'Anfrage zurückgezogen.' : 'Gestrichen.');
    }

    private function authorizeItem(User $user, Trip $trip, GroupItem $item): void
    {
        Gate::authorize('pack', $trip);
        abort_unless($item->trip_id === $trip->id, 404);
    }
}
