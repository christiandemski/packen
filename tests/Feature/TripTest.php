<?php

use App\Enums\TripMemberRole;
use App\Enums\TripMode;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->anna = User::factory()->create(['name' => 'Anna']);
    $this->max = User::factory()->create(['name' => 'Max']);
});

function tripInput(array $overrides = []): array
{
    return [
        'name' => 'Lissabon',
        'location' => 'Lissabon, Portugal',
        'start_date' => '2027-05-12',
        'end_date' => '2027-05-16',
        'type' => 'citytrip',
        'mode' => 'solo',
        'transports' => ['plane', 'rental_car'],
        'accommodation' => 'apartment',
        ...$overrides,
    ];
}

it('legt eine private Reise an', function () {
    $this->actingAs($this->anna)->post(route('trips.store'), tripInput(['members' => [$this->max->id]]))
        ->assertRedirect();

    $trip = Trip::sole();
    expect($trip)
        ->owner_id->toBe($this->anna->id)
        ->mode->toBe(TripMode::Solo)
        ->and($trip->members()->pluck('users.id')->all())->toBe([$this->anna->id])
        ->and($trip->members->first()->pivot->role)->toBe(TripMemberRole::Owner);
});

it('legt eine Gruppenreise mit Mitreisenden an', function () {
    $this->actingAs($this->anna)->post(route('trips.store'), tripInput(['mode' => 'group', 'members' => [$this->max->id]]));

    $trip = Trip::sole();
    expect($trip->isGroup())->toBeTrue()
        ->and($trip->members()->pluck('users.id')->sort()->values()->all())->toBe([$this->anna->id, $this->max->id]);
});

it('nimmt keine gesperrten Personen als Mitreisende', function () {
    $blocked = User::factory()->blocked()->create();

    $this->actingAs($this->anna)
        ->post(route('trips.store'), tripInput(['mode' => 'group', 'members' => [$blocked->id]]))
        ->assertSessionHasErrors('members.0');
});

it('prüft den Zeitraum', function () {
    $this->actingAs($this->anna)
        ->post(route('trips.store'), tripInput(['start_date' => '2027-05-16', 'end_date' => '2027-05-12']))
        ->assertSessionHasErrors('end_date');

    $this->actingAs($this->anna)
        ->post(route('trips.store'), tripInput(['start_date' => '2027-01-01', 'end_date' => '2028-06-01']))
        ->assertSessionHasErrors('end_date');
});

it('zeigt auf der Startseite nur eigene Reisen', function () {
    Trip::factory()->for($this->anna, 'owner')->create(['name' => 'Annas Reise']);
    Trip::factory()->for($this->max, 'owner')->create(['name' => 'Max’ Geheimtrip']);

    $this->actingAs($this->anna)->get(route('home'))
        ->assertOk()
        ->assertSee('Annas Reise')
        ->assertDontSee('Max’ Geheimtrip');
});

it('zeigt Gruppenreisen allen Mitreisenden', function () {
    $trip = Trip::factory()->group()->for($this->max, 'owner')->create(['name' => 'Gemeinsam nach Porto']);
    $trip->addMember($this->anna);

    $this->actingAs($this->anna)->get(route('home'))->assertSee('Gemeinsam nach Porto');
    $this->actingAs($this->anna)->get(route('trips.show', $trip))->assertOk();
});

it('verweigert Fremden den Zugriff auf eine Reise', function () {
    $trip = Trip::factory()->for($this->max, 'owner')->create();

    $this->actingAs($this->anna)->get(route('trips.show', $trip))->assertForbidden();
    $this->actingAs($this->anna)->get(route('trips.edit', $trip))->assertForbidden();
    $this->actingAs($this->anna)->put(route('trips.update', $trip), tripInput())->assertForbidden();
    $this->actingAs($this->anna)->delete(route('trips.destroy', $trip))->assertForbidden();
});

it('lässt nur den Owner ändern und löschen', function () {
    $trip = Trip::factory()->group()->for($this->max, 'owner')->create();
    $trip->addMember($this->anna);

    $this->actingAs($this->anna)->get(route('trips.edit', $trip))->assertForbidden();
    $this->actingAs($this->anna)->put(route('trips.update', $trip), tripInput())->assertForbidden();
    $this->actingAs($this->anna)->delete(route('trips.destroy', $trip))->assertForbidden();

    $this->actingAs($this->max)->put(route('trips.update', $trip), tripInput(['name' => 'Neu', 'mode' => 'group', 'members' => [$this->anna->id]]))
        ->assertRedirect(route('trips.show', $trip->fresh()));
    expect($trip->fresh()->name)->toBe('Neu');

    $this->actingAs($this->max)->delete(route('trips.destroy', $trip))->assertRedirect(route('home'));
    expect(Trip::find($trip->id))->toBeNull();
});

it('entfernt Mitreisende beim Wechsel auf privat', function () {
    $trip = Trip::factory()->group()->for($this->max, 'owner')->create();
    $trip->addMember($this->anna);

    $this->actingAs($this->max)->put(route('trips.update', $trip), tripInput(['mode' => 'solo']));

    expect($trip->members()->pluck('users.id')->all())->toBe([$this->max->id]);
});

