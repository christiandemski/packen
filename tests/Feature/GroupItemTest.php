<?php

use App\Enums\EntrySource;
use App\Models\GroupItem;
use App\Models\PacklistEntry;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\TripNotification;
use App\Services\GroupItemService;
use App\Services\PacklistService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->anna = User::factory()->create(['name' => 'Anna']);
    $this->max = User::factory()->create(['name' => 'Max']);
    $this->lea = User::factory()->create(['name' => 'Lea']);
    $this->trip = Trip::factory()->group()->for($this->anna, 'owner')->create();
    $this->trip->addMember($this->max);
    $this->trip->addMember($this->lea);
    $this->service = app(GroupItemService::class);
});

it('fragt die Gruppe und benachrichtigt alle anderen', function () {
    Notification::fake();

    $this->actingAs($this->anna)->post(route('group-items.store', $this->trip), ['kind' => 'request', 'name' => 'Föhn', 'note' => 'USB-C reicht'])
        ->assertRedirect(route('trips.show', $this->trip));

    $request = GroupItem::sole();
    expect($request)->kind->toBe('request')->status->toBe('open')->requested_by->toBe($this->anna->id);
    Notification::assertSentTo([$this->max, $this->lea], TripNotification::class, fn ($n) => str_contains($n->message, 'Anna fragt'));
    Notification::assertNotSentTo($this->anna, TripNotification::class);

    $this->actingAs($this->max)->get(route('trips.show', $this->trip))->assertSee('Offene Anfragen')->assertSee('Föhn');
});

it('lässt genau eine Person annehmen', function () {
    Notification::fake();
    $request = $this->service->request($this->trip, $this->anna, 'Föhn');

    $this->actingAs($this->max)->post(route('group-items.claim', [$this->trip, $request]))
        ->assertSessionHas('status', fn ($s) => str_contains($s, 'deiner Liste'));
    $this->actingAs($this->lea)->post(route('group-items.claim', [$this->trip, $request]))
        ->assertSessionHas('status', 'Wurde schon übernommen.');

    expect($request->fresh())->status->toBe('claimed')->assigned_user_id->toBe($this->max->id)
        ->and(PacklistEntry::where('group_item_id', $request->id)->count())->toBe(1);

    $entry = PacklistEntry::where('group_item_id', $request->id)->first();
    expect($entry)->user_id->toBe($this->max->id)->note->toBe('für Anna')->source->toBe(EntrySource::GroupItem);
    Notification::assertSentTo($this->anna, TripNotification::class, fn ($n) => $n->message === 'Max bringt „Föhn“ mit.');
});

it('verhindert doppeltes Annehmen auch bei gleichzeitigen Versuchen', function () {
    $request = $this->service->request($this->trip, $this->anna, 'Adapter');

    // Beide haben die Anfrage noch als „offen“ geladen
    $forMax = GroupItem::find($request->id);
    $forLea = GroupItem::find($request->id);

    expect($this->service->claim($forMax, $this->max))->toBeTrue()
        ->and($this->service->claim($forLea, $this->lea))->toBeFalse()
        ->and(PacklistEntry::where('group_item_id', $request->id)->pluck('user_id')->all())->toBe([$this->max->id]);
});

it('lässt die eigene Anfrage nicht selbst annehmen', function () {
    $request = $this->service->request($this->trip, $this->anna, 'Föhn');

    expect($this->service->claim($request, $this->anna))->toBeFalse()
        ->and($request->fresh()->status)->toBe('open');
});

it('blendet den Eintrag der fragenden Person nach dem Annehmen aus', function () {
    $own = app(PacklistService::class)->addEntry($this->trip, $this->anna, ['custom_name' => 'Föhn']);

    $this->actingAs($this->anna)->post(route('group-items.store', $this->trip), ['kind' => 'request', 'name' => 'Föhn', 'source_entry_id' => $own->id]);
    $request = GroupItem::sole();
    expect($request->source_entry_id)->toBe($own->id);

    $this->service->claim($request, $this->max);
    expect($own->fresh()->excluded)->toBeTrue();
    $this->actingAs($this->anna)->get(route('trips.show', $this->trip))->assertSee('Bringt Max mit');

    // Max gibt ab → wieder offen, Annas Eintrag wieder sichtbar, Anna wird informiert
    Notification::fake();
    $this->actingAs($this->max)->post(route('group-items.release', [$this->trip, $request]));
    expect($request->fresh())->status->toBe('open')->assigned_user_id->toBeNull()
        ->and($own->fresh()->excluded)->toBeFalse()
        ->and(PacklistEntry::where('group_item_id', $request->id)->exists())->toBeFalse();
    Notification::assertSentTo($this->anna, TripNotification::class, fn ($n) => $n->kind === 'released');
});

it('zieht Anfragen zurück und informiert, wer sie schon angenommen hat', function () {
    Notification::fake();
    $request = $this->service->request($this->trip, $this->anna, 'Föhn');
    $this->service->claim($request, $this->max);

    $this->actingAs($this->max)->post(route('group-items.withdraw', [$this->trip, $request]))->assertForbidden();
    $this->actingAs($this->anna)->post(route('group-items.withdraw', [$this->trip, $request]))->assertRedirect();

    expect($request->fresh()->status)->toBe('withdrawn')
        ->and(PacklistEntry::where('group_item_id', $request->id)->exists())->toBeFalse();
    Notification::assertSentTo($this->max, TripNotification::class, fn ($n) => $n->kind === 'withdrawn');
});

