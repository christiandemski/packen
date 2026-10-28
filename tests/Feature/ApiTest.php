<?php

use App\Models\ApiToken;
use App\Models\Item;
use App\Models\Trip;
use App\Models\TripStage;
use App\Models\User;
use App\Services\AccountService;
use App\Services\ApiTokenService;
use App\Services\PacklistService;
use App\Services\WeatherService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->anna = User::factory()->create();
    $this->max = User::factory()->create();
    [, $this->token] = app(ApiTokenService::class)->create($this->anna, 'unterwegs');
    $this->api = fn () => $this->withToken($this->token);
});

function tripPayload(array $overrides = []): array
{
    return [
        'external_id' => 'act-42',
        'name' => 'Rheinradweg',
        'start_date' => '2027-06-01',
        'end_date' => '2027-06-04',
        'type' => 'bikepacking',
        'accommodation' => 'camping',
        'transports' => ['bike', 'train'],
        'location' => 'Basel',
        'stages' => [
            ['date' => '2027-06-02', 'location' => 'Straßburg'],
            ['date' => '2027-06-01', 'location' => 'Basel'],
        ],
        ...$overrides,
    ];
}

it('verlangt einen gültigen Token', function () {
    $this->postJson(route('api.trips.store'), tripPayload())->assertUnauthorized();
    $this->withToken('packen_falsch')->postJson(route('api.trips.store'), tripPayload())->assertUnauthorized();

    // Sitzung reicht nicht – die API kennt nur Tokens.
    $this->actingAs($this->anna)->postJson(route('api.trips.store'), tripPayload())->assertUnauthorized();
});

it('legt eine Reise samt Etappen an', function () {
    $response = ($this->api)()->postJson(route('api.trips.store'), tripPayload())->assertCreated();

    $trip = Trip::firstWhere('external_id', 'act-42');
    expect($trip)->owner_id->toBe($this->anna->id)->name->toBe('Rheinradweg')
        ->and($trip->hasMember($this->anna))->toBeTrue();

    $response->assertJsonPath('data.id', $trip->id)
        ->assertJsonPath('data.days', 4)
        ->assertJsonPath('data.nights', 3)
        ->assertJsonPath('data.mode', 'solo')
        ->assertJsonPath('data.transports', ['bike', 'train'])
        ->assertJsonPath('data.stages', [
            ['date' => '2027-06-01', 'location' => 'Basel'],
            ['date' => '2027-06-02', 'location' => 'Straßburg'],
        ])
        ->assertJsonPath('data.url', route('trips.show', $trip));

    expect(ApiToken::first()->last_used_at)->not->toBeNull();
});

it('verdoppelt nichts, wenn dieselbe Reise erneut gesendet wird', function () {
    ($this->api)()->postJson(route('api.trips.store'), tripPayload())->assertCreated();
    ($this->api)()->postJson(route('api.trips.store'), tripPayload(['name' => 'Rheinradweg 2027']))->assertOk();

    expect(Trip::count())->toBe(1)->and(Trip::first()->name)->toBe('Rheinradweg 2027');
});

it('ändert nur mitgeschickte Felder und ersetzt Etappen', function () {
    $id = ($this->api)()->postJson(route('api.trips.store'), tripPayload())->json('data.id');

    ($this->api)()->patchJson(route('api.trips.update', $id), ['end_date' => '2027-06-05', 'stages' => [['date' => '2027-06-05', 'location' => 'Mainz']]])
        ->assertOk()
        ->assertJsonPath('data.name', 'Rheinradweg')
        ->assertJsonPath('data.end_date', '2027-06-05')
        ->assertJsonPath('data.stages', [['date' => '2027-06-05', 'location' => 'Mainz']]);

    // Verkürzen ohne neue Etappen: was außerhalb liegt, fällt weg.
    ($this->api)()->patchJson(route('api.trips.update', $id), ['end_date' => '2027-06-03'])
        ->assertOk()
        ->assertJsonPath('data.stages', []);
});

