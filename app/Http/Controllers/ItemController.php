<?php

namespace App\Http\Controllers;

use App\Enums\ItemCategory;
use App\Http\Requests\ItemRequest;
use App\Models\Category;
use App\Models\Item;
use App\Services\PacklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $items = Item::accessibleBy($user)->with('customCategory')->withCount('templates')->orderBy('name')->get();
        $byKey = $items->groupBy(fn (Item $item) => $item->categoryGroup()->key);

        // Kategorien mit Inhalt plus alle eigenen (auch leere – dort tippt man die ersten Dinge ein).
        $sections = Category::choices($user)
            ->filter(fn ($group) => $byKey->has($group->key) || $group->isCustom())
            ->map(fn ($group) => ['group' => $group, 'items' => $byKey->get($group->key, collect())])
            ->values();

        return view('items.index', [
            'sections' => $sections,
            'total' => $items->count(),
            'hasCustom' => $sections->contains(fn ($s) => $s['group']->isCustom()),
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
            return response()->json(['id' => $item->id, 'name' => $item->name, 'url' => route('items.edit', $item), 'rain_protection' => $item->rain_protection], 201);
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
            'categories' => Category::choices($request->user()),
            'containers' => $request->user()->containers()->orderBy('name')->get(),
        ];
    }
}