it('plant gemeinsame Gegenstände und weist sie direkt zu', function () {
    $this->actingAs($this->anna)->post(route('group-items.store', $this->trip), ['kind' => 'planned', 'name' => 'Sonnencreme', 'assigned_user_id' => $this->lea->id]);
    $planned = GroupItem::sole();

    expect($planned)->kind->toBe('planned')->status->toBe('claimed')->assigned_user_id->toBe($this->lea->id)
        ->and(PacklistEntry::where(['group_item_id' => $planned->id, 'user_id' => $this->lea->id])->exists())->toBeTrue();
    $this->actingAs($this->lea)->get(route('trips.show', $this->trip))->assertSee('Für die Gruppe')->assertSee('Bringst du mit');

    // Offen geplant, dann übernimmt Max
    $this->actingAs($this->anna)->post(route('group-items.store', $this->trip), ['kind' => 'planned', 'name' => 'Adapter']);
    $adapter = GroupItem::where('name', 'Adapter')->first();
    $this->actingAs($this->max)->post(route('group-items.claim', [$this->trip, $adapter]));
    expect($adapter->fresh()->assigned_user_id)->toBe($this->max->id);
});

it('gibt beim Entfernen aus der Packliste wieder an die Gruppe ab', function () {
    $request = $this->service->request($this->trip, $this->anna, 'Föhn');
    $this->service->claim($request, $this->max);
    $entry = PacklistEntry::where('group_item_id', $request->id)->first();

    $this->actingAs($this->max)->delete(route('packlist.destroy', [$this->trip, $entry]));

    expect($request->fresh()->status)->toBe('open');
});

it('gibt Übernommenes frei, wenn jemand die Reise verlässt oder sein Konto löscht', function () {
    $a = $this->service->request($this->trip, $this->anna, 'Föhn');
    $b = $this->service->request($this->trip, $this->anna, 'Adapter');
    $this->service->claim($a, $this->max);
    $this->service->claim($b, $this->lea);

    $this->actingAs($this->anna)->put(route('trips.update', $this->trip), [
        'name' => $this->trip->name, 'start_date' => $this->trip->start_date->toDateString(), 'end_date' => $this->trip->end_date->toDateString(),
        'type' => 'citytrip', 'mode' => 'group', 'members' => [$this->lea->id],
    ]);
    expect($a->fresh())->status->toBe('open')->assigned_user_id->toBeNull();

    $this->actingAs($this->lea)->delete(route('profile.destroy'), ['confirm' => '1']);
    expect($b->fresh())->status->toBe('open')->assigned_user_id->toBeNull();
});

it('zeigt gelöschte fragende Personen als „Ehemaliges Mitglied“', function () {
    $request = $this->service->request($this->trip, $this->max, 'Föhn');
    $this->actingAs($this->max)->delete(route('profile.destroy'), ['confirm' => '1']);

    expect($request->fresh()->requesterName())->toBe('Ehemaliges Mitglied');
    $this->actingAs($this->lea)->get(route('trips.show', $this->trip))->assertSee('Ehemaliges Mitglied');
});

it('sperrt Fremde aus', function () {
    $outsider = User::factory()->create();
    $request = $this->service->request($this->trip, $this->anna, 'Föhn');

    $this->actingAs($outsider)->post(route('group-items.store', $this->trip), ['kind' => 'request', 'name' => 'X'])->assertForbidden();
    $this->actingAs($outsider)->post(route('group-items.claim', [$this->trip, $request]))->assertForbidden();

    // Gegenstand einer anderen Reise in der URL
    $other = Trip::factory()->group()->for($this->max, 'owner')->create();
    $this->actingAs($this->max)->post(route('group-items.claim', [$other, $request]))->assertNotFound();
});

it('zeigt Benachrichtigungen mit Zähler und markiert sie beim Ansehen als gelesen', function () {
    $this->service->request($this->trip, $this->anna, 'Föhn');

    $this->actingAs($this->max)->get(route('home'))->assertSee('Benachrichtigungen, 1 neu');
    $this->actingAs($this->max)->get(route('notifications.index'))->assertOk()->assertSee('Anna fragt: Kann jemand „Föhn“ mitnehmen?');
    expect($this->max->fresh()->unreadNotifications()->count())->toBe(0);
});

it('lädt die Benachrichtigungen fürs Popup der Glocke nach', function () {
    $this->service->request($this->trip, $this->anna, 'Föhn');

    $this->actingAs($this->max)->get(route('home'))
        ->assertSee('benachrichtigungen?popup=1', false)
        ->assertSee('Profil &amp; Einstellungen', false);

    // Nur die Liste, ohne Seitenrahmen – und danach gelesen
    $this->actingAs($this->max)->get(route('notifications.index', ['popup' => 1]))
        ->assertOk()
        ->assertSee('Anna fragt: Kann jemand „Föhn“ mitnehmen?')
        ->assertDontSee('<html', false);
    expect($this->max->fresh()->unreadNotifications()->count())->toBe(0);
});

it('verschickt E-Mails nur, wenn eingeschaltet', function () {
    $notification = new TripNotification($this->trip, 'Test', 'request');

    expect($notification->via($this->max))->toBe(['database']);

    $this->actingAs($this->max)->patch(route('profile.update'), ['name' => 'Max', 'email_notifications' => '1']);
    expect($notification->via($this->max->fresh()))->toBe(['database', 'mail']);
});