it('prüft Zeitraum und Etappen', function () {
    ($this->api)()->postJson(route('api.trips.store'), tripPayload(['end_date' => '2027-05-30']))->assertJsonValidationErrors('end_date');
    ($this->api)()->postJson(route('api.trips.store'), tripPayload(['stages' => [['date' => '2027-07-01', 'location' => 'Köln']]]))->assertJsonValidationErrors('stages.0.date');
    ($this->api)()->postJson(route('api.trips.store'), tripPayload(['type' => 'mondfahrt']))->assertJsonValidationErrors('type');

    $id = ($this->api)()->postJson(route('api.trips.store'), tripPayload())->json('data.id');
    ($this->api)()->patchJson(route('api.trips.update', $id), ['start_date' => '2027-06-10'])->assertJsonValidationErrors('end_date');
});

it('nimmt ohne Reiseart „Sonstiges“', function () {
    ($this->api)()->postJson(route('api.trips.store'), ['name' => 'Lauf', 'start_date' => '2027-06-01', 'end_date' => '2027-06-01'])
        ->assertCreated()
        ->assertJsonPath('data.type', 'other');
});

it('schützt fremde Reisen', function () {
    $foreign = Trip::factory()->for($this->max, 'owner')->create();
    $foreign->addMember($this->max);

    ($this->api)()->getJson(route('api.trips.show', $foreign))->assertForbidden();
    ($this->api)()->patchJson(route('api.trips.update', $foreign), ['name' => 'X'])->assertForbidden();
    ($this->api)()->getJson(route('api.trips.summary', $foreign))->assertForbidden();
});

it('liefert Packstand und Gesamtgewicht', function () {
    $id = ($this->api)()->postJson(route('api.trips.store'), tripPayload())->json('data.id');
    $trip = Trip::find($id);
    $stove = Item::factory()->for($this->anna, 'owner')->create(['weight_g' => 400]);
    $packlists = app(PacklistService::class);
    $packlists->addEntry($trip, $this->anna, ['item_id' => $stove->id, 'quantity' => 2])->update(['packed' => true]);
    $packlists->addEntry($trip, $this->anna, ['custom_name' => 'Ladekabel']);

    ($this->api)()->getJson(route('api.trips.summary', $id))
        ->assertOk()
        ->assertJson(['data' => ['packed' => 1, 'total' => 2, 'progress' => '1/2', 'weight_g' => 800, 'weight_complete' => false]]);
});

it('legt Zugänge im Profil an, zeigt sie einmal und widerruft sie', function () {
    $this->actingAs($this->max)->post(route('api-tokens.store'), ['name' => 'unterwegs'])->assertRedirect(route('profile.edit'));
    $plain = session('api_token');
    expect($plain)->toStartWith('packen_');

    $this->actingAs($this->max)->get(route('profile.edit'))->assertSee('unterwegs')->assertSee('noch nie genutzt');
    $this->withToken($plain)->getJson(route('api.trips.summary', Trip::factory()->create()))->assertForbidden(); // gültig, aber fremd

    $token = $this->max->apiTokens()->first();
    $this->actingAs($this->anna)->delete(route('api-tokens.destroy', $token))->assertNotFound();
    $this->actingAs($this->max)->delete(route('api-tokens.destroy', $token))->assertRedirect();
    $this->withToken($plain)->getJson(route('api.trips.summary', 1))->assertUnauthorized();
});

it('sperrt den Zugang gesperrter Konten', function () {
    app(AccountService::class)->block($this->anna);

    ($this->api)()->postJson(route('api.trips.store'), tripPayload())->assertUnauthorized();
    expect($this->anna->apiTokens()->count())->toBe(0);
});

