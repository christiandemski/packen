<?php

use App\Enums\ItemCategory;
use App\Models\Category;
use App\Models\Item;
use App\Models\PacklistEntry;
use App\Models\Template;
use App\Models\Trip;
use App\Models\User;
use App\Services\PacklistService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->anna = User::factory()->create();
    $this->max = User::factory()->create();
});

it('legt eigene Kategorien mit Icon an', function () {
    $this->actingAs($this->anna)->post(route('categories.store'), ['name' => ' Kamera ', 'icon' => 'camera'])
        ->assertRedirect(route('items.index'));

    expect(Category::sole())->owner_id->toBe($this->anna->id)->name->toBe('Kamera')->icon->toBe('camera');

    $this->actingAs($this->anna)->post(route('categories.store'), ['name' => 'Kamera', 'icon' => 'camera'])
        ->assertSessionHasErrorsIn('create', 'name');
    $this->actingAs($this->anna)->post(route('categories.store'), ['name' => 'Hund', 'icon' => 'gibtsnicht'])
        ->assertSessionHasErrorsIn('create', 'icon');
});

it('ordnet Gegenstände einer eigenen Kategorie zu und zeigt sie im Katalog', function () {
    $camera = Category::create(['owner_id' => $this->anna->id, 'name' => 'Kamera', 'icon' => 'camera']);

    $this->actingAs($this->anna)->post(route('items.store'), ['name' => 'Objektiv', 'category' => $camera->key()])
        ->assertRedirect(route('items.index'));

    expect(Item::sole())->category_id->toBe($camera->id)->category->toBe(ItemCategory::Misc)->is_clothing->toBeFalse();

    // Leere eigene Kategorien erscheinen trotzdem (zum Eintippen)
    Category::create(['owner_id' => $this->anna->id, 'name' => 'Hund', 'icon' => 'heart']);
    $this->actingAs($this->anna)->get(route('items.index'))->assertOk()->assertSee('Kamera')->assertSee('Objektiv')->assertSee('Hund');

    // Leere feste Kategorien gibt es zum Einblenden und Füllen
    $this->actingAs($this->anna)->get(route('items.index'))->assertSeeInOrder(['Weitere Kategorie füllen', 'Oberteile', 'Hygiene']);

    // Zurück auf eine feste Kategorie
    $this->actingAs($this->anna)->put(route('items.update', Item::sole()), ['name' => 'Objektiv', 'category' => 'electronics']);
    expect(Item::sole())->category_id->toBeNull()->category->toBe(ItemCategory::Electronics);
});

it('zeigt eigene Kategorien auf allen Seiten zur Auswahl an', function () {
    $camera = Category::create(['owner_id' => $this->anna->id, 'name' => 'Kamera', 'icon' => 'camera']);
    $item = Item::factory()->for($this->anna, 'owner')->create(['name' => 'Objektiv', 'category' => 'misc', 'category_id' => $camera->id]);
    $template = Template::factory()->for($this->anna, 'owner')->create();
    $template->items()->attach($item->id, ['quantity' => 1]);

    $this->actingAs($this->anna)->get(route('categories.index'))->assertOk()->assertSee('Kamera')->assertSee('1 Gegenstand');
    $this->actingAs($this->anna)->get(route('items.create', ['kategorie' => $camera->key()]))->assertOk()->assertSee('value="c:'.$camera->id.'"', false);
    $this->actingAs($this->anna)->get(route('items.edit', $item))->assertOk()->assertSee('Kamera');
    $this->actingAs($this->anna)->get(route('templates.show', $template))->assertOk()->assertSeeInOrder(['Kamera', 'Objektiv']);

    // Neu anlegen aus der Vorlage heraus, direkt in die eigene Kategorie
    $this->actingAs($this->anna)->post(route('templates.items.store', $template), ['new_name' => 'Stativ', 'new_category' => $camera->key()]);
    expect(Item::where('name', 'Stativ')->sole()->category_id)->toBe($camera->id);
});

