<?php

namespace App\Http\Controllers;

use App\Enums\TripType;
use App\Models\CustomTripType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Eigene Reisearten */
class CustomTripTypeController extends Controller
{
    public function index(Request $request): View
    {
        return view('trip-types.index', [
            'types' => CustomTripType::accessibleBy($request->user())->withCount('trips')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $request->user()->customTripTypes()->create($this->validated($request, 'create'));

        return redirect()->route('trip-types.index')->with('status', "Reiseart „{$type->name}“ angelegt.");
    }

    public function update(Request $request, CustomTripType $tripType): RedirectResponse
    {
        Gate::authorize('update', $tripType);

        $tripType->update($this->validated($request, 'edit-'.$tripType->id, $tripType));

        return redirect()->route('trip-types.index')->with('status', 'Gespeichert.');
    }

    /** Die Reisen bleiben und stehen danach unter „Sonstiges“. */
    public function destroy(CustomTripType $tripType): RedirectResponse
    {
        Gate::authorize('delete', $tripType);

        $tripType->trips()->update(['type' => TripType::Other->value]);
        $tripType->delete();

        return redirect()->route('trip-types.index')->with('status', "„{$tripType->name}“ gelöscht. Die Reisen stehen jetzt unter „Sonstiges“.");
    }

    private function validated(Request $request, string $bag, ?CustomTripType $type = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validateWithBag($bag, [
            'name' => ['required', 'string', 'max:60', Rule::unique('custom_trip_types', 'name')->where('owner_id', $request->user()->id)->ignore($type)],
            'icon' => ['required', Rule::in(CustomTripType::ICONS)],
            'color' => ['required', Rule::in(array_keys(CustomTripType::COLORS))],
        ], ['name.unique' => 'Eine Reiseart mit diesem Namen gibt es schon.'], ['icon' => 'Icon', 'color' => 'Farbe']);
    }
}
