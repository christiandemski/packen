<?php

use App\Models\Item;
use App\Models\Template;
use App\Models\User;
use App\Services\StarterTemplates;

beforeEach(function () {
    $this->anna = User::factory()->create();
    $this->max = User::factory()->create();
});

it('legt Vorlagen an und benennt sie um', function () {
    $this->actingAs($this->anna)->post(route('templates.store'), ['name' => 'Winter'])->assertRedirect();
    $template = Template::sole();
    expect($template->owner_id)->toBe($this->anna->id);

    $this->actingAs($this->anna)->put(route('templates.update', $template), ['name' => 'Skiurlaub', 'description' => 'Mit Schnee']);
    expect($template->fresh())->name->toBe('Skiurlaub')->description->toBe('Mit Schnee');

    $this->actingAs($this->anna)->post(route('templates.store'), ['name' => 'Skiurlaub'])->assertSessionHasErrors('name');
});

it('fügt eigene Gegenstände hinzu und ignoriert fremde', function () {
    $template = Template::factory()->for($this->anna, 'owner')->create();
    $own = Item::factory()->for($this->anna, 'owner')->create();
    $foreign = Item::factory()->for($this->max, 'owner')->create();

    $this->actingAs($this->anna)->post(route('templates.items.store', $template), ['item_ids' => [$own->id, $foreign->id]]);

    expect($template->items()->pluck('items.id')->all())->toBe([$own->id]);
});

it('legt beim Hinzufügen neue Gegenstände im Katalog an und nutzt vorhandene', function () {
    $template = Template::factory()->for($this->anna, 'owner')->create();
    $existing = Item::factory()->for($this->anna, 'owner')->create(['name' => 'Sonnencreme']);

    $this->actingAs($this->anna)->post(route('templates.items.store', $template), ['new_name' => 'sonnencreme', 'new_category' => 'toiletries']);
    $this->actingAs($this->anna)->post(route('templates.items.store', $template), ['new_name' => 'Strandtuch', 'new_category' => 'gear']);

    expect($this->anna->items()->count())->toBe(2)
        ->and($template->items()->pluck('name')->sort()->values()->all())->toBe(['Sonnencreme', 'Strandtuch'])
        ->and($template->items()->whereKey($existing->id)->exists())->toBeTrue();
});

it('ändert Mengen im erlaubten Bereich', function () {
    $template = Template::factory()->for($this->anna, 'owner')->create();
    $item = Item::factory()->for($this->anna, 'owner')->create();
    $template->items()->attach($item, ['quantity' => 1]);

    $this->actingAs($this->anna)->patch(route('templates.items.update', [$template, $item]), ['quantity' => 3]);
    expect($template->items()->first()->pivot->quantity)->toBe(3);

    $this->actingAs($this->anna)->patch(route('templates.items.update', [$template, $item]), ['quantity' => 0])->assertSessionHasErrors('quantity');
    $this->actingAs($this->anna)->patch(route('templates.items.update', [$template, $item]), ['quantity' => 100])->assertSessionHasErrors('quantity');
});

it('entfernt Gegenstände aus der Vorlage, nicht aus dem Katalog', function () {
    $template = Template::factory()->for($this->anna, 'owner')->create();
    $item = Item::factory()->for($this->anna, 'owner')->create();
    $template->items()->attach($item);

    $this->actingAs($this->anna)->delete(route('templates.items.destroy', [$template, $item]));

    expect($template->items()->count())->toBe(0)->and($item->fresh())->not->toBeNull();
});

it('verbietet den Zugriff auf fremde Vorlagen', function () {
    $template = Template::factory()->for($this->max, 'owner')->create(['name' => 'Max’ Geheimvorlage']);
    $item = Item::factory()->for($this->max, 'owner')->create();
    $template->items()->attach($item);
    $annasItem = Item::factory()->for($this->anna, 'owner')->create();

    $this->actingAs($this->anna)->get(route('templates.index'))->assertDontSee('Max’ Geheimvorlage');
    // Über die Adresse (je Person) nicht auffindbar, über die alte Nummer gesperrt
    $this->actingAs($this->anna)->get(route('templates.show', $template))->assertNotFound();
    $this->actingAs($this->anna)->put(route('templates.update', $template), ['name' => 'X'])->assertNotFound();
    $this->actingAs($this->anna)->delete(route('templates.destroy', $template))->assertNotFound();
    $this->actingAs($this->anna)->post(route('templates.items.store', $template), ['item_ids' => [$annasItem->id]])->assertNotFound();
    $this->actingAs($this->anna)->patch(route('templates.items.update', [$template, $item]), ['quantity' => 5])->assertNotFound();
    $this->actingAs($this->anna)->delete(route('templates.items.destroy', [$template, $item]))->assertNotFound();

    $this->actingAs($this->anna)->get('/vorlagen/'.$template->id)->assertForbidden();
    $this->actingAs($this->anna)->delete('/vorlagen/'.$template->id)->assertForbidden();

    expect($template->items()->count())->toBe(1);
});

it('löscht Vorlagen, behält aber die Gegenstände', function () {
    $template = Template::factory()->for($this->anna, 'owner')->create();
    $item = Item::factory()->for($this->anna, 'owner')->create();
    $template->items()->attach($item);

    $this->actingAs($this->anna)->delete(route('templates.destroy', $template))->assertRedirect(route('templates.index'));

    expect(Template::find($template->id))->toBeNull()->and($item->fresh())->not->toBeNull();
});

