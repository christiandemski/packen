<?php

use App\Enums\EntrySource;
use App\Models\Container;
use App\Models\Item;
use App\Models\OutfitDay;
use App\Models\PacklistEntry;
use App\Models\Template;
use App\Models\Trip;
use App\Models\User;
use App\Services\ContainerService;
use App\Services\OutfitService;
use App\Services\PacklistService;

beforeEach(function () {
    $this->anna = User::factory()->create();
    $this->max = User::factory()->create();
    $this->trip = Trip::factory()->for($this->anna, 'owner')->create(['start_date' => '2027-05-12', 'end_date' => '2027-05-16']); // 5 Tage
    $this->service = app(OutfitService::class);

    $item = fn (string $name, string $category, bool $perDay = false) => Item::factory()->for($this->anna, 'owner')->create(['name' => $name, 'category' => $category, 'per_day' => $perDay]);
    $this->socks = $item('Socken', 'underwear', true);
    $this->jeans = $item('Jeans', 'bottoms');
    $this->shirt = $item('Leinenhemd', 'tops');
    $this->dress = $item('Kleid', 'tops');
});

/** Teil an bestimmten Tagen (Index ab 0) in den Slot „Tag“ legen. */
function wear(Item $item, array $dayIndexes, string $slot = 'Tag'): void
{
    $days = app(OutfitService::class)->days(test()->trip, test()->anna);
    foreach ($dayIndexes as $index) {
        $day = $days[$index];
        $target = $day->slots->firstWhere('name', $slot) ?? app(OutfitService::class)->addSlot($day, $slot);
        $target->items()->syncWithoutDetaching([$item->id]);
    }
}

function quantitiesNow(): array
{
    return app(OutfitService::class)->quantities(app(OutfitService::class)->days(test()->trip, test()->anna));
}

it('legt pro Reisetag einen Tag mit Slot „Tag“ an und passt sich dem Zeitraum an', function () {
    $days = $this->service->days($this->trip, $this->anna);
    expect($days)->toHaveCount(5)
        ->and($days->first()->slots->pluck('name')->all())->toBe(['Tag'])
        ->and($days->first()->date->toDateString())->toBe('2027-05-12');

    $this->trip->update(['start_date' => '2027-05-14', 'end_date' => '2027-05-18']);
    $days = $this->service->days($this->trip, $this->anna);
    expect($days->map(fn ($d) => $d->date->toDateString())->all())->toBe(['2027-05-14', '2027-05-15', '2027-05-16', '2027-05-17', '2027-05-18']);
});

it('rechnet ohne „pro Tag“ immer 1, mit „pro Tag“ je Einsatz', function () {
    wear($this->jeans, [0, 1, 2, 3, 4]);
    wear($this->socks, [0, 1, 2, 3, 4]);

    expect(quantitiesNow())->toBe([$this->jeans->id => 1, $this->socks->id => 5]);
});

it('teilt „pro Tag“ an Waschtagen', function () {
    wear($this->socks, [0, 1, 2, 3, 4]);
    $days = $this->service->days($this->trip, $this->anna);
    $days[1]->update(['laundry_day' => true]); // Tag 1+2 | Tag 3–5

    expect(quantitiesNow()[$this->socks->id])->toBe(3);

    $days[3]->update(['laundry_day' => true]); // 2 | 2 | 1
    expect(quantitiesNow()[$this->socks->id])->toBe(2);
});

it('zählt zwei Einsätze am selben Tag doppelt (Tag + Sport)', function () {
    wear($this->socks, [0, 1]);
    wear($this->socks, [0], 'Sport');

    expect(quantitiesNow()[$this->socks->id])->toBe(3);
});

it('zeigt die Übersicht mit Tagen und markiert Einzelstücke', function () {
    wear($this->jeans, [0, 1, 2]);
    wear($this->dress, [4]);

    $overview = $this->service->overview($this->service->days($this->trip, $this->anna));
    $rows = collect($overview)->flatten(1)->keyBy(fn ($row) => $row['item']->name);
    expect($rows['Jeans']['total'])->toBe(3)->and($rows['Kleid']['total'])->toBe(1);

    $this->actingAs($this->anna)->get(route('trips.show', ['trip' => $this->trip, 'tab' => 'outfits']))
        ->assertOk()
        ->assertSee('Übersicht')
        ->assertSee('Nur einmal getragen', false)
        ->assertSee('1× Jeans', false);
});