it('speichert das Titelbild privat und zeigt es nur Mitgliedern', function () {
    $this->actingAs($this->anna)->post(route('trips.store'), tripInput([
        'cover' => UploadedFile::fake()->image('lissabon.jpg', 1200, 800),
    ]));

    $trip = Trip::sole();
    Storage::disk('local')->assertExists($trip->cover_path);

    $this->actingAs($this->anna)->get(route('trips.cover', $trip))->assertOk();
    $this->actingAs($this->max)->get(route('trips.cover', $trip))->assertForbidden();
});

it('ersetzt und entfernt das Titelbild', function () {
    $this->actingAs($this->anna)->post(route('trips.store'), tripInput(['cover' => UploadedFile::fake()->image('a.jpg')]));
    $trip = Trip::sole();
    $first = $trip->cover_path;

    $this->actingAs($this->anna)->put(route('trips.update', $trip), tripInput(['cover' => UploadedFile::fake()->image('b.png')]));
    $second = $trip->fresh()->cover_path;
    Storage::disk('local')->assertMissing($first);
    Storage::disk('local')->assertExists($second);

    $this->actingAs($this->anna)->put(route('trips.update', $trip), tripInput(['remove_cover' => '1']));
    expect($trip->fresh()->cover_path)->toBeNull();
    Storage::disk('local')->assertMissing($second);
});

it('akzeptiert nur Bilder als Titelbild', function () {
    $this->actingAs($this->anna)
        ->post(route('trips.store'), tripInput(['cover' => UploadedFile::fake()->create('virus.pdf', 100, 'application/pdf')]))
        ->assertSessionHasErrors('cover');
});

it('zeigt Formular und Detailseite an', function () {
    $this->actingAs($this->anna)->get(route('trips.create', ['mode' => 'group']))->assertOk()->assertSee('Max');

    $this->actingAs($this->anna)->post(route('trips.store'), tripInput());
    $trip = Trip::sole();

    $this->actingAs($this->anna)->get(route('trips.show', $trip))->assertOk()->assertSee('Lissabon');
    $this->actingAs($this->anna)->get(route('trips.edit', $trip))->assertOk();
});

it('erlaubt mehrere Verkehrsmittel und kann sie wieder abwählen', function () {
    $this->actingAs($this->anna)->post(route('trips.store'), tripInput(['transports' => ['plane', 'rental_car', 'plane']]));
    $trip = Trip::sole();

    expect($trip->transports->map->value->all())->toBe(['plane', 'rental_car']);
    $this->actingAs($this->anna)->get(route('trips.show', $trip))->assertSee('Flugzeug + Mietwagen');
    $this->actingAs($this->anna)->get(route('home'))->assertSee('Mietwagen');

    $input = tripInput();
    unset($input['transports']); // nichts angekreuzt: Feld wird gar nicht gesendet
    $this->actingAs($this->anna)->put(route('trips.update', $trip), $input);
    expect($trip->fresh()->transports->all())->toBe([]);

    $this->actingAs($this->anna)->post(route('trips.store'), tripInput(['transports' => ['zeppelin']]))->assertSessionHasErrors('transports.0');
});

it('hat lesbare Adressen, die auch nach dem Umbenennen funktionieren', function () {
    $make = fn (string $name, string $start) => Trip::factory()->for($this->anna, 'owner')->create(['name' => $name, 'start_date' => $start, 'end_date' => $start]);
    $first = $make('Städtetrip Köln', '2026-05-01');
    $second = $make('Städtetrip Köln', '2027-05-01');
    $third = $make('Städtetrip Köln', '2027-09-01');
    $number = $make('2027', '2027-01-01');

    expect([$first->slug, $second->slug, $third->slug, $number->slug])
        ->toBe(['staedtetrip-koeln', 'staedtetrip-koeln-2027', 'staedtetrip-koeln-2027-2', 'reise-2027']);
    expect(route('trips.show', $first))->toEndWith('/reisen/staedtetrip-koeln');

    $this->actingAs($this->anna)->get('/reisen/staedtetrip-koeln')->assertOk()->assertSee('Städtetrip Köln');

    // Alte Nummer und alter Name leiten weiter (Abfrage-Parameter bleiben erhalten).
    $this->actingAs($this->anna)->get('/reisen/'.$first->id.'?tab=outfits')->assertRedirect('/reisen/staedtetrip-koeln?tab=outfits');
    $first->update(['name' => 'Köln im Mai']);
    expect($first->slug)->toBe('koeln-im-mai');
    $this->actingAs($this->anna)->get('/reisen/staedtetrip-koeln')->assertRedirect('/reisen/koeln-im-mai');

    // Die frühere Adresse wird nicht an eine andere Reise vergeben.
    expect($make('Städtetrip Köln', '2028-05-01')->slug)->toBe('staedtetrip-koeln-2028');

    // Fremde sehen auch über die Adresse nichts.
    $this->actingAs($this->max)->get('/reisen/koeln-im-mai')->assertForbidden();
});
