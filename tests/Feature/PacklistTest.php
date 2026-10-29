<?php

use App\Enums\EntrySource;
use App\Enums\PacklistGroup;
use App\Models\Container;
use App\Models\Item;
use App\Models\PacklistEntry;
use App\Models\Template;
use App\Models\Trip;
use App\Models\User;
use App\Services\ContainerService;
use App\Services\PacklistService;

beforeEach(function () {
    $this->anna = User::factory()->create();
    $this->max = User::factory()->create();
    $this->trip = Trip::factory()->for($this->anna, 'owner')->create(['start_date' => '2027-05-12', 'end_date' => '2027-05-16']);

    $item = fn (string $name, string $category, array $attrs = []) => Item::factory()->for($this->anna, 'owner')->create(['name' => $name, 'category' => $category, ...$attrs]);
    $this->jacket = $item('Regenjacke', 'outerwear');
    $this->socks = $item('Socken', 'underwear', ['per_day' => true]);
    $this->charger = $item('Ladekabel', 'electronics', ['pack_last' => true]);
    $this->bottle = $item('Trinkflasche', 'gear');

    $this->basis = Template::factory()->for($this->anna, 'owner')->create(['name' => 'Basis']);
    $this->basis->items()->attach([$this->socks->id => ['quantity' => 1], $this->charger->id => ['quantity' => 1], $this->jacket->id => ['quantity' => 1]]);
    $this->hiking = Template::factory()->for($this->anna, 'owner')->create(['name' => 'Wandern']);
    $this->hiking->items()->attach([$this->jacket->id => ['quantity' => 1], $this->bottle->id => ['quantity' => 2]]);
});

function entryFor(Item $item): ?PacklistEntry
{
    return PacklistEntry::where(['trip_id' => test()->trip->id, 'item_id' => $item->id])->first();
}

it('setzt die Liste aus Modulen zusammen und führt doppelte zusammen', function () {
    $this->actingAs($this->anna)
        ->put(route('packlist.templates', $this->trip), ['template_ids' => [$this->basis->id, $this->hiking->id]])
        ->assertRedirect(route('trips.show', $this->trip));

    expect(PacklistEntry::count())->toBe(4)
        ->and(entryFor($this->jacket)->quantity)->toBe(1)
        ->and(entryFor($this->bottle)->quantity)->toBe(2)
        ->and(entryFor($this->socks)->quantity)->toBe(5)
        ->and(entryFor($this->charger)->source)->toBe(EntrySource::Template);
});

it('stellt „Am Abfahrtstag“ an die erste Stelle', function () {
    app(PacklistService::class)->setTemplates($this->trip, $this->anna, [$this->basis->id]);

    expect(entryFor($this->charger)->packlistGroup()->key)->toBe(PacklistGroup::DepartureDay->value)
        ->and(entryFor($this->socks)->packlistGroup()->key)->toBe(PacklistGroup::Clothing->value);

    $this->actingAs($this->anna)->get(route('trips.show', $this->trip))
        ->assertOk()
        ->assertSeeInOrder(['Am Abfahrtstag', 'Ladekabel', 'Kleidung', 'Socken']);
});

it('nimmt beim Abwählen eines Moduls dessen Gegenstände wieder mit, eigene bleiben', function () {
    $service = app(PacklistService::class);
    $service->setTemplates($this->trip, $this->anna, [$this->basis->id, $this->hiking->id]);
    $service->addEntry($this->trip, $this->anna, ['custom_name' => 'Geschenk']);

    $service->setTemplates($this->trip, $this->anna, [$this->basis->id]);

    expect(entryFor($this->bottle))->toBeNull()
        ->and(entryFor($this->jacket))->not->toBeNull()
        ->and(PacklistEntry::where('custom_name', 'Geschenk')->exists())->toBeTrue();
});

it('behält von Hand geänderte Mengen beim Neuberechnen', function () {
    $service = app(PacklistService::class);
    $service->setTemplates($this->trip, $this->anna, [$this->basis->id]);

    $this->actingAs($this->anna)->patch(route('packlist.update', [$this->trip, entryFor($this->socks)]), ['quantity' => 3]);
    $this->trip->update(['end_date' => '2027-05-20']);
    $service->syncTrip($this->trip);

    expect(entryFor($this->socks)->quantity)->toBe(3);
});

