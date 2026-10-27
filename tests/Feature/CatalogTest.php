<?php

use App\Enums\ItemCategory;
use App\Models\Item;
use App\Models\Template;
use App\Models\User;

beforeEach(function () {
    $this->anna = User::factory()->create();
    $this->max = User::factory()->create();
});

it('legt Gegenstände an und leitet „Kleidung“ aus der Kategorie ab', function () {
    $this->actingAs($this->anna)->post(route('items.store'), [
        'name' => ' Wandersocken ',
        'category' => 'underwear',
        'weight_g' => 60,
        'per_day' => '1',
    ])->assertRedirect(route('items.index'));

    $item = Item::sole();
    expect($item)
        ->owner_id->toBe($this->anna->id)
        ->name->toBe('Wandersocken')
        ->category->toBe(ItemCategory::Underwear)
        ->is_clothing->toBeTrue()
        ->per_day->toBeTrue()
        ->pack_last->toBeFalse();

    $this->actingAs($this->anna)->put(route('items.update', $item), ['name' => 'Ladekabel', 'category' => 'electronics']);
    expect($item->fresh())->is_clothing->toBeFalse()->per_day->toBeFalse();
});

it('verhindert doppelte Namen nur innerhalb des eigenen Katalogs', function () {
    Item::factory()->for($this->anna, 'owner')->create(['name' => 'Zelt']);

    $this->actingAs($this->anna)->post(route('items.store'), ['name' => 'Zelt', 'category' => 'sleep'])
        ->assertSessionHasErrors('name');
    $this->actingAs($this->max)->post(route('items.store'), ['name' => 'Zelt', 'category' => 'sleep'])
        ->assertSessionHasNoErrors();
});

it('prüft Kategorie und Gewicht', function () {
    $this->actingAs($this->anna)->post(route('items.store'), ['name' => 'X', 'category' => 'quatsch', 'weight_g' => -5])
        ->assertSessionHasErrors(['category', 'weight_g']);
});

it('zeigt nur den eigenen Katalog', function () {
    Item::factory()->for($this->anna, 'owner')->create(['name' => 'Annas Föhn']);
    Item::factory()->for($this->max, 'owner')->create(['name' => 'Max’ Rasierer']);

    $this->actingAs($this->anna)->get(route('items.index'))
        ->assertOk()
        ->assertSee('Annas Föhn')
        ->assertDontSee('Max’ Rasierer');
});

it('verbietet den Zugriff auf fremde Gegenstände', function () {
    $item = Item::factory()->for($this->max, 'owner')->create();

    $this->actingAs($this->anna)->get(route('items.edit', $item))->assertForbidden();
    $this->actingAs($this->anna)->put(route('items.update', $item), ['name' => 'Gekapert', 'category' => 'misc'])->assertForbidden();
    $this->actingAs($this->anna)->delete(route('items.destroy', $item))->assertForbidden();

    expect($item->fresh()->name)->not->toBe('Gekapert');
});

it('entfernt gelöschte Gegenstände auch aus Vorlagen', function () {
    $item = Item::factory()->for($this->anna, 'owner')->create();
    $template = Template::factory()->for($this->anna, 'owner')->create();
    $template->items()->attach($item, ['quantity' => 2]);

    $this->actingAs($this->anna)->get(route('items.edit', $item))->assertSee($template->name);
    $this->actingAs($this->anna)->delete(route('items.destroy', $item))->assertRedirect(route('items.index'));

    expect($template->items()->count())->toBe(0);
});

it('zeigt die Formulare an', function () {
    $item = Item::factory()->for($this->anna, 'owner')->create();

    $this->actingAs($this->anna)->get(route('items.create', ['kategorie' => 'shoes']))->assertOk();
    $this->actingAs($this->anna)->get(route('items.edit', $item))->assertOk()->assertSee($item->name);
});
