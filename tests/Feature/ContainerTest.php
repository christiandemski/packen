<?php

use App\Enums\ContainerKind;
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
    $this->trip = Trip::factory()->for($this->anna, 'owner')->create();

    $this->suitcase = Container::factory()->for($this->anna, 'owner')->create(['name' => 'Großer Koffer', 'kind' => ContainerKind::Suitcase, 'max_weight_g' => 1000, 'empty_weight_g' => 300]);
    $this->box = Container::factory()->for($this->anna, 'owner')->create(['name' => 'Eurobox Küche', 'kind' => ContainerKind::Box]);

    $this->stove = Item::factory()->for($this->anna, 'owner')->create(['name' => 'Kocher', 'category' => 'food', 'weight_g' => 400, 'default_container_id' => $this->box->id]);
    $this->jacket = Item::factory()->for($this->anna, 'owner')->create(['name' => 'Jacke', 'category' => 'outerwear', 'weight_g' => 800]);

    $this->template = Template::factory()->for($this->anna, 'owner')->create();
    $this->template->items()->attach([$this->stove->id => ['quantity' => 1], $this->jacket->id => ['quantity' => 1]]);
    app(PacklistService::class)->setTemplates($this->trip, $this->anna, [$this->template->id]);
});

function entryOf(Item $item): PacklistEntry
{
    return PacklistEntry::where(['trip_id' => test()->trip->id, 'item_id' => $item->id])->first();
}

it('legt Behältnisse an, rechnet kg in Gramm um und ändert sie', function () {
    $this->actingAs($this->anna)->post(route('containers.store'), [
        'name' => 'Wanderrucksack', 'kind' => 'backpack', 'capacity_l' => 45, 'max_weight_kg' => '12,5', 'empty_weight_kg' => '1.4',
    ])->assertSessionHasErrorsIn('create', 'max_weight_kg'); // Komma lehnt der Browser ohnehin ab; der Server erwartet Punkt

    $this->actingAs($this->anna)->post(route('containers.store'), [
        'name' => 'Wanderrucksack', 'kind' => 'backpack', 'capacity_l' => 45, 'max_weight_kg' => '12.5', 'empty_weight_kg' => '1.4',
    ])->assertRedirect(route('containers.index'));

    $backpack = Container::where('name', 'Wanderrucksack')->first();
    expect($backpack)->max_weight_g->toBe(12500)->empty_weight_g->toBe(1400)->capacity_l->toBe(45)
        ->and($backpack->limitsLabel())->toBe('45 l · max. 12,5 kg');

    $this->actingAs($this->anna)->put(route('containers.update', $backpack), ['name' => 'Tagesrucksack', 'kind' => 'backpack']);
    expect($backpack->fresh())->name->toBe('Tagesrucksack')->max_weight_g->toBeNull();
});

it('schützt fremde Behältnisse', function () {
    $this->actingAs($this->max)->put(route('containers.update', $this->box), ['name' => 'X', 'kind' => 'box'])->assertForbidden();
    $this->actingAs($this->max)->delete(route('containers.destroy', $this->box))->assertForbidden();
    $this->actingAs($this->max)->get(route('containers.index'))->assertOk()->assertDontSee('Großer Koffer');

    // Auch nicht als Standardplatz eines eigenen Gegenstands
    $this->actingAs($this->max)->post(route('items.store'), ['name' => 'Topf', 'category' => 'food', 'default_container_id' => $this->box->id])
        ->assertSessionHasErrors('default_container_id');
});

it('legt Gegenstände mit Standardplatz automatisch ins gewählte Behältnis', function () {
    expect(entryOf($this->stove)->container_id)->toBeNull(); // Box ist noch nicht dabei

    $this->actingAs($this->anna)->put(route('packlist.containers', $this->trip), ['container_ids' => [$this->box->id, $this->suitcase->id]])
        ->assertRedirect(route('trips.show', ['trip' => $this->trip, 'ansicht' => 'behaeltnisse']));

    expect(entryOf($this->stove)->container_id)->toBe($this->box->id)
        ->and(entryOf($this->jacket)->container_id)->toBeNull();
});