it('holt das Wetter je Etappenort', function () {
    $this->travelTo('2027-05-31 09:00');
    $id = ($this->api)()->postJson(route('api.trips.store'), tripPayload())->json('data.id');

    $daily = fn (array $dates, int $max) => Http::response(['daily' => [
        'time' => $dates,
        'weather_code' => array_fill(0, count($dates), 0),
        'temperature_2m_min' => array_fill(0, count($dates), 10),
        'temperature_2m_max' => array_fill(0, count($dates), $max),
        'precipitation_probability_max' => array_fill(0, count($dates), 0),
    ]]);
    Http::fake(function ($request) use ($daily) {
        if (str_contains($request->url(), 'geocoding')) {
            return Http::response(['results' => [['name' => $request['name'], 'country' => 'X', 'latitude' => $request['name'] === 'Basel' ? 47.5 : 48.5, 'longitude' => 7.6]]]);
        }

        return $request['latitude'] == 47.5
            ? $daily(['2027-06-01', '2027-06-02', '2027-06-03', '2027-06-04'], 20)
            : $daily(['2027-06-02'], 25);
    });

    $weather = app(WeatherService::class)->forTrip(Trip::find($id));

    // 1.6. Basel (Etappe), 2.6. Straßburg (Etappe), 3./4.6. Basel (Reiseziel)
    expect($weather['place'])->toBe('die Etappenorte')
        ->and(collect($weather['days'])->map->max->all())->toBe(['2027-06-01' => 20, '2027-06-02' => 25, '2027-06-03' => 20, '2027-06-04' => 20]);

    $this->actingAs($this->anna)->get(route('trips.show', $id))->assertSee('Straßburg');
});

it('verlinkt zurück zu „unterwegs“, um Etappen dort zu ändern', function () {
    ($this->api)()->postJson(route('api.trips.store'), tripPayload(['external_url' => 'javascript:alert(1)']))->assertJsonValidationErrors('external_url');

    $id = ($this->api)()->postJson(route('api.trips.store'), tripPayload(['external_url' => 'https://unterwegs.example/aktivitaeten/42']))
        ->assertJsonPath('data.external_url', 'https://unterwegs.example/aktivitaeten/42')
        ->json('data.id');

    $this->actingAs($this->anna)->get(route('trips.show', $id))
        ->assertSee('https://unterwegs.example/aktivitaeten/42')
        ->assertSeeText('Basel → Straßburg')
        ->assertSeeText('dort ändern');
    $this->actingAs($this->anna)->get(route('trips.edit', $id))->assertSeeText('Diese Reise kommt aus „unterwegs“');
});

it('löscht eigene Reisen, aber keine fremden', function () {
    $id = ($this->api)()->postJson(route('api.trips.store'), tripPayload())->json('data.id');
    $foreign = Trip::factory()->for($this->max, 'owner')->create();
    $foreign->addMember($this->max);
    $foreign->addMember($this->anna); // Mitreisende darf trotzdem nicht löschen

    ($this->api)()->deleteJson(route('api.trips.destroy', $foreign))->assertForbidden();
    ($this->api)()->deleteJson(route('api.trips.destroy', $id))->assertNoContent();

    expect(Trip::find($id))->toBeNull()
        ->and(Trip::find($foreign->id))->not->toBeNull()
        ->and(TripStage::where('trip_id', $id)->count())->toBe(0);
    ($this->api)()->deleteJson(route('api.trips.destroy', $id))->assertNotFound();
});

it('lehnt das Löschen gemeinsamer Reisen mit Mitreisenden ab', function () {
    $id = ($this->api)()->postJson(route('api.trips.store'), tripPayload())->json('data.id');
    $trip = Trip::find($id);
    $trip->update(['mode' => 'group']);
    $trip->addMember($this->max);

    ($this->api)()->deleteJson(route('api.trips.destroy', $id))
        ->assertStatus(409)
        ->assertJsonPath('url', route('trips.show', $trip));
    expect(Trip::find($id))->not->toBeNull();

    // Ohne Mitreisende geht es wieder.
    $trip->members()->detach($this->max->id);
    ($this->api)()->deleteJson(route('api.trips.destroy', $id))->assertNoContent();
});
