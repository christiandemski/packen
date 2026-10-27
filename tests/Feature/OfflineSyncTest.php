<?php

use App\Models\Trip;
use App\Models\User;
use App\Services\PacklistService;

beforeEach(function () {
    $this->anna = User::factory()->create();
    $this->trip = Trip::factory()->for($this->anna, 'owner')->create();
    $this->entry = app(PacklistService::class)->addEntry($this->trip, $this->anna, ['custom_name' => 'Zahnbürste']);
    $this->url = route('packlist.toggle', [$this->trip, $this->entry]);
});

it('übernimmt eine Offline-Änderung mit Gerätezeit', function () {
    $this->travelTo('2026-11-01 12:00:00');

    $this->actingAs($this->anna)
        ->patchJson($this->url, ['packed' => true, 'changed_at' => '2026-11-01T11:30:00Z'])
        ->assertOk()
        ->assertJson(['applied' => true, 'packed' => true]);

    expect($this->entry->fresh()->packed_changed_at->toIso8601ZuluString())->toBe('2026-11-01T11:30:00Z');
});

it('verwirft ältere Änderungen – letzter Stand gewinnt', function () {
    $this->travelTo('2026-11-01 12:00:00');

    // Handy (später, schon synchronisiert): abgehakt um 11:50
    $this->actingAs($this->anna)->patchJson($this->url, ['packed' => true, 'changed_at' => '2026-11-01T11:50:00Z']);

    // Tablet war offline und hatte um 11:40 „nicht gepackt“ gesetzt – kommt jetzt erst an.
    $this->actingAs($this->anna)
        ->patchJson($this->url, ['packed' => false, 'changed_at' => '2026-11-01T11:40:00Z'])
        ->assertOk()
        ->assertJson(['applied' => false, 'packed' => true]);

    expect($this->entry->fresh()->packed)->toBeTrue();
});

it('akzeptiert keine Zeitpunkte in der Zukunft', function () {
    $this->travelTo('2026-11-01 12:00:00');

    $this->actingAs($this->anna)->patchJson($this->url, ['packed' => true, 'changed_at' => '2030-01-01T00:00:00Z']);
    expect($this->entry->fresh()->packed_changed_at->toDateTimeString())->toBe('2026-11-01 12:00:00');

    // Eine falsch gehende Uhr darf spätere Änderungen nicht blockieren.
    $this->travelTo('2026-11-01 12:05:00');
    $this->actingAs($this->anna)->patchJson($this->url, ['packed' => false])->assertJson(['applied' => true, 'packed' => false]);
});

it('behält den ersten Pack-Zeitpunkt bei doppeltem Abhaken', function () {
    $this->travelTo('2026-11-01 12:00:00');
    $this->actingAs($this->anna)->patchJson($this->url, ['packed' => true]);

    $this->travelTo('2026-11-01 12:10:00');
    $this->actingAs($this->anna)->patchJson($this->url, ['packed' => true]);

    expect($this->entry->fresh()->packed_at->toDateTimeString())->toBe('2026-11-01 12:00:00');
});

it('kennzeichnet auch Fehlerantworten der App', function () {
    // Der Service Worker unterscheidet so eine echte 404 der App von der eines Routers oder Proxys.
    $this->actingAs($this->anna)->get('/reisen/999999')->assertNotFound()->assertHeader('X-Packen', '1');
    $this->actingAs($this->anna)->patchJson(route('packlist.toggle', [$this->trip, 999999]), ['packed' => true])->assertHeader('X-Packen', '1');
});

it('liefert angemeldeten Personen einen frischen CSRF-Token', function () {
    $this->actingAs($this->anna)->getJson(route('csrf'))->assertOk()->assertJsonStructure(['token'])
        ->assertHeader('Cache-Control', 'no-store, private');

    $this->post(route('logout'));
    $this->getJson(route('csrf'))->assertUnauthorized();
});

it('bindet Manifest und Service Worker ein', function () {
    expect(file_exists(public_path('sw.js')))->toBeTrue()
        ->and(json_decode(file_get_contents(public_path('manifest.webmanifest')), true)['start_url'])->toBe('/');

    $this->actingAs($this->anna)->get(route('home'))
        ->assertHeader('X-Packen', '1')
        ->assertSee('rel="manifest"', false)
        ->assertSee('data-auth="1"', false)
        ->assertSee('data-offline-prefetch', false);
});
