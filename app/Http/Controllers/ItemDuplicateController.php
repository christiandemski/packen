<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\CatalogDuplicates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Katalog aufräumen: vermutete Doppelungen zusammenführen oder als verschieden markieren. */
class ItemDuplicateController extends Controller
{
    public function __construct(private readonly CatalogDuplicates $duplicates) {}

    public function index(Request $request): View
    {
        $pairs = $this->duplicates->pairs($request->user());
        $ids = $pairs->flatMap(fn ($p) => [$p['a']->id, $p['b']->id])->unique();

        return view('items.duplicates', [
            'pairs' => $pairs,
            'usage' => Item::whereIn('id', $ids)->withCount(['templates'])->get()->keyBy('id'),
        ]);
    }

    public function merge(Request $request): RedirectResponse
    {
        [$keep, $drop] = $this->pair($request, 'keep_id', 'drop_id');

        $this->duplicates->merge($keep, $drop);

        return redirect()->route('items.duplicates')->with('status', "„{$drop->name}“ ist jetzt „{$keep->name}“.");
    }

    public function dismiss(Request $request): RedirectResponse
    {
        [$a, $b] = $this->pair($request, 'a_id', 'b_id');

        $this->duplicates->dismiss($request->user(), $a, $b);

        return redirect()->route('items.duplicates')->with('status', "Gemerkt: „{$a->name}“ und „{$b->name}“ sind verschieden.");
    }

    /** @return array{Item, Item} beide aus dem eigenen Katalog */
    private function pair(Request $request, string $first, string $second): array
    {
        $own = Rule::exists('items', 'id')->where('owner_id', $request->user()->id);
        $data = $request->validate([
            $first => ['required', 'integer', $own],
            $second => ['required', 'integer', $own, "different:{$first}"],
        ]);

        return [Item::findOrFail($data[$first]), Item::findOrFail($data[$second])];
    }
}