it('bietet Start-Vorlagen an und übernimmt die ausgewählten', function () {
    $this->actingAs($this->anna)->get(route('home'))->assertSee('Start-Vorlagen übernehmen?');
    $this->actingAs($this->anna)->get(route('templates.index'))->assertSee('Mit Start-Vorlagen loslegen?');

    $this->actingAs($this->anna)->post(route('starter-templates.store'), ['templates' => ['Basis', 'Wandern']])
        ->assertRedirect(route('templates.index'));

    expect($this->anna->templates()->pluck('name')->sort()->values()->all())->toBe(['Basis', 'Wandern'])
        ->and($this->anna->fresh()->starter_templates_at)->not->toBeNull()
        ->and($this->anna->items()->where('name', 'Socken')->first())->per_day->toBeTrue()->is_clothing->toBeTrue()
        ->and($this->anna->items()->where('name', 'Zahnbürste')->first()->pack_last)->toBeTrue();

    $this->actingAs($this->anna)->get(route('home'))->assertDontSee('Start-Vorlagen übernehmen?');
});

it('führt gleiche Gegenstände der Start-Vorlagen zusammen', function () {
    Item::factory()->for($this->anna, 'owner')->create(['name' => 'Trinkflasche']);

    app(StarterTemplates::class)->install($this->anna, ['Städtetrip', 'Wandern', 'Bikepacking']);

    expect($this->anna->items()->where('name', 'Trinkflasche')->count())->toBe(1)
        ->and($this->anna->items()->where('name', 'Stirnlampe')->count())->toBe(1);

    $bikepacking = $this->anna->templates()->where('name', 'Bikepacking')->first();
    expect($bikepacking->items()->where('name', 'Trinkflasche')->first()->pivot->quantity)->toBe(2);
});

it('lässt Start-Vorlagen ablehnen und nimmt keine erfundenen', function () {
    $this->actingAs($this->anna)->post(route('starter-templates.store'), ['templates' => ['Mondreise']])->assertSessionHasErrors('templates.0');

    $this->actingAs($this->anna)->delete(route('starter-templates.dismiss'));

    expect($this->anna->fresh()->starter_templates_at)->not->toBeNull()
        ->and($this->anna->templates()->count())->toBe(0);
});

it('bietet abgelehnte Start-Vorlagen auf Wunsch erneut an', function () {
    $this->actingAs($this->anna)->delete(route('starter-templates.dismiss'));
    $this->actingAs($this->anna)->get(route('templates.index'))
        ->assertDontSee('Mit Start-Vorlagen loslegen?')
        ->assertSee('Start-Vorlagen anzeigen');

    $this->actingAs($this->anna)->get(route('templates.index', ['start' => 1]))
        ->assertSee('Mit Start-Vorlagen loslegen?')
        ->assertDontSee('Nein danke');

    $this->actingAs($this->anna)->post(route('starter-templates.store'), ['templates' => ['Basis']]);
    expect($this->anna->templates()->pluck('name')->all())->toBe(['Basis']);

    $this->actingAs($this->anna)->get(route('templates.index', ['start' => 1]))->assertSee('hast du schon');
});

it('zeigt Übersicht und Detailseite an', function () {
    app(StarterTemplates::class)->install($this->anna, ['Basis']);
    $template = $this->anna->templates()->first();

    $this->actingAs($this->anna)->get(route('templates.index'))->assertOk()->assertSee('Basis');
    $this->actingAs($this->anna)->get(route('templates.show', $template))->assertOk()->assertSee('Zahnbürste');
});

it('hat lesbare Adressen je Person, die auch nach dem Umbenennen funktionieren', function () {
    $anna = User::factory()->create();
    $max = User::factory()->create();
    $first = Template::factory()->for($anna, 'owner')->create(['name' => 'Städtetrip']);
    $second = Template::factory()->for($anna, 'owner')->create(['name' => 'Staedtetrip']);
    $maxs = Template::factory()->for($max, 'owner')->create(['name' => 'Städtetrip']);

    expect([$first->slug, $second->slug, $maxs->slug])->toBe(['staedtetrip', 'staedtetrip-2', 'staedtetrip'])
        ->and(route('templates.show', $first))->toEndWith('/vorlagen/staedtetrip');

    // Jede Person landet bei ihrer eigenen Vorlage
    $this->actingAs($anna)->get('/vorlagen/staedtetrip')->assertOk()->assertViewHas('template', fn ($t) => $t->is($first));
    $this->actingAs($max)->get('/vorlagen/staedtetrip')->assertOk()->assertViewHas('template', fn ($t) => $t->is($maxs));
    $this->actingAs($max)->get('/vorlagen/staedtetrip-2')->assertNotFound();
    $this->actingAs($max)->get('/vorlagen/'.$first->id)->assertForbidden();

    // Alte Nummer und alter Name leiten weiter
    $this->actingAs($anna)->get('/vorlagen/'.$first->id)->assertRedirect('/vorlagen/staedtetrip');
    $this->actingAs($anna)->put(route('templates.update', $first), ['name' => 'Wochenende'])->assertRedirect('/vorlagen/wochenende');
    $this->actingAs($anna)->get('/vorlagen/staedtetrip')->assertRedirect('/vorlagen/wochenende');
});