it('rechnet „pro Tag“ neu, wenn sich der Zeitraum ändert', function () {
    app(PacklistService::class)->setTemplates($this->trip, $this->anna, [$this->basis->id]);

    $this->actingAs($this->anna)->put(route('trips.update', $this->trip), [
        'name' => $this->trip->name, 'start_date' => '2027-05-12', 'end_date' => '2027-05-18',
        'type' => 'hiking', 'mode' => 'solo',
    ]);

    expect(entryFor($this->socks)->quantity)->toBe(7);
});

it('lässt entfernte Vorlagen-Einträge beim Neuberechnen weg', function () {
    $service = app(PacklistService::class);
    $service->setTemplates($this->trip, $this->anna, [$this->basis->id]);

    $this->actingAs($this->anna)->delete(route('packlist.destroy', [$this->trip, entryFor($this->jacket)]));
    $service->setTemplates($this->trip, $this->anna, [$this->basis->id, $this->hiking->id]);

    expect(entryFor($this->jacket)->excluded)->toBeTrue();
    $this->actingAs($this->anna)->get(route('trips.show', $this->trip))->assertDontSee('aria-label="Regenjacke bearbeiten"', false);

    // Ausdrücklich wieder hinzufügen holt den Eintrag zurück.
    $this->actingAs($this->anna)->post(route('packlist.store', $this->trip), ['item_id' => $this->jacket->id]);
    expect(entryFor($this->jacket)->excluded)->toBeFalse();
});

it('lässt Einträge per „Anpassen“ weg und holt sie wieder zurück', function () {
    app(PacklistService::class)->setTemplates($this->trip, $this->anna, [$this->basis->id]);
    $manual = app(PacklistService::class)->addEntry($this->trip, $this->anna, ['custom_name' => 'Geschenk']);

    $this->actingAs($this->anna)->deleteJson(route('packlist.destroy', [$this->trip, entryFor($this->socks)]))
        ->assertOk()->assertJson(['restorable' => true, 'progress' => ['total' => 3]]);
    $this->actingAs($this->anna)->deleteJson(route('packlist.destroy', [$this->trip, $manual]))
        ->assertOk()->assertJson(['restorable' => false]);

    // Die Vorlage bleibt unverändert, die Reise zeigt den Eintrag unter „Diesmal nicht dabei“.
    expect($this->basis->items()->count())->toBe(3);
    $this->actingAs($this->anna)->get(route('trips.show', $this->trip))->assertSeeInOrder(['Diesmal nicht dabei', 'Socken', 'Doch mitnehmen']);

    $this->actingAs($this->anna)->postJson(route('packlist.restore', [$this->trip, entryFor($this->socks)]))->assertOk();
    expect(entryFor($this->socks))->excluded->toBeFalse()->quantity->toBe(5);

    // Nur Weggelassenes lässt sich zurückholen, nur von der Person selbst.
    $this->actingAs($this->anna)->postJson(route('packlist.restore', [$this->trip, entryFor($this->socks)]))->assertNotFound();
    $this->actingAs($this->anna)->deleteJson(route('packlist.destroy', [$this->trip, entryFor($this->socks)]));
    $this->actingAs($this->max)->postJson(route('packlist.restore', [$this->trip, entryFor($this->socks)]))->assertForbidden();
});

it('übernimmt Änderungen an Vorlagen in kommende Reisen', function () {
    app(PacklistService::class)->setTemplates($this->trip, $this->anna, [$this->hiking->id]);

    $this->actingAs($this->anna)->patch(route('templates.items.update', [$this->hiking, $this->bottle]), ['quantity' => 3]);
    expect(entryFor($this->bottle)->quantity)->toBe(3);

    $this->actingAs($this->anna)->delete(route('templates.items.destroy', [$this->hiking, $this->bottle]));
    expect(entryFor($this->bottle))->toBeNull();
});

it('lässt vergangene Reisen bei Vorlagen-Änderungen in Ruhe', function () {
    $past = Trip::factory()->for($this->anna, 'owner')->create(['start_date' => now()->subDays(10), 'end_date' => now()->subDays(5)]);
    app(PacklistService::class)->setTemplates($past, $this->anna, [$this->hiking->id]);

    $this->actingAs($this->anna)->delete(route('templates.items.destroy', [$this->hiking, $this->bottle]));

    expect(PacklistEntry::where(['trip_id' => $past->id, 'item_id' => $this->bottle->id])->exists())->toBeTrue();
});

it('behält Einträge, wenn eine Vorlage gelöscht wird', function () {
    app(PacklistService::class)->setTemplates($this->trip, $this->anna, [$this->hiking->id]);

    $this->actingAs($this->anna)->delete(route('templates.destroy', $this->hiking));

    expect(entryFor($this->bottle))->not->toBeNull()
        ->and(entryFor($this->bottle)->source)->toBe(EntrySource::Manual);
});

