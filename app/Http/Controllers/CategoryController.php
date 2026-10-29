<?php

namespace App\Http\Controllers;

use App\Models\Category;
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
        return view('categories.index', [
            'categories' => Category::accessibleBy($request->user())->withCount('items')->orderBy('name')->get(),
        ]);
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
