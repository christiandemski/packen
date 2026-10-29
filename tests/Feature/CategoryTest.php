<?php

use App\Enums\ItemCategory;
use App\Models\Category;
use App\Models\Item;
use App\Models\PacklistEntry;
use App\Models\Template;
use App\Models\Trip;
use App\Models\User;

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
