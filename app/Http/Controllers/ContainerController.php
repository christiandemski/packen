<?php

namespace App\Http\Controllers;

use App\Enums\ContainerKind;
use App\Models\Container;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContainerController extends Controller
{
    public function index(Request $request): View
    {
        return view('containers.index', [
            'containers' => Container::accessibleBy($request->user())->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $container = $request->user()->containers()->create($this->validated($request, 'create'));

        return redirect()->route('containers.index')->with('status', "„{$container->name}“ angelegt.");
    }

    public function update(Request $request, Container $container): RedirectResponse
    {
        Gate::authorize('update', $container);

        $container->update($this->validated($request, 'edit-'.$container->id, $container));

        return redirect()->route('containers.index')->with('status', 'Gespeichert.');
    }

    public function destroy(Container $container): RedirectResponse
    {
        Gate::authorize('delete', $container);

        $container->delete();

        return redirect()->route('containers.index')->with('status', "„{$container->name}“ gelöscht. Zugeordnete Einträge sind jetzt ohne Behältnis.");
    }

    private function validated(Request $request, string $bag, ?Container $container = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        $data = $request->validateWithBag($bag, [
            'name' => ['required', 'string', 'max:80', Rule::unique('containers', 'name')->where('owner_id', $request->user()->id)->ignore($container)],
            'kind' => ['required', Rule::enum(ContainerKind::class)],
            'capacity_l' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'max_weight_kg' => ['nullable', 'numeric', 'min:0.1', 'max:500'],
            'empty_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], ['name.unique' => 'Ein Behältnis mit diesem Namen gibt es schon.'], [
            'kind' => 'Art', 'capacity_l' => 'Volumen', 'max_weight_kg' => 'Maximalgewicht', 'empty_weight_kg' => 'Leergewicht',
        ]);

        // Eingabe in kg (mit Komma), gespeichert in Gramm.
        return [
            'name' => $data['name'],
            'kind' => $data['kind'],
            'capacity_l' => $data['capacity_l'] ?? null,
            'max_weight_g' => isset($data['max_weight_kg']) ? (int) round($data['max_weight_kg'] * 1000) : null,
            'empty_weight_g' => isset($data['empty_weight_kg']) ? (int) round($data['empty_weight_kg'] * 1000) : null,
        ];
    }
}
