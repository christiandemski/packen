<?php

namespace App\Http\Controllers;

use App\Enums\ItemCategory;
use App\Models\Item;
use App\Models\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TemplateItemController extends Controller
{
    public const MAX_QUANTITY = 99;

    /** Fügt Gegenstände aus dem Katalog hinzu – oder legt einen neuen an und fügt ihn hinzu. */
    public function store(Request $request, Template $template): RedirectResponse
    {
        Gate::authorize('update', $template);
        $user = $request->user();

        if ($request->filled('new_name')) {
            $data = $request->validate([
                'new_name' => ['string', 'max:120'],
                'new_category' => ['required', Rule::enum(ItemCategory::class)],
            ], attributes: ['new_name' => 'Name', 'new_category' => 'Kategorie']);

            $name = trim($data['new_name']);
            $item = Item::accessibleBy($user)->whereRaw('lower(name) = ?', [Str::lower($name)])->first()
                ?? $user->items()->create(['name' => $name, 'category' => $data['new_category']]);

            $template->items()->syncWithoutDetaching([$item->id => ['quantity' => 1]]);

            return back()->with('status', "„{$item->name}“ hinzugefügt.");
        }

        $data = $request->validate([
            'item_ids' => ['required', 'array'],
            'item_ids.*' => ['integer'],
        ], ['item_ids.required' => 'Bitte mindestens einen Gegenstand auswählen.']);

        // Nur eigene Gegenstände – fremde IDs werden stillschweigend ignoriert.
        $ids = Item::accessibleBy($user)->whereIn('id', $data['item_ids'])->pluck('id');

        $template->items()->syncWithoutDetaching($ids->mapWithKeys(fn ($id) => [$id => ['quantity' => 1]])->all());

        return back()->with('status', $ids->count() === 1 ? '1 Gegenstand hinzugefügt.' : "{$ids->count()} Gegenstände hinzugefügt.");
    }

    public function update(Request $request, Template $template, Item $item): RedirectResponse
    {
        Gate::authorize('update', $template);
        abort_unless($template->items()->whereKey($item->id)->exists(), 404);

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY]]);

        $template->items()->updateExistingPivot($item->id, ['quantity' => $data['quantity']]);

        return back();
    }

    public function destroy(Template $template, Item $item): RedirectResponse
    {
        Gate::authorize('update', $template);

        $template->items()->detach($item->id);

        return back()->with('status', "„{$item->name}“ entfernt.");
    }
}
