<?php

namespace App\Http\Controllers;

use App\Enums\ItemCategory;
use App\Http\Requests\ItemRequest;
use App\Models\Category;
use App\Models\Item;
use App\Services\CatalogDuplicates;
use App\Services\PacklistService;
use App\Support\CategorySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request, CatalogDuplicates $duplicates): View
    {
        $user = $request->user();
        $items = Item::accessibleBy($user)->with('customCategory')->withCount('templates')->orderBy('name')->get();
        $byKey = $items->groupBy(fn (Item $item) => $item->categoryGroup()->key);

        // Alle Kategorien; leere feste blendet die Seite erst auf Wunsch ein (eigene immer – die legt man ja zum Füllen an).
        $sections = Category::choices($user, withHidden: true)
            ->reject(fn ($group) => in_array($group->key, CategorySettings::hidden($user), true) && ! $byKey->has($group->key))
            ->map(fn ($group) => [
                'group' => $group,
                'items' => $byKey->get($group->key, collect()),
                'hidden' => ! $byKey->has($group->key) && ! $group->isCustom(),
            ])
            ->values();

        return view('items.index', [
            'sections' => $sections,
            'targets' => Category::choices($user),
            'total' => $items->count(),
            'duplicates' => $items->count() > 1 ? $duplicates->pairs($user)->count() : 0,
        ]);
    }

    public function create(Request $request): View
    {
        $item = new Item(Category::columnsFor((string) $request->query('kategorie', ItemCategory::Misc->value), $request->user()));

        return view('items.form', $this->formData($request, $item));
    }

    public function store(ItemRequest $request): RedirectResponse|JsonResponse
    {
        $item = $request->user()->items()->create($request->validated());

        // Schnelleingabe im Katalog
        if ($request->expectsJson()) {
            return response()->json([
                'id' => $item->id,
                'name' => $item->name,
                'url' => route('items.edit', $item),
                'rain_protection' => $item->rain_protection,
                'badges' => collect($item->weatherNeeds())->map->badge()->all(),
            ], 201);
        }

        return redirect()->route('items.index')->with('status', "„{$item->name}“ ist im Katalog.");
    }

    public function edit(Request $request, Item $item): View
    {
        Gate::authorize('update', $item);

        return view('items.form', $this->formData($request, $item->load('templates')));
    }

    public function update(ItemRequest $request, Item $item, PacklistService $packlists): RedirectResponse
    {
        Gate::authorize('update', $item);

        $item->update($request->validated());

        if ($item->wasChanged('per_day')) {
            $item->templates->each(fn ($template) => $packlists->syncTemplate($template));
        }

        return redirect()->route('items.index')->with('status', 'Gespeichert.');
    }

    /** Mehrere Gegenstände auf einmal in eine andere Kategorie (aus dem „…“ einer Katalog-Karte). */
    public function move(Request $request): RedirectResponse
    {
        $user = $request->user();
        $groups = Category::choices($user, withHidden: true);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['integer', Rule::exists('items', 'id')->where('owner_id', $user->id)],
            'category' => ['required', 'string', Rule::in($groups->pluck('key'))],
        ], ['items.required' => 'Wähl mindestens einen Gegenstand aus.'], ['category' => 'Kategorie']);

        // Einzeln speichern, damit „is_clothing“ mitzieht
        $items = Item::accessibleBy($user)->whereKey($data['items'])->get();
        $items->each->update(Category::columnsFor($data['category'], $user));
        $count = $items->count();
        $label = $groups->firstWhere('key', $data['category'])->label;

        return redirect()->route('items.index')->with('status', ($count === 1 ? '1 Gegenstand' : "{$count} Gegenstände")." nach „{$label}“ verschoben.");
    }

    public function destroy(Item $item): RedirectResponse
    {
        Gate::authorize('delete', $item);

        $item->delete();

        return redirect()->route('items.index')->with('status', "„{$item->name}“ wurde gelöscht.");
    }

    private function formData(Request $request, Item $item): array
    {
        return [
            'item' => $item,
            'categories' => Category::choices($request->user(), withHidden: true)
                ->filter(fn ($group) => ! in_array($group->key, CategorySettings::hidden($request->user()), true) || $group->key === $item->categoryGroup()->key)
                ->values(),
            'containers' => $request->user()->containers()->orderBy('name')->get(),
        ];
    }
}
