<?php

use App\Models\Trip;
use Illuminate\Support\Carbon;

function trip(string $start, string $end): Trip
{
    return new Trip(['start_date' => $start, 'end_date' => $end]);
}

beforeEach(fn () => $this->travelTo(Carbon::parse('2026-05-01')));

it('formatiert Zeiträume deutsch', function (string $start, string $end, string $label) {
    expect(trip($start, $end)->dateRangeLabel())->toBe($label);
})->with([
    'gleicher Monat' => ['2026-05-12', '2026-05-16', '12.–16. Mai'],
    'ein Tag' => ['2026-05-12', '2026-05-12', '12. Mai'],
    'Monatswechsel' => ['2026-05-28', '2026-06-03', '28. Mai – 3. Juni'],
    'anderes Jahr' => ['2027-03-02', '2027-03-05', '2.–5. März 2027'],
    'Jahreswechsel' => ['2026-12-28', '2027-01-03', '28. Dez 2026 – 3. Jan 2027'],
]);

it('zählt Tage inklusive An- und Abreise', function () {
    expect(trip('2026-05-12', '2026-05-16')->days())->toBe(5);
});

it('zählt Nächte und beschreibt die Dauer', function () {
    expect(trip('2026-05-12', '2026-05-16')->nights())->toBe(4)
        ->and(trip('2026-05-12', '2026-05-16')->durationLabel())->toBe('5 Tage · 4 Nächte')
        ->and(trip('2026-05-12', '2026-05-13')->durationLabel())->toBe('2 Tage · 1 Nacht')
        ->and(trip('2026-05-12', '2026-05-12')->durationLabel())->toBe('1 Tag');
});

it('beschreibt den Stand der Reise', function () {
    expect(trip('2026-05-02', '2026-05-04')->countdownLabel())->toBe('Morgen')
        ->and(trip('2026-05-13', '2026-05-16')->countdownLabel())->toBe('in 12 Tagen')
        ->and(trip('2026-04-30', '2026-05-04')->countdownLabel())->toBe('Tag 2 von 5')
        ->and(trip('2026-04-20', '2026-04-25')->countdownLabel())->toBe('Vorbei');
});
