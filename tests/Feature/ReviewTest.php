<?php

use App\Models\Item;
use App\Models\PacklistEntry;
use App\Models\Template;
use App\Models\Trip;
use App\Models\User;
use App\Services\PacklistService;

beforeEach(function () {
    $this->anna = User::factory()->create();
    $this->max = User::factory()->create();
    $this->past = Trip::factory()->for($this->anna, 'owner')->create(['start_date' => now()->subDays(10), 'end_date' => now()->subDays(5)]);
    $this->upcoming = Trip::factory()->for($this->anna, 'owner')->create(['start_date' => now()->addDays(10), 'end_date' => now()->addDays(12)]);

    $this->iron = Item::factory()->for($this->anna, 'owner')->create(['name' => 'Bügeleisen', 'category' => 'misc', 'weight_g' => 900]);
    $this->basis = Template::factory()->for($this->anna, 'owner')->create(['name' => 'Basis']);
    $this->basis->items()->attach($this->iron, ['quantity' => 1]);

    $packlists = app(PacklistService::class);
    $packlists->setTemplates($this->past, $this->anna, [$this->basis->id]);
    $packlists->setTemplates($this->upcoming, $this->anna, [$this->basis->id]);
    $this->entry = PacklistEntry::where(['trip_id' => $this->past->id, 'item_id' => $this->iron->id])->first();
});

it('markiert Einträge als nicht gebraucht und wertet aus', function () {
    $this->actingAs($this->anna)->patch(route('review.update', [$this->past, $this->entry]), ['review' => 'unused'])
        ->assertRedirect(route('trips.show', ['trip' => $this->past, 'tab' => 'review']));

    expect($this->entry->fresh()->review)->toBe('unused');
    $this->actingAs($this->anna)->get(route('trips.show', ['trip' => $this->past, 'tab' => 'review']))
        ->assertOk()->assertSee('100 % umsonst mitgenommen – 900 g Ballast');

    $this->actingAs($this->anna)->patch(route('review.update', [$this->past, $this->entry]), ['review' => '']);
    expect($this->entry->fresh()->review)->toBeNull();
});

it('streicht nicht Gebrauchtes aus der Vorlage – nur für kommende Reisen', function () {
    $this->entry->update(['review' => 'unused']);

    $this->actingAs($this->anna)->delete(route('review.template.remove', [$this->past, $this->entry, $this->basis]));

    expect($this->basis->items()->count())->toBe(0)
        ->and(PacklistEntry::where(['trip_id' => $this->upcoming->id, 'item_id' => $this->iron->id])->exists())->toBeFalse()
        ->and($this->entry->fresh())->not->toBeNull();
});

it('notiert, was gefehlt hat, und übernimmt es in eine Vorlage', function () {
    $this->actingAs($this->anna)->post(route('review.missing', $this->past), ['name' => 'Regenschirm']);
    $missing = PacklistEntry::where(['trip_id' => $this->past->id, 'custom_name' => 'Regenschirm'])->first();
    expect($missing->review)->toBe('missing');

    $this->actingAs($this->anna)->post(route('review.template.add', [$this->past, $missing]), ['template_id' => $this->basis->id])
        ->assertSessionHas('status', fn ($s) => str_contains($s, 'Regenschirm'));

    $umbrella = $this->anna->items()->where('name', 'Regenschirm')->first();
    expect($umbrella)->not->toBeNull()
        ->and($this->basis->items()->whereKey($umbrella->id)->exists())->toBeTrue()
        ->and($missing->fresh()->item_id)->toBe($umbrella->id)
        // Die kommende Reise bekommt den Regenschirm direkt dazu
        ->and(PacklistEntry::where(['trip_id' => $this->upcoming->id, 'item_id' => $umbrella->id])->exists())->toBeTrue();
});

it('nutzt vorhandene Katalog-Gegenstände für „hat gefehlt“', function () {
    Item::factory()->for($this->anna, 'owner')->create(['name' => 'Ohrstöpsel', 'category' => 'sleep']);

    $this->actingAs($this->anna)->post(route('review.missing', $this->past), ['name' => 'ohrstöpsel']);

    expect($this->anna->items()->where('name', 'Ohrstöpsel')->count())->toBe(1)
        ->and(PacklistEntry::where('trip_id', $this->past->id)->where('review', 'missing')->first()->item->name)->toBe('Ohrstöpsel');
});

it('hält den Rückblick persönlich', function () {
    $foreignTemplate = Template::factory()->for($this->max, 'owner')->create();

    $this->actingAs($this->max)->patch(route('review.update', [$this->past, $this->entry]), ['review' => 'unused'])->assertForbidden();
    $this->actingAs($this->max)->post(route('review.missing', $this->past), ['name' => 'X'])->assertForbidden();

    // Eigene Einträge nicht in fremde Vorlagen
    $this->actingAs($this->anna)->post(route('review.template.add', [$this->past, $this->entry]), ['template_id' => $foreignTemplate->id])
        ->assertSessionHasErrors('template_id');

    // Eintrag einer anderen Reise in der URL
    $this->actingAs($this->anna)->patch(route('review.update', [$this->upcoming, $this->entry]), ['review' => 'unused'])->assertNotFound();
});
