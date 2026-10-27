<?php

namespace App\Http\Controllers;

use App\Enums\ItemCategory;
use App\Http\Requests\ItemRequest;
use App\Models\Item;
use App\Services\PacklistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $items = Item::accessibleBy($request->user())->withCount('templates')->orderBy('name')->get();

        return view('items.index', [
            'groups' => $items->groupBy(fn (Item $item) => $item->category->value)
                ->sortBy(fn ($group, $category) => ItemCategory::from($category)->position()),
            'total' => $items->count(),
        ]);
    }

    public function create(Request $request): View
    {
        $category = ItemCategory::tryFrom((string) $request->query('kategorie')) ?? ItemCategory::Misc;

        return view('items.form', ['item' => new Item(['category' => $category]), 'containers' => $request->user()->containers()->orderBy('name')->get()]);
    }

    public function store(ItemRequest $request): RedirectResponse
    {
        $item = $request->user()->items()->create($request->validated());

        return redirect()->route('items.index')->with('status', "„{$item->name}“ ist im Katalog.");
    }

    public function edit(Request $request, Item $item): View
    {
        Gate::authorize('update', $item);

        return view('items.form', ['item' => $item->load('templates'), 'containers' => $request->user()->containers()->orderBy('name')->get()]);
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
}