it('ordnet mehrere Einträge zu und nimmt sie beim Abwählen wieder heraus', function () {
    $service = app(ContainerService::class);
    $service->setForTrip($this->trip, $this->anna, [$this->suitcase->id, $this->box->id]);

    $this->actingAs($this->anna)->patch(route('packlist.assign', $this->trip), [
        'entry_ids' => [entryOf($this->jacket)->id], 'container_id' => $this->suitcase->id,
    ]);
    expect(entryOf($this->jacket)->container_id)->toBe($this->suitcase->id);

    $service->setForTrip($this->trip, $this->anna, [$this->box->id]);
    expect(entryOf($this->jacket)->container_id)->toBeNull()
        ->and(entryOf($this->stove)->container_id)->toBe($this->box->id);
});

it('ordnet nur in Behältnisse dieser Reise zu', function () {
    $foreign = Container::factory()->for($this->max, 'owner')->create();
    app(ContainerService::class)->setForTrip($this->trip, $this->anna, [$foreign->id, $this->box->id]);

    expect(app(ContainerService::class)->selectedIds($this->trip, $this->anna)->all())->toBe([$this->box->id]);

    $this->actingAs($this->anna)->patch(route('packlist.assign', $this->trip), ['entry_ids' => [entryOf($this->jacket)->id], 'container_id' => $this->suitcase->id]);
    expect(entryOf($this->jacket)->container_id)->toBeNull();
});

it('rechnet Gewicht und warnt bei Überladung', function () {
    $service = app(ContainerService::class);
    $service->setForTrip($this->trip, $this->anna, [$this->suitcase->id, $this->box->id]);
    $service->assign($this->trip, $this->anna, [entryOf($this->jacket)->id], $this->suitcase->id);

    $entries = PacklistEntry::with('item')->where('trip_id', $this->trip->id)->get();
    $summary = $service->summary($this->trip, $this->anna, $entries)->keyBy(fn ($row) => $row['container']->name);

    expect($summary['Großer Koffer'])->weight->toBe(1100)->over->toBeTrue()
        ->and($summary['Eurobox Küche'])->weight->toBe(400)->over->toBeFalse()
        ->and(ContainerService::neededLabel($summary))->toBe('1 Box und 1 Koffer');
});

it('zeigt die Ansicht nach Behältnis', function () {
    app(ContainerService::class)->setForTrip($this->trip, $this->anna, [$this->box->id]);

    $this->actingAs($this->anna)->get(route('trips.show', ['trip' => $this->trip, 'ansicht' => 'behaeltnisse']))
        ->assertOk()
        ->assertSee('Nach Behältnis')
        ->assertSee('Du brauchst')
        ->assertSee('1 Box')
        ->assertSee('Ohne Behältnis');
});

it('übernimmt Behältnisse und Plätze beim Kopieren einer Reise', function () {
    $service = app(ContainerService::class);
    $service->setForTrip($this->trip, $this->anna, [$this->suitcase->id, $this->box->id]);
    $service->assign($this->trip, $this->anna, [entryOf($this->jacket)->id], $this->suitcase->id);

    $next = Trip::factory()->for($this->anna, 'owner')->create();
    app(PacklistService::class)->copyFrom($this->trip, $next, $this->anna);

    $jacket = PacklistEntry::where(['trip_id' => $next->id, 'item_id' => $this->jacket->id])->first();
    expect($service->selectedIds($next, $this->anna)->sort()->values()->all())->toBe(collect([$this->suitcase->id, $this->box->id])->sort()->values()->all())
        ->and($jacket->container_id)->toBe($this->suitcase->id);
});

it('lässt Einträge beim Löschen eines Behältnisses ohne Behältnis zurück', function () {
    app(ContainerService::class)->setForTrip($this->trip, $this->anna, [$this->box->id]);

    $this->actingAs($this->anna)->delete(route('containers.destroy', $this->box));

    expect(entryOf($this->stove)->container_id)->toBeNull()
        ->and($this->stove->fresh()->default_container_id)->toBeNull();
});

it('zeigt die Verwaltung und das Gegenstand-Formular mit Standardplatz', function () {
    $this->actingAs($this->anna)->get(route('containers.index'))->assertOk()->assertSee('Großer Koffer')->assertSee('Behältnis anlegen');
    $this->actingAs($this->anna)->get(route('items.edit', $this->stove))->assertOk()->assertSee('Standardplatz');
});