it('lässt keine fremden Kategorien zu', function () {
    $foreign = Category::create(['owner_id' => $this->max->id, 'name' => 'Angeln', 'icon' => 'waves']);

    $this->actingAs($this->anna)->post(route('items.store'), ['name' => 'Rute', 'category' => $foreign->key()])
        ->assertSessionHasErrors('category');
    $this->actingAs($this->anna)->put(route('categories.update', $foreign), ['name' => 'Meins', 'icon' => 'star'])->assertForbidden();
    $this->actingAs($this->anna)->delete(route('categories.destroy', $foreign))->assertForbidden();
    expect(Item::count())->toBe(0)->and($foreign->fresh())->not->toBeNull();
});

it('legt Gegenstände per Schnelleingabe an (JSON)', function () {
    $this->actingAs($this->anna)->postJson(route('items.store'), ['name' => 'Regenschirm', 'category' => 'gear'])
        ->assertCreated()
        ->assertJson(['name' => 'Regenschirm', 'rain_protection' => true]);

    $this->actingAs($this->anna)->postJson(route('items.store'), ['name' => 'Regenschirm', 'category' => 'gear'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('löscht Kategorien, ohne die Gegenstände zu verlieren', function () {
    $camera = Category::create(['owner_id' => $this->anna->id, 'name' => 'Kamera', 'icon' => 'camera']);
    $item = Item::factory()->for($this->anna, 'owner')->create(['name' => 'Akku', 'category' => 'misc', 'category_id' => $camera->id]);

    $this->actingAs($this->anna)->delete(route('categories.destroy', $camera))->assertRedirect(route('categories.index'));

    expect($item->fresh())->category_id->toBeNull()->category->toBe(ItemCategory::Misc);
});

it('bekommt in der Packliste eine eigene Karte', function () {
    $camera = Category::create(['owner_id' => $this->anna->id, 'name' => 'Kamera', 'icon' => 'camera']);
    $item = Item::factory()->for($this->anna, 'owner')->create(['name' => 'Objektiv', 'category' => 'misc', 'category_id' => $camera->id]);
    $template = Template::factory()->for($this->anna, 'owner')->create();
    $template->items()->attach($item->id, ['quantity' => 1]);
    $trip = Trip::factory()->for($this->anna, 'owner')->create();

    $this->actingAs($this->anna)->put(route('packlist.templates', $trip), ['template_ids' => [$template->id]]);

    expect(PacklistEntry::sole()->packlistGroup())->key->toBe($camera->key())->label->toBe('Kamera');

    // Freitext direkt in die eigene Karte
    $this->actingAs($this->anna)->post(route('packlist.store', $trip), ['custom_name' => 'Speicherkarte', 'group' => $camera->key()]);
    expect(PacklistEntry::where('custom_name', 'Speicherkarte')->sole()->packlistGroup()->label)->toBe('Kamera');

    $this->actingAs($this->anna)->get(route('trips.show', $trip))->assertOk()->assertSeeInOrder(['Kamera', 'Objektiv', 'Speicherkarte']);
});

it('benennt feste Kategorien um und blendet sie aus – nur für mich', function () {
    $soap = Item::factory()->for($this->anna, 'owner')->create(['name' => 'Seife', 'category' => 'toiletries']);
    $trip = Trip::factory()->for($this->anna, 'owner')->create();
    $trip->addMember($this->max);
    app(PacklistService::class)->addEntry($trip, $this->anna, ['item_id' => $soap->id]);

    $this->actingAs($this->anna)->put(route('categories.builtin.update', 'toiletries'), ['label' => 'Kulturbeutel'])->assertRedirect(route('categories.index'));
    $this->actingAs($this->anna)->put(route('categories.builtin.update', 'food'), ['label' => '', 'hidden' => '1']);
    $this->actingAs($this->anna)->put(route('categories.builtin.update', 'misc'), ['hidden' => '1']); // geht nicht

    $this->actingAs($this->anna)->get(route('items.index'))->assertSee('Kulturbeutel')->assertDontSee('Hygiene')->assertDontSee('Essen &amp; Küche', false);
    $this->actingAs($this->anna)->get(route('items.create'))->assertDontSee('value="food"', false)->assertSee('value="misc"', false);
    $this->actingAs($this->anna)->get(route('trips.show', $trip))->assertSee('Kulturbeutel');
    $this->actingAs($this->anna)->get(route('categories.index'))->assertSee('statt „Hygiene“')->assertSee('ausgeblendet');

    // Andere sehen weiter die Standardnamen
    $this->actingAs($this->max)->get(route('items.index'))->assertSee('Essen &amp; Küche', false);

    // Bestehende Gegenstände in ausgeblendeten Kategorien bleiben gültig und sichtbar
    $bread = Item::factory()->for($this->anna, 'owner')->create(['name' => 'Brot', 'category' => 'food']);
    $this->actingAs($this->anna)->get(route('items.index'))->assertSee('Brot')->assertSee('Essen &amp; Küche', false);
    $this->actingAs($this->anna)->get(route('items.edit', $bread))->assertSee('value="food"', false);
    $this->actingAs($this->anna)->put(route('items.update', $bread), ['name' => 'Brot', 'category' => 'food'])->assertSessionHasNoErrors();

    // Leerer Name = Standard, nicht mehr ausgeblendet = Einstellung weg
    $this->actingAs($this->anna)->put(route('categories.builtin.update', 'toiletries'), ['label' => '']);
    $this->actingAs($this->anna)->put(route('categories.builtin.update', 'food'), []);
    expect(DB::table('category_settings')->count())->toBe(0);
});

it('verschiebt mehrere Gegenstände auf einmal in eine andere Kategorie', function () {
    $camera = Category::create(['owner_id' => $this->anna->id, 'name' => 'Kamera', 'icon' => 'camera']);
    $lens = Item::factory()->for($this->anna, 'owner')->create(['name' => 'Objektiv', 'category' => 'electronics']);
    $sd = Item::factory()->for($this->anna, 'owner')->create(['name' => 'SD-Karte', 'category' => 'electronics']);
    $cable = Item::factory()->for($this->anna, 'owner')->create(['name' => 'Ladekabel', 'category' => 'electronics']);

    $this->actingAs($this->anna)->get(route('items.index'))->assertOk()->assertSee('Mehrere verschieben');

    $this->actingAs($this->anna)->post(route('items.move'), ['items' => [$lens->id, $sd->id], 'category' => $camera->key()])
        ->assertRedirect(route('items.index'))
        ->assertSessionHas('status', '2 Gegenstände nach „Kamera“ verschoben.');

    expect($lens->fresh())->category_id->toBe($camera->id)->category->toBe(ItemCategory::Misc)
        ->and($sd->fresh()->category_id)->toBe($camera->id)
        ->and($cable->fresh()->category)->toBe(ItemCategory::Electronics);

    // In eine feste Kleidungskategorie – „is_clothing“ zieht mit
    $this->actingAs($this->anna)->post(route('items.move'), ['items' => [$lens->id], 'category' => 'tops']);
    expect($lens->fresh())->category_id->toBeNull()->category->toBe(ItemCategory::Tops)->is_clothing->toBeTrue();

    // Fremde Gegenstände und unbekannte Kategorien gehen nicht
    $foreign = Item::factory()->for($this->max, 'owner')->create(['category' => 'electronics']);
    $this->actingAs($this->anna)->post(route('items.move'), ['items' => [$foreign->id], 'category' => 'tops'])->assertSessionHasErrors('items.0');
    $this->actingAs($this->anna)->post(route('items.move'), ['items' => [$sd->id], 'category' => 'gibtsnicht'])->assertSessionHasErrors('category');
    expect($foreign->fresh()->category)->toBe(ItemCategory::Electronics);
});

it('bietet im Katalog über „Neu“ Gegenstand, Kategorie und Behältnis an', function () {
    Item::factory()->for($this->anna, 'owner')->create();

    $this->actingAs($this->anna)->get(route('items.index'))->assertOk()
        ->assertSee(route('items.create'))
        ->assertSee(route('categories.index', ['neu' => 1]).'#neu', false)
        ->assertSee(route('containers.index', ['neu' => 1]).'#neu', false);

    $this->actingAs($this->anna)->get(route('categories.index', ['neu' => 1]))->assertOk()->assertSee('id="neu"', false);
    $this->actingAs($this->anna)->get(route('containers.index', ['neu' => 1]))->assertOk()->assertSee('id="neu"', false);
});