it('übernimmt Kleidung in die Packliste und behält Handkorrekturen', function () {
    $basis = Template::factory()->for($this->anna, 'owner')->create();
    $basis->items()->attach([$this->socks->id => ['quantity' => 1], $this->shirt->id => ['quantity' => 1]]);
    app(PacklistService::class)->setTemplates($this->trip, $this->anna, [$basis->id]);
    expect(PacklistEntry::where('item_id', $this->socks->id)->first()->quantity)->toBe(5); // aus der Vorlage: 5 Tage

    wear($this->socks, [0, 1, 2, 3, 4]);
    wear($this->jeans, [0, 1]);
    $this->service->days($this->trip, $this->anna)[2]->update(['laundry_day' => true]);

    $this->actingAs($this->anna)->post(route('outfits.apply', $this->trip))->assertRedirect(route('trips.show', $this->trip));

    $socks = PacklistEntry::where('item_id', $this->socks->id)->first();
    $jeans = PacklistEntry::where('item_id', $this->jeans->id)->first();
    expect($socks)->quantity->toBe(3)->source->toBe(EntrySource::Outfit)
        ->and($jeans)->quantity->toBe(1)->source->toBe(EntrySource::Outfit)
        ->and(PacklistEntry::where('item_id', $this->shirt->id)->first()->source)->toBe(EntrySource::Template);

    // Handkorrektur bleibt; Vorlagen-Sync fasst Outfit-Einträge nicht an
    $this->actingAs($this->anna)->patch(route('packlist.update', [$this->trip, $socks]), ['quantity' => 4]);
    $this->actingAs($this->anna)->post(route('outfits.apply', $this->trip));
    app(PacklistService::class)->sync($this->trip, $this->anna);
    expect($socks->fresh()->quantity)->toBe(4);
});

it('gibt Teile, die in keinem Outfit mehr vorkommen, an die Vorlagen zurück', function () {
    $basis = Template::factory()->for($this->anna, 'owner')->create();
    $basis->items()->attach([$this->socks->id => ['quantity' => 1]]);
    app(PacklistService::class)->setTemplates($this->trip, $this->anna, [$basis->id]);

    wear($this->socks, [0]);
    wear($this->jeans, [0]);
    $this->service->applyToPacklist($this->trip, $this->anna);
    expect(PacklistEntry::where('item_id', $this->socks->id)->first()->quantity)->toBe(1);

    $this->service->days($this->trip, $this->anna)->each(fn ($day) => $day->slots->each(fn ($slot) => $slot->items()->detach()));
    $this->service->applyToPacklist($this->trip, $this->anna);

    expect(PacklistEntry::where('item_id', $this->socks->id)->first())->source->toBe(EntrySource::Template)->quantity->toBe(5)
        ->and(PacklistEntry::where('item_id', $this->jeans->id)->exists())->toBeFalse();
});

it('pflegt Tage, Slots und Teile über die Oberfläche', function () {
    $day = $this->service->days($this->trip, $this->anna)[1];
    $slot = $day->slots->first();

    $this->actingAs($this->anna)->patch(route('outfits.day', [$this->trip, $day]), ['title' => 'Alfama & Belém'])
        ->assertRedirect(route('trips.show', ['trip' => $this->trip, 'tab' => 'outfits', 'tag' => '2027-05-13']));
    $this->actingAs($this->anna)->patch(route('outfits.day', [$this->trip, $day]), ['laundry_day' => '1']);
    expect($day->fresh())->title->toBe('Alfama & Belém')->laundry_day->toBeTrue();

    $this->actingAs($this->anna)->post(route('outfits.slots.store', [$this->trip, $day]), ['name' => 'Abend']);
    $this->actingAs($this->anna)->post(route('outfits.items.store', [$this->trip, $slot]), ['item_id' => $this->jeans->id]);
    $this->actingAs($this->anna)->post(route('outfits.items.store', [$this->trip, $slot]), ['new_name' => 'Strohhut', 'new_category' => 'accessories']);
    expect($day->fresh()->slots->pluck('name')->all())->toBe(['Tag', 'Abend'])
        ->and($slot->items()->pluck('name')->sort()->values()->all())->toBe(['Jeans', 'Strohhut'])
        ->and($this->anna->items()->where('name', 'Strohhut')->first()->is_clothing)->toBeTrue();

    $this->actingAs($this->anna)->delete(route('outfits.items.destroy', [$this->trip, $slot, $this->jeans]));
    expect($slot->items()->count())->toBe(1);

    // Wie am Vortag
    $next = $this->service->days($this->trip, $this->anna)[2];
    $this->actingAs($this->anna)->post(route('outfits.copy', [$this->trip, $next]));
    expect($next->fresh()->slots()->with('items')->get()->map(fn ($s) => [$s->name, $s->items->pluck('name')->all()])->all())
        ->toBe([['Tag', ['Strohhut']], ['Abend', []]]);
});

