<?php

use App\Enums\TripType;
use App\Models\CustomTripType;
use App\Models\Trip;
use App\Models\User;

beforeEach(function () {
    $this->anna = User::factory()->create();
    $this->max = User::factory()->create();
});

function typedTripInput(array $overrides = []): array
{
    return [
        'name' => 'Messe Berlin',
        'start_date' => '2027-03-02',
        'end_date' => '2027-03-04',
        'mode' => 'solo',
        ...$overrides,
    ];
}

it('legt beim Anlegen einer Reise eine neue Reiseart an', function () {
    $this->actingAs($this->anna)->post(route('trips.store'), typedTripInput([
        'type' => 'new', 'new_type_name' => 'Geschäftsreise', 'new_type_icon' => 'briefcase', 'new_type_color' => 'ice',
    ]))->assertRedirect();

    $type = CustomTripType::sole();
    $trip = Trip::sole();
    expect($type)->owner_id->toBe($this->anna->id)->name->toBe('Geschäftsreise')
        ->and($trip->custom_trip_type_id)->toBe($type->id)
        ->and($trip->type)->toBe(TripType::Other)
        ->and($trip->typeLabel())->toBe('Geschäftsreise')
        ->and($trip->typeIcon())->toBe('briefcase');

    $this->actingAs($this->anna)->get(route('home'))->assertSee('Geschäftsreise');
});

it('verlangt Name, Icon und Farbe für eine neue Reiseart', function () {
    $this->actingAs($this->anna)->post(route('trips.store'), typedTripInput(['type' => 'new']))
        ->assertSessionHasErrors(['new_type_name', 'new_type_icon', 'new_type_color']);

    expect(Trip::count())->toBe(0);
});

it('wählt eigene Reisearten aus und wechselt zurück auf feste', function () {
    $type = CustomTripType::create(['owner_id' => $this->anna->id, 'name' => 'Festival', 'icon' => 'music', 'color' => 'rose']);

    $this->actingAs($this->anna)->get(route('trips.create'))->assertSee('Festival');
    $this->actingAs($this->anna)->post(route('trips.store'), typedTripInput(['type' => $type->key()]))->assertRedirect();
    $trip = Trip::sole();
    expect($trip->custom_trip_type_id)->toBe($type->id);

    $this->actingAs($this->anna)->put(route('trips.update', $trip), typedTripInput(['type' => 'hiking']));
    expect($trip->fresh())->custom_trip_type_id->toBeNull()->type->toBe(TripType::Hiking);
});

it('lässt keine fremden Reisearten zu', function () {
    $foreign = CustomTripType::create(['owner_id' => $this->max->id, 'name' => 'Geheim', 'icon' => 'star', 'color' => 'pine']);

    $this->actingAs($this->anna)->post(route('trips.store'), typedTripInput(['type' => $foreign->key()]))->assertSessionHasErrors('type');
    $this->actingAs($this->anna)->get(route('trip-types.index'))->assertDontSee('Geheim');
    $this->actingAs($this->anna)->put(route('trip-types.update', $foreign), ['name' => 'X', 'icon' => 'star', 'color' => 'pine'])->assertForbidden();
    $this->actingAs($this->anna)->delete(route('trip-types.destroy', $foreign))->assertForbidden();
});

it('verwaltet Reisearten; beim Löschen werden Reisen zu „Sonstiges“', function () {
    $this->actingAs($this->anna)->post(route('trip-types.store'), ['name' => 'Segeln', 'icon' => 'ship', 'color' => 'sea'])->assertRedirect(route('trip-types.index'));
    $type = CustomTripType::sole();
    $this->actingAs($this->anna)->post(route('trip-types.store'), ['name' => 'Segeln', 'icon' => 'ship', 'color' => 'sea'])->assertSessionHasErrorsIn('create', 'name');

    $this->actingAs($this->anna)->put(route('trip-types.update', $type), ['name' => 'Segeltörn', 'icon' => 'waves', 'color' => 'ice']);
    expect($type->fresh())->name->toBe('Segeltörn')->icon->toBe('waves');

    $trip = Trip::factory()->for($this->anna, 'owner')->create(['type' => 'other', 'custom_trip_type_id' => $type->id]);
    $this->actingAs($this->anna)->delete(route('trip-types.destroy', $type))->assertRedirect(route('trip-types.index'));

    expect(CustomTripType::count())->toBe(0)
        ->and($trip->fresh())->custom_trip_type_id->toBeNull()->typeLabel()->toBe('Sonstiges');
});
