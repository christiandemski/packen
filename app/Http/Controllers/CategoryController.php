<?php

namespace App\Http\Controllers;

use App\Enums\ItemCategory;
use App\Models\Category;
use App\Support\CategorySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Eigene Kategorien im Katalog */
class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('categories.index', [
            'order' => Category::choices($user),
            'categories' => Category::accessibleBy($user)->withCount('items')->orderBy('name')->get(),
            'builtin' => collect(ItemCategory::cases())->map(fn (ItemCategory $c) => [
                'category' => $c,
                'hidden' => in_array($c->value, CategorySettings::hidden($user), true),
                'renamed' => CategorySettings::label($c, $user) !== null,
            ]),
        ]);
    }

    /** Eigene Reihenfolge (per Ziehen); gilt für Katalog, Vorlagen und Packliste. */
    public function order(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'order' => ['present', 'array'],
            'order.*' => ['string', Rule::in(Category::choices($user, withHidden: true)->pluck('key'))],
        ]);

        // Ausgeblendete behalten ihren Platz hinten
        $hidden = Category::choices($user, withHidden: true)->pluck('key')->diff($data['order'])->values()->all();
        CategorySettings::saveOrder($user, [...$data['order'], ...$hidden]);

        return response()->json(['saved' => true]);
    }

    /** Zurück zur Standard-Reihenfolge */
    public function resetOrder(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['category_order' => null])->save();

        return redirect()->route('categories.index')->with('status', 'Standard-Reihenfolge wiederhergestellt.');
    }

    /** Feste Kategorie umbenennen oder ausblenden (nur für mich). */
    public function updateBuiltin(Request $request, string $category): RedirectResponse
    {
        $case = ItemCategory::tryFrom($category) ?? abort(404);
        $request->merge(['label' => trim((string) $request->input('label'))]);
        $data = $request->validateWithBag('builtin-'.$case->value, ['label' => ['nullable', 'string', 'max:60']], attributes: ['label' => 'Name']);

        CategorySettings::save($request->user(), $case, $data['label'] ?? null, $request->boolean('hidden'));

        return redirect()->route('categories.index')->with('status', 'Gespeichert.');
    }

    public function store(Request $request): RedirectResponse
    {
        $category = $request->user()->categories()->create($this->validated($request, 'create'));

        return redirect()->route('items.index')->with('status', "Kategorie „{$category->name}“ angelegt – tipp unten im Kasten gleich die ersten Dinge ein.");
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        $category->update($this->validated($request, 'edit-'.$category->id, $category));

        return redirect()->route('categories.index')->with('status', 'Gespeichert.');
    }

    /** Die Gegenstände bleiben im Katalog und stehen danach unter „Sonstiges“. */
    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        $category->delete();

        return redirect()->route('categories.index')->with('status', "„{$category->name}“ gelöscht. Ihre Gegenstände stehen jetzt unter „Sonstiges“.");
    }

    private function validated(Request $request, string $bag, ?Category $category = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validateWithBag($bag, [
            'name' => ['required', 'string', 'max:60', Rule::unique('categories', 'name')->where('owner_id', $request->user()->id)->ignore($category)],
            'icon' => ['required', Rule::in(Category::ICONS)],
        ], ['name.unique' => 'Eine Kategorie mit diesem Namen gibt es schon.'], ['icon' => 'Icon']);
    }
}