it('nimmt nur Kleidung aus dem eigenen Katalog', function () {
    $slot = $this->service->days($this->trip, $this->anna)[0]->slots->first();
    $foreign = Item::factory()->for($this->max, 'owner')->create(['category' => 'tops']);

    $this->actingAs($this->anna)->post(route('outfits.items.store', [$this->trip, $slot]), ['item_id' => $foreign->id])->assertSessionHasErrors('item_id');
    $this->actingAs($this->anna)->post(route('outfits.items.store', [$this->trip, $slot]), ['new_name' => 'Zelt', 'new_category' => 'gear'])->assertSessionHasErrors('new_category');
});

it('hält Outfits privat – auch in Gruppenreisen', function () {
    $group = Trip::factory()->group()->for($this->anna, 'owner')->create();
    $group->addMember($this->max);
    $annasDay = $this->service->days($group, $this->anna)[0];
    $annasSlot = $annasDay->slots->first();

    $this->actingAs($this->max)->patch(route('outfits.day', [$group, $annasDay]), ['title' => 'gekapert'])->assertNotFound();
    $this->actingAs($this->max)->post(route('outfits.slots.store', [$group, $annasDay]), ['name' => 'X'])->assertNotFound();
    $this->actingAs($this->max)->delete(route('outfits.slots.destroy', [$group, $annasSlot]))->assertNotFound();

    // Tag aus einer anderen Reise in der URL
    $this->actingAs($this->anna)->patch(route('outfits.day', [$group, $this->service->days($this->trip, $this->anna)[0]]), ['title' => 'X'])->assertNotFound();

    // Fremde ohne Mitgliedschaft
    $outsider = User::factory()->create();
    $this->actingAs($outsider)->post(route('outfits.apply', $group))->assertForbidden();

    expect($annasDay->fresh()->title)->toBeNull()
        ->and(OutfitDay::where('trip_id', $group->id)->where('user_id', $this->max->id)->count())->toBe(0);
});

it('rechnet die Anreise-Kleidung aus dem Gepäckgewicht heraus', function () {
    $this->jeans->update(['weight_g' => 600]);
    $this->shirt->update(['weight_g' => 200]);
    $this->socks->update(['weight_g' => 50]);
    wear($this->jeans, [0, 1, 2]);
    wear($this->shirt, [0]);
    wear($this->socks, [0, 1, 2, 3, 4]);
    $this->service->applyToPacklist($this->trip, $this->anna); // 1 Jeans, 1 Hemd, 5 Socken = 1050 g

    $suitcase = Container::factory()->for($this->anna, 'owner')->create(['empty_weight_g' => null]);
    $containers = app(ContainerService::class);
    $containers->setForTrip($this->trip, $this->anna, [$suitcase->id]);
    $containers->assign($this->trip, $this->anna, PacklistEntry::where('trip_id', $this->trip->id)->pluck('id')->all(), $suitcase->id);

    $weigh = function () use ($containers) {
        $entries = PacklistEntry::with('item')->where('trip_id', $this->trip->id)->visible()->get();
        $worn = $this->service->wornOnArrival($this->trip, $this->anna);
        $summary = $containers->summary($this->trip, $this->anna, $entries, $worn);

        return [ContainerService::totalWeight($entries, $summary, $worn), $summary->first()['weight']];
    };

    // Ohne Slot „Anreise“ zählt das Outfit „Tag“ des ersten Tages: Jeans, Hemd, 1 Paar Socken.
    [$total, $inSuitcase] = $weigh();
    expect($total)->worn->toBe(850)->total->toBe(200)->and($inSuitcase)->toBe(200);

    // Mit Slot „Anreise“ zählt nur der.
    wear($this->jeans, [0], 'Anreise');
    [$total] = $weigh();
    expect($total)->worn->toBe(600)->total->toBe(450);

    $this->actingAs($this->anna)->get(route('trips.show', $this->trip))->assertSeeText('ohne 600 g Anreise-Kleidung');
});
