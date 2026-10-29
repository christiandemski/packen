<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\PacklistEntry;
use App\Models\Template;
use App\Models\Trip;
use App\Models\User;
use App\Services\CatalogDuplicates;
use App\Services\PacklistService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->anna = User::factory()->create();
    $this->max = User::factory()->create();
    $this->item = fn (string $name, string $category = 'misc', array $attrs = []) => Item::factory()->for($this->anna, 'owner')->create(['name' => $name, 'category' => $category, ...$attrs]);
});

it('erkennt Doppelungen, aber keine zusammengesetzten Wörter', function () {
    ($this->item)('Zahnbürste', 'toiletries');
    ($this->item)('Zahnbuerste', 'toiletries');
    ($this->item)('Sonnencreme', 'toiletries');
    ($this->item)('Sonencreme', 'toiletries');
    ($this->item)('Ladekabel', 'electronics');
    ($this->item)('Handy-Ladekabel', 'electronics');
    ($this->item)('Jacke', 'outerwear');
    ($this->item)('Regenjacke', 'outerwear');
    ($this->item)('Socken', 'underwear');
    ($this->item)('Wandersocken', 'underwear');
    ($this->item)('Hose', 'bottoms');
    ($this->item)('Rose', 'misc');

    $pairs = app(CatalogDuplicates::class)->pairs($this->anna)
        ->map(fn ($p) => collect([$p['a']->name, $p['b']->name])->sort()->join(' / ').' ('.$p['reason'].')')->sort()->values()->all();

    expect($pairs)->toBe([
        'Handy-Ladekabel / Ladekabel (ein Name steckt im anderen)',
        'Sonencreme / Sonnencreme (ähnlich geschrieben)',
        'Zahnbuerste / Zahnbürste (gleicher Name)',
    ]);
});

it('führt zusammen: Vorlagen, Packlisten und Outfits ziehen mit', function () {
    $keep = ($this->item)('Ladekabel', 'electronics', ['weight_g' => null]);
    $drop = ($this->item)('Handy-Ladekabel', 'electronics', ['weight_g' => 40]);

    $basis = Template::factory()->for($this->anna, 'owner')->create();
    $basis->items()->attach([$keep->id => ['quantity' => 1], $drop->id => ['quantity' => 2]]);
    $city = Template::factory()->for($this->anna, 'owner')->create();
    $city->items()->attach($drop->id, ['quantity' => 1]);

    $trip = Trip::factory()->for($this->anna, 'owner')->create(['start_date' => now()->addWeek(), 'end_date' => now()->addWeeks(2)]);
    app(PacklistService::class)->setTemplates($trip, $this->anna, [$basis->id, $city->id]);
    PacklistEntry::where('item_id', $drop->id)->update(['packed' => true]);

    $this->actingAs($this->anna)->post(route('items.duplicates.merge'), ['keep_id' => $keep->id, 'drop_id' => $drop->id])
        ->assertRedirect(route('items.duplicates'));

    expect(Item::find($drop->id))->toBeNull()
        ->and($keep->fresh()->weight_g)->toBe(40)
        ->and($basis->items()->pluck('quantity', 'items.id')->all())->toBe([$keep->id => 2])
        ->and($city->items()->pluck('items.id')->all())->toBe([$keep->id]);

    $entry = PacklistEntry::where('trip_id', $trip->id)->sole();
    expect($entry)->item_id->toBe($keep->id)->quantity->toBe(2)->packed->toBeTrue();
});

it('merkt sich „Sind verschieden“ und schützt fremde Gegenstände', function () {
    $a = ($this->item)('Ladekabel', 'electronics');
    $b = ($this->item)('Handy-Ladekabel', 'electronics');
    $foreign = Item::factory()->for($this->max, 'owner')->create(['name' => 'Ladekabel']);

    $this->actingAs($this->anna)->get(route('items.index'))->assertSee('1 mögliche Doppelung');
    $this->actingAs($this->anna)->get(route('items.duplicates'))->assertOk()->assertSee('Handy-Ladekabel');

    $this->actingAs($this->anna)->post(route('items.duplicates.merge'), ['keep_id' => $a->id, 'drop_id' => $foreign->id])->assertSessionHasErrors('drop_id');
    $this->actingAs($this->max)->post(route('items.duplicates.dismiss'), ['a_id' => $a->id, 'b_id' => $b->id])->assertSessionHasErrors();

    $this->actingAs($this->anna)->post(route('items.duplicates.dismiss'), ['a_id' => $b->id, 'b_id' => $a->id])->assertRedirect();
    expect(app(CatalogDuplicates::class)->pairs($this->anna))->toBeEmpty()
        ->and(Item::count())->toBe(3);
    $this->actingAs($this->anna)->get(route('items.index'))->assertDontSee('mögliche Doppelung');
});

it('ordnet Kategorien in der eigenen Reihenfolge an – überall', function () {
    $camera = Category::create(['owner_id' => $this->anna->id, 'name' => 'Kamera', 'icon' => 'camera']);
    $docs = ($this->item)('Pass', 'documents');
    $lens = ($this->item)('Objektiv', 'misc', ['category_id' => $camera->id]);
    $shirt = ($this->item)('T-Shirt', 'tops');
    $charger = ($this->item)('Ladekabel', 'electronics', ['pack_last' => true]);

    $this->actingAs($this->anna)->putJson(route('categories.order'), ['order' => ['documents', $camera->key(), 'tops', 'electronics']])->assertOk();
    $this->actingAs($this->anna)->putJson(route('categories.order'), ['order' => ['c:99999']])->assertUnprocessable();

    // Katalog: Dokumente, Kamera, Oberteile …
    $this->actingAs($this->anna)->get(route('items.index'))->assertSeeInOrder(['Dokumente &amp; Geld', 'Kamera', 'Oberteile'], false);

    // Packliste: „Am Abfahrtstag“ bleibt oben, dann in eigener Reihenfolge
    $trip = Trip::factory()->for($this->anna, 'owner')->create();
    foreach ([$docs, $lens, $shirt, $charger] as $item) {
        app(PacklistService::class)->addEntry($trip, $this->anna, ['item_id' => $item->id]);
    }
    $this->actingAs($this->anna)->get(route('trips.show', $trip))->assertSeeInOrder(['Am Abfahrtstag', 'Dokumente &amp; Geld', 'Kamera', 'Kleidung'], false);

    // Andere sehen die Standard-Reihenfolge, Zurücksetzen geht
    expect(DB::table('users')->where('id', $this->max->id)->value('category_order'))->toBeNull();
    $this->actingAs($this->anna)->delete(route('categories.order.reset'))->assertRedirect(route('categories.index'));
    $this->actingAs($this->anna)->get(route('items.index'))->assertSeeInOrder(['Oberteile', 'Dokumente &amp; Geld', 'Kamera'], false);
});
