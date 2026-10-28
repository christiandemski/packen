<?php

use App\Models\Trip;
use App\Models\User;
use App\Services\OutfitService;
use App\Services\PacklistService;
use App\Services\WeatherService;
use App\Support\RainGear;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->travelTo('2027-05-10 09:00');
    $this->anna = User::factory()->create();
    $this->trip = Trip::factory()->for($this->anna, 'owner')->create([
        'location' => 'Lissabon, Portugal', 'start_date' => '2027-05-12', 'end_date' => '2027-05-14',
    ]);
});

function fakeWeather(): void
{
    $codes = [0, 61, 95];
    $min = [14.4, 13.6, 12.0];
    $max = [21.4, 18.5, 17.0];

    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => [['name' => 'Lissabon', 'country' => 'Portugal', 'latitude' => 38.71667, 'longitude' => -9.13333]]]),
        // Liefert für jeden abgefragten Tag Werte (reihum aus den drei Mustertagen).
        'https://api.open-meteo.com/*' => function ($request) use ($codes, $min, $max) {
            $dates = collect(CarbonPeriod::create($request['start_date'], $request['end_date']))->map->toDateString();

            return Http::response(['daily' => [
                'time' => $dates->all(),
                'weather_code' => $dates->keys()->map(fn ($i) => $codes[$i % 3])->all(),
                'temperature_2m_min' => $dates->keys()->map(fn ($i) => $min[$i % 3])->all(),
                'temperature_2m_max' => $dates->keys()->map(fn ($i) => $max[$i % 3])->all(),
                'precipitation_probability_max' => $dates->keys()->map(fn ($i) => [5, 70, 90][$i % 3])->all(),
            ]]);
        },
    ]);
}

it('liefert das Wetter pro Reisetag und sucht nur den Ortsnamen', function () {
    fakeWeather();

    $weather = app(WeatherService::class)->forTrip($this->trip);

    expect($weather['place'])->toBe('Lissabon, Portugal')
        ->and($weather['hint'])->toBeNull()
        ->and($weather['days'])->toHaveCount(3)
        ->and($weather['days']['2027-05-12']->label())->toBe('Sonnig')
        ->and($weather['days']['2027-05-12']->summary())->toBe('14–21 °C')
        ->and($weather['days']['2027-05-13']->summary())->toBe('14–19 °C · Regen 70 %')
        ->and($weather['days']['2027-05-14']->icon())->toBe('cloud-lightning');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'geocoding') && $request['name'] === 'Lissabon');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'forecast') && $request['start_date'] === '2027-05-12' && $request['end_date'] === '2027-05-14');
});

it('zeigt das Wetter im Outfit-Planer', function () {
    fakeWeather();

    $this->actingAs($this->anna)->get(route('trips.show', $this->trip))
        ->assertOk()
        ->assertSee('Wetter für Lissabon, Portugal')
        ->assertSee('Regen · 14–19 °C · Regen 70 %')
        ->assertSee('21°');
});

it('fragt die Vorhersage nur für Tage im Vorhersagezeitraum ab', function () {
    fakeWeather();
    $this->trip->update(['start_date' => '2027-05-20', 'end_date' => '2027-06-02']);

    $weather = app(WeatherService::class)->forTrip($this->trip);

    expect($weather['hint'])->toBe('Für spätere Tage gibt es noch keine Vorhersage.');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'forecast') && $request['end_date'] === '2027-05-25');
});

it('fragt nichts ab, wenn die Reise zu weit weg ist oder kein Ziel hat', function () {
    fakeWeather();
    $this->trip->update(['start_date' => '2027-08-01', 'end_date' => '2027-08-05']);

    expect(app(WeatherService::class)->forTrip($this->trip)['hint'])->toBe('Die Vorhersage gibt es ab 16 Tagen vor der Reise.');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'forecast'));

    $this->trip->update(['location' => null]);
    expect(app(WeatherService::class)->forTrip($this->trip)['hint'])->toBe('Mit einem Reiseziel zeigen wir hier das Wetter.');
});

it('funktioniert weiter, wenn der Wetterdienst ausfällt', function () {
    Http::fake(['*' => Http::response('', 500)]);

    $this->actingAs($this->anna)->get(route('trips.show', $this->trip))->assertOk();

    expect(app(WeatherService::class)->forTrip($this->trip))
        ->days->toBe([])
        ->hint->toBe('Das Wetter ist gerade nicht abrufbar.');
});

it('meldet unbekannte Orte', function () {
    Http::fake(['geocoding-api.open-meteo.com/*' => Http::response(['generationtime_ms' => 0.5])]);
    $this->trip->update(['location' => 'Xyzzyhausen']);

    expect(app(WeatherService::class)->forTrip($this->trip))
        ->place->toBeNull()
        ->hint->toContain('nicht gefunden');
});

it('nimmt für einen Tag mit eigenem Ort dessen Wetter', function () {
    fakeWeather();
    $day = app(OutfitService::class)->days($this->trip, $this->anna)->firstWhere(fn ($d) => $d->date->toDateString() === '2027-05-13');

    $this->actingAs($this->anna)->patch(route('outfits.day', [$this->trip, $day]), ['location' => ' Sintra '])
        ->assertRedirect(route('trips.show', ['trip' => $this->trip, 'tab' => 'outfits', 'tag' => '2027-05-13']));
    expect($day->fresh()->location)->toBe('Sintra');

    $this->actingAs($this->anna)->get(route('trips.show', $this->trip))
        ->assertOk()
        ->assertSee('statt Lissabon, Portugal');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'geocoding') && $request['name'] === 'Sintra');

    $this->actingAs($this->anna)->patch(route('outfits.day', [$this->trip, $day]), ['location' => '']);
    expect($day->fresh()->location)->toBeNull();
});

it('überschreibt Etappen mit dem eigenen Ort und ignoriert Tage außerhalb der Reise', function () {
    fakeWeather();
    $this->trip->stages()->create(['date' => '2027-05-13', 'location' => 'Porto']);

    app(WeatherService::class)->forTrip($this->trip->fresh(), ['2027-05-13' => 'Sintra', '2027-06-01' => 'Faro']);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'geocoding') && $request['name'] === 'Sintra');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'geocoding') && in_array($request['name'], ['Porto', 'Faro'], true));
});

it('warnt bei Regen, wenn kein Regenschutz auf der Liste steht', function () {
    fakeWeather();
    $packlists = app(PacklistService::class);
    $packlists->addEntry($this->trip, $this->anna, ['custom_name' => 'Trainingshose']);

    $this->actingAs($this->anna)->get(route('trips.show', $this->trip))
        ->assertSee('Regen angesagt: Do, 13. Mai, Fr, 14. Mai')
        ->assertSee('Auf deiner Liste steht noch nichts gegen Regen');

    $this->actingAs($this->anna)->post(route('packlist.store', $this->trip), ['custom_name' => 'Regenjacke', 'group' => 'clothing']);

    $this->actingAs($this->anna)->get(route('trips.show', $this->trip))->assertDontSee('Regen angesagt');
});

it('erkennt Regenschutz am Namen', function (string $name, bool $expected) {
    expect(RainGear::matches($name))->toBe($expected);
})->with([
    ['Regenjacke', true], ['Taschenschirm', true], ['Poncho', true], ['Hardshell', true], ['Rain Jacket', true],
    ['Trainingshose', false], ['Regenerationsgel', false], ['Sonnenhut', false],
]);