it('behält den Namen, wenn ein Gegenstand aus dem Katalog gelöscht wird', function () {
    app(PacklistService::class)->setTemplates($this->trip, $this->anna, [$this->basis->id]);
    $entryId = entryFor($this->charger)->id;

    $this->actingAs($this->anna)->delete(route('items.destroy', $this->charger));

    $entry = PacklistEntry::with('item')->find($entryId);
    expect($entry->item_id)->toBeNull()
        ->and($entry->name())->toBe('Ladekabel')
        ->and($entry->packlistGroup()->key)->toBe(PacklistGroup::DepartureDay->value);
});

it('fügt Freitext- und Katalog-Einträge hinzu', function () {
    $this->actingAs($this->anna)->post(route('packlist.store', $this->trip), ['custom_name' => 'Geschenk für Oma', 'group' => 'misc', 'quantity' => 2])
        ->assertRedirect(route('trips.show', $this->trip));
    $this->actingAs($this->anna)->post(route('packlist.store', $this->trip), ['item_id' => $this->bottle->id]);

    expect(PacklistEntry::where('custom_name', 'Geschenk für Oma')->first()->quantity)->toBe(2)
        ->and(entryFor($this->bottle)->source)->toBe(EntrySource::Manual);
});

it('nimmt keine fremden Gegenstände auf die Liste', function () {
    $foreign = Item::factory()->for($this->max, 'owner')->create();

    $this->actingAs($this->anna)->post(route('packlist.store', $this->trip), ['item_id' => $foreign->id])
        ->assertSessionHasErrors('item_id');
});

it('nutzt nur eigene Vorlagen als Module', function () {
    $foreign = Template::factory()->for($this->max, 'owner')->create();
    $foreign->items()->attach(Item::factory()->for($this->max, 'owner')->create());

    app(PacklistService::class)->setTemplates($this->trip, $this->anna, [$foreign->id]);

    expect(PacklistEntry::count())->toBe(0);
});

it('hakt per JSON ab und liefert den Fortschritt', function () {
    app(PacklistService::class)->setTemplates($this->trip, $this->anna, [$this->basis->id]);

    $this->actingAs($this->anna)
        ->patchJson(route('packlist.toggle', [$this->trip, entryFor($this->jacket)]), ['packed' => true])
        ->assertOk()
        ->assertJson(['packed' => true, 'progress' => ['packed' => 1, 'total' => 3]]);

    expect(entryFor($this->jacket)->packed_at)->not->toBeNull();

    $this->actingAs($this->anna)->patchJson(route('packlist.toggle', [$this->trip, entryFor($this->jacket)]), ['packed' => false]);
    expect(entryFor($this->jacket))->packed->toBeFalse()->packed_at->toBeNull();
});

it('kopiert Module und eigene Einträge von einer früheren Reise', function () {
    $service = app(PacklistService::class);
    $old = Trip::factory()->for($this->anna, 'owner')->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-03']);
    $service->setTemplates($old, $this->anna, [$this->hiking->id]);
    $service->addEntry($old, $this->anna, ['custom_name' => 'Kartenspiel']);
    $old->entries()->update(['packed' => true]);

    $this->actingAs($this->anna)->post(route('packlist.copy', $this->trip), ['source_trip_id' => $old->id])
        ->assertRedirect(route('trips.show', $this->trip));

    expect($this->trip->templatesFor($this->anna)->pluck('templates.id')->all())->toBe([$this->hiking->id])
        ->and(PacklistEntry::where(['trip_id' => $this->trip->id, 'custom_name' => 'Kartenspiel'])->exists())->toBeTrue()
        ->and(PacklistEntry::where('trip_id', $this->trip->id)->where('packed', true)->exists())->toBeFalse();
});

it('kopiert nicht aus fremden Reisen', function () {
    $foreign = Trip::factory()->for($this->max, 'owner')->create();

    $this->actingAs($this->anna)->post(route('packlist.copy', $this->trip), ['source_trip_id' => $foreign->id])->assertForbidden();
});

