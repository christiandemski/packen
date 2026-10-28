<?php

use App\Enums\ContainerKind;
use App\Models\Container;
use App\Models\Item;
use App\Models\Trip;
use App\Models\User;
use App\Services\ContainerService;
use App\Services\PacklistService;

beforeEach(function () {
    $this->anna = User::factory()->create(['name' => 'Anna']);
    $this->max = User::factory()->create(['name' => 'Max']);
    $this->trip = Trip::factory()->for($this->anna, 'owner')->group()->create([
        'name' => 'Lissabon', 'location' => 'Lissabon', 'start_date' => '2027-05-12', 'end_date' => '2027-05-16',
    ]);
    $this->trip->addMember($this->anna);
    $this->trip->addMember($this->max);

    $packlists = app(PacklistService::class);
    $socks = Item::factory()->for($this->anna, 'owner')->create(['name' => 'Socken', 'category' => 'underwear']);
    $passport = Item::factory()->for($this->anna, 'owner')->create(['name' => 'Reisepass', 'category' => 'documents', 'pack_last' => true]);
    $packlists->addEntry($this->trip, $this->anna, ['item_id' => $socks->id, 'quantity' => 5]);
    $packlists->addEntry($this->trip, $this->anna, ['item_id' => $passport->id])->update(['packed' => true, 'note' => 'Ausweis reicht nicht']);
    $packlists->addEntry($this->trip, $this->anna, ['custom_name' => 'Ladekabel', 'group' => 'electronics']);
});

it('exportiert die Liste als Text für Notiz-Apps', function () {
    $response = $this->actingAs($this->anna)->get(route('packlist.export', $this->trip))->assertOk();

    expect($response->headers->get('Content-Type'))->toStartWith('text/plain')
        ->and($response->getContent())->toBe(<<<'TXT'
        Lissabon – Packliste
        12.–16. Mai 2027 · 5 Tage · 4 Nächte · Lissabon

        AM ABFAHRTSTAG
        ☑ Reisepass – Ausweis reicht nicht

        KLEIDUNG
        ☐ 5× Socken

        ELEKTRONIK
        ☐ Ladekabel

        TXT);
});

it('exportiert Markdown als Datei und nur offene Einträge', function () {
    $response = $this->actingAs($this->anna)->get(route('packlist.export', ['trip' => $this->trip, 'format' => 'md', 'download' => 1, 'offen' => 1]))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('Packliste%20Lissabon.md')
        ->and($response->getContent())->toContain("# Lissabon – Packliste\n")
        ->toContain("## Kleidung\n- [ ] 5× Socken")
        ->not->toContain('Reisepass');
});

it('ordnet nach Behältnis', function () {
    $box = Container::factory()->for($this->anna, 'owner')->create(['name' => 'Rucksack', 'kind' => ContainerKind::Backpack]);
    $service = app(ContainerService::class);
    $service->setForTrip($this->trip, $this->anna, [$box->id]);
    $service->assign($this->trip, $this->anna, $this->trip->entries()->whereNotNull('item_id')->pluck('id')->all(), $box->id);

    $text = $this->actingAs($this->anna)->get(route('packlist.export', ['trip' => $this->trip, 'behaeltnisse' => 1]))->getContent();

    expect($text)->toContain("RUCKSACK\n☑ Reisepass – Ausweis reicht nicht\n☐ 5× Socken\n\nOHNE BEHÄLTNIS\n☐ Ladekabel");
});

it('zeigt eine Druckansicht', function () {
    $this->actingAs($this->anna)->get(route('packlist.print', $this->trip))
        ->assertOk()
        ->assertSeeText('Lissabon – Packliste')
        ->assertSeeText('Am Abfahrtstag')
        ->assertSeeText('5× Socken')
        ->assertSee('Drucken oder als PDF sichern');
});

it('exportiert fremde Listen nur mit Freigabe', function () {
    $this->actingAs($this->max)->get(route('packlist.export', ['trip' => $this->trip, 'liste' => $this->anna->id]))->assertForbidden();
    $this->actingAs($this->max)->get(route('packlist.print', ['trip' => $this->trip, 'liste' => $this->anna->id]))->assertForbidden();

    DB::table('packlist_grants')->insert(['trip_id' => $this->trip->id, 'owner_id' => $this->anna->id, 'grantee_id' => $this->max->id]);

    $this->actingAs($this->max)->get(route('packlist.export', ['trip' => $this->trip, 'liste' => $this->anna->id]))
        ->assertOk()
        ->assertSeeText('Lissabon – Packliste von Anna');

    $outsider = User::factory()->create();
    $this->actingAs($outsider)->get(route('packlist.export', $this->trip))->assertForbidden();
});

it('bietet den Export in der Packliste an', function () {
    $this->actingAs($this->anna)->get(route('trips.show', $this->trip))
        ->assertOk()
        ->assertSee('Packliste exportieren')
        ->assertSee('Drucken / PDF')
        ->assertSee('Markdown (.md)');
});
