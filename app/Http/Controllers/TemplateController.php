<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use App\Models\Template;
use App\Services\PacklistService;
use App\Services\StarterTemplates;
use App\Support\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function index(Request $request): View
    {
        $undecided = $request->user()->starter_templates_at === null;
        $templates = Template::accessibleBy($request->user())->with('items')->orderBy('name')->get();

        return view('templates.index', [
            'templates' => $templates,
            // Nach „Nein danke“ lässt sich das Angebot über ?start=1 jederzeit wieder öffnen.
            'starters' => $undecided || $request->boolean('start') ? StarterTemplates::definitions() : null,
            'undecided' => $undecided,
            'existingNames' => $templates->pluck('name')->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $template = $request->user()->templates()->create($data);

        return redirect()->route('templates.show', $template)->with('status', 'Vorlage angelegt – jetzt Gegenstände hinzufügen.');
    }

    public function show(Request $request, Template $template): View|RedirectResponse
    {
        Gate::authorize('view', $template);

        // Alte Nummer oder früherer Name in der Adresse → auf die aktuelle umleiten
        if ($request->route()->originalParameter('template') !== $template->slug) {
            return redirect()->route('templates.show', [$template, ...$request->query()], 301);
        }

        $template->load('items.customCategory');
        $available = Item::accessibleBy($request->user())->with('customCategory')
            ->whereNotIn('id', $template->items->modelKeys())
            ->orderBy('name')
            ->get();

        return view('templates.show', [
            'template' => $template,
            'sections' => $this->sections($request, $template),
            'categories' => Category::choices($request->user()),
            'available' => $available,
        ]);
    }

    public function update(Request $request, Template $template): RedirectResponse
    {
        Gate::authorize('update', $template);

        $template->update($this->validated($request, $template));

        return redirect()->route('templates.show', $template)->with('status', 'Gespeichert.');
    }

    public function destroy(Template $template, PacklistService $packlists): RedirectResponse
    {
        Gate::authorize('delete', $template);

        $packlists->detachTemplate($template);
        $template->delete();

        return redirect()->route('templates.index')->with('status', "Vorlage „{$template->name}“ gelöscht. Die Gegenstände bleiben im Katalog.");
    }

    private function validated(Request $request, ?Template $template = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('templates', 'name')->where('owner_id', $request->user()->id)->ignore($template),
            ],
            'description' => ['nullable', 'string', 'max:200'],
        ], ['name.unique' => 'Eine Vorlage mit diesem Namen gibt es schon.']);
    }

    /**
     * Alle Kategorien; ohne Inhalt bleiben sie ausgeblendet, bis man sie zum Eintippen öffnet.
     *
     * @return Collection<int, array{group: Group, items: Collection, hidden: bool}>
     */
    private function sections(Request $request, Template $template)
    {
        $byKey = $template->items->sortBy('name')->groupBy(fn (Item $item) => $item->categoryGroup()->key);

        return Category::choices($request->user())
            ->map(fn ($group) => ['group' => $group, 'items' => $byKey->get($group->key, collect()), 'hidden' => ! $byKey->has($group->key)])
            ->values();
    }
}