it('hält Packlisten in Gruppenreisen getrennt', function () {
    $group = Trip::factory()->group()->for($this->anna, 'owner')->create();
    $group->addMember($this->max);
    app(PacklistService::class)->addEntry($group, $this->anna, ['custom_name' => 'Annas Tagebuch']);
    $maxEntry = app(PacklistService::class)->addEntry($group, $this->max, ['custom_name' => 'Max’ Kamera']);

    $this->actingAs($this->anna)->get(route('trips.show', $group))->assertSee('Annas Tagebuch')->assertDontSee('Max’ Kamera');

    // Anna darf Max’ Eintrag weder abhaken noch ändern oder löschen.
    $this->actingAs($this->anna)->patchJson(route('packlist.toggle', [$group, $maxEntry]), ['packed' => true])->assertForbidden();
    $this->actingAs($this->anna)->patch(route('packlist.update', [$group, $maxEntry]), ['quantity' => 5])->assertForbidden();
    $this->actingAs($this->anna)->delete(route('packlist.destroy', [$group, $maxEntry]))->assertForbidden();
    expect($maxEntry->fresh())->packed->toBeFalse()->quantity->toBe(1);
});

it('sperrt Fremde von der Packliste aus', function () {
    $entry = app(PacklistService::class)->addEntry($this->trip, $this->anna, ['custom_name' => 'Pass']);

    $this->actingAs($this->max)->put(route('packlist.templates', $this->trip), ['template_ids' => []])->assertForbidden();
    $this->actingAs($this->max)->post(route('packlist.store', $this->trip), ['custom_name' => 'X'])->assertForbidden();
    $this->actingAs($this->max)->patchJson(route('packlist.toggle', [$this->trip, $entry]), ['packed' => true])->assertForbidden();
});

it('verhindert Einträge einer anderen Reise in der URL', function () {
    $other = Trip::factory()->for($this->anna, 'owner')->create();
    $entry = app(PacklistService::class)->addEntry($other, $this->anna, ['custom_name' => 'Pass']);

    $this->actingAs($this->anna)->patchJson(route('packlist.toggle', [$this->trip, $entry]), ['packed' => true])->assertNotFound();
});

it('entfernt Packlisten von Mitreisenden, die aus der Reise genommen werden', function () {
    $group = Trip::factory()->group()->for($this->anna, 'owner')->create();
    $group->addMember($this->max);
    app(PacklistService::class)->addEntry($group, $this->max, ['custom_name' => 'Kamera']);

    $this->actingAs($this->anna)->put(route('trips.update', $group), [
        'name' => $group->name, 'start_date' => $group->start_date->toDateString(), 'end_date' => $group->end_date->toDateString(),
        'type' => 'citytrip', 'mode' => 'group', 'members' => [],
    ]);

    expect(PacklistEntry::where('user_id', $this->max->id)->exists())->toBeFalse();
});

it('zeigt den Fortschritt auf der Startseite', function () {
    $service = app(PacklistService::class);
    $service->setTemplates($this->trip, $this->anna, [$this->basis->id]);
    entryFor($this->jacket)->setPacked(true);
    entryFor($this->socks)->setPacked(true);

    // Mehr als ein gepackter Eintrag: fängt ab, dass die Zählung als Boolean gecastet wird.
    expect($service->progress($this->trip, $this->anna))->toBe(['packed' => 2, 'total' => 3]);
    $this->actingAs($this->anna)->get(route('home'))->assertSee('2 von 3 gepackt');
    $this->actingAs($this->anna)->get(route('trips.show', $this->trip))->assertSee('width: 67%', false);
});

it('führt Aufgaben vor der Abfahrt als eigene Karte ohne Behältnis und Gewicht', function () {
    $task = Item::factory()->for($this->anna, 'owner')->create(['name' => 'Herd aus', 'category' => 'tasks', 'weight_g' => 500]);
    $suitcase = Container::factory()->for($this->anna, 'owner')->create(['name' => 'Koffer']);
    app(ContainerService::class)->setForTrip($this->trip, $this->anna, [$suitcase->id]);
    app(PacklistService::class)->setTemplates($this->trip, $this->anna, [$this->basis->id]);
    app(PacklistService::class)->addEntry($this->trip, $this->anna, ['item_id' => $task->id]);
    // Nur die Aufgabe hat ein Gewicht – zählt sie mit, wäre die Summe nicht 0.
    Item::whereKeyNot($task->id)->update(['weight_g' => null]);
    $suitcase->update(['empty_weight_g' => null]);

    expect(entryFor($task)->isTask())->toBeTrue()
        ->and(entryFor($task)->packlistGroup()->key)->toBe(PacklistGroup::Tasks->value);

    $this->actingAs($this->anna)->get(route('trips.show', $this->trip))
        ->assertSeeInOrder(['Am Abfahrtstag', 'Vor der Abfahrt erledigen', 'Herd aus', 'Kleidung'])
        ->assertViewHas('unassigned', fn ($entries) => ! $entries->contains('item_id', $task->id))
        ->assertViewHas('weight', fn ($weight) => $weight['total'] === 0);
});

it('legt per Schnelleingabe Gegenstände direkt in der Vorlage an', function () {
    $this->actingAs($this->anna)->postJson(route('templates.items.store', $this->basis), ['new_name' => 'Kulturbeutel', 'new_category' => 'toiletries'])
        ->assertCreated()->assertJson(['name' => 'Kulturbeutel', 'badges' => []]);
    // Gibt es schon im Katalog → wird übernommen
    $this->actingAs($this->anna)->postJson(route('templates.items.store', $this->basis), ['new_name' => 'trinkflasche', 'new_category' => 'misc'])
        ->assertCreated()->assertJson(['name' => 'Trinkflasche', 'badges' => ['aus dem Katalog']]);
    // Steht schon drin → Fehler am Feld
    $this->actingAs($this->anna)->postJson(route('templates.items.store', $this->basis), ['new_name' => 'Socken', 'new_category' => 'misc'])
        ->assertUnprocessable()->assertJsonValidationErrors('new_name');

    expect(Item::where('name', 'Kulturbeutel')->sole()->category->value)->toBe('toiletries')
        ->and($this->basis->items()->pluck('name')->sort()->values()->all())->toBe(['Kulturbeutel', 'Ladekabel', 'Regenjacke', 'Socken', 'Trinkflasche']);
});

it('schlägt vor, mehrfach Weggelassenes aus der Vorlage zu streichen', function () {
    $earlier = Trip::factory()->for($this->anna, 'owner')->create(['start_date' => '2027-03-01', 'end_date' => '2027-03-03']);
    $service = app(PacklistService::class);
    foreach ([$earlier, $this->trip] as $trip) {
        $service->setTemplates($trip, $this->anna, [$this->basis->id]);
        $service->removeEntry(PacklistEntry::where(['trip_id' => $trip->id, 'item_id' => $this->jacket->id])->sole());
    }

    $this->actingAs($this->anna)->get(route('trips.show', $this->trip))
        ->assertSee('Schon auf 2 Reisen weggelassen')
        ->assertSee('Aus „Basis“ streichen');

    $this->actingAs($this->anna)->delete(route('review.template.remove', [$this->trip, entryFor($this->jacket), $this->basis]), ['zurueck' => 'packliste'])
        ->assertRedirect(route('trips.show', $this->trip));
    expect($this->basis->items()->whereKey($this->jacket->id)->exists())->toBeFalse();
});

it('hakt auf dem Rückweg getrennt ab – ohne Aufgaben, ab dem ersten Reisetag', function () {
    app(PacklistService::class)->setTemplates($this->trip, $this->anna, [$this->basis->id]);
    $task = app(PacklistService::class)->addEntry($this->trip, $this->anna, ['custom_name' => 'Herd aus', 'group' => 'tasks']);
    $socks = entryFor($this->socks);
    $socks->update(['packed' => true]);

    // Vor der Reise: kein Rückweg
    $this->travelTo('2027-05-01');
    $this->actingAs($this->anna)->get(route('trips.show', $this->trip))->assertDontSee('Rückweg')->assertViewHas('initialDirection', 'there');

    // Während der Reise umschaltbar, am letzten Tag gleich aktiv
    $this->travelTo('2027-05-13');
    $this->actingAs($this->anna)->get(route('trips.show', $this->trip))->assertSee('Rückweg')->assertViewHas('initialDirection', 'there');
    $this->travelTo('2027-05-16');
    $response = $this->actingAs($this->anna)->get(route('trips.show', $this->trip))->assertViewHas('initialDirection', 'back');
    expect($response->getContent())->not->toContain('&quot;'.$task->id.'&quot;:false}');

    $this->actingAs($this->anna)->patchJson(route('packlist.return', [$this->trip, $socks]), ['packed' => true])
        ->assertOk()->assertJson(['applied' => true, 'packed' => true]);
    expect($socks->fresh())->returned->toBeTrue()->packed->toBeTrue();

    // Älterer Offline-Stand verliert
    $this->actingAs($this->anna)->patchJson(route('packlist.return', [$this->trip, $socks]), ['packed' => false, 'changed_at' => now()->subHour()->toIso8601String()])
        ->assertJson(['applied' => false, 'packed' => true]);

    // Fremde dürfen nicht
    $this->actingAs($this->max)->patchJson(route('packlist.return', [$this->trip, $socks]), ['packed' => false])->assertForbidden();
});
