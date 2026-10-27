<?php

use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\Trip;
use App\Models\User;
use App\Services\OutfitService;
use App\Services\PacklistService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->anna = User::factory()->create(['name' => 'Anna']);
    $this->max = User::factory()->create(['name' => 'Max']);
    $this->lea = User::factory()->create(['name' => 'Lea']);
    $this->trip = Trip::factory()->group()->for($this->anna, 'owner')->create();
    $this->trip->addMember($this->max);
    $this->trip->addMember($this->lea);
    $this->entry = app(PacklistService::class)->addEntry($this->trip, $this->anna, ['custom_name' => 'Annas Kamera']);
});

it('lässt freigegebene Mitreisende sehen und abhaken – sonst niemanden', function () {
    $this->actingAs($this->max)->get(route('trips.show', ['trip' => $this->trip, 'liste' => $this->anna->id]))->assertForbidden();
    $this->actingAs($this->max)->patchJson(route('packlist.toggle', [$this->trip, $this->entry]), ['packed' => true])->assertForbidden();

    $this->actingAs($this->anna)->put(route('sharing.packlist', $this->trip), ['grantee_ids' => [$this->max->id]]);

    $this->actingAs($this->max)->get(route('trips.show', $this->trip))->assertSee('Meine Liste');
    $this->actingAs($this->max)->get(route('trips.show', ['trip' => $this->trip, 'liste' => $this->anna->id]))
        ->assertOk()->assertSee('Annas Kamera')->assertSee('Du packst für Anna mit');

    $this->actingAs($this->max)->patchJson(route('packlist.toggle', [$this->trip, $this->entry]), ['packed' => true])->assertOk();
    expect($this->entry->fresh())->packed->toBeTrue()->packed_by->toBe($this->max->id);
    $this->actingAs($this->anna)->get(route('trips.show', $this->trip))->assertSee('gepackt von Max');

    // Abhaken ja, ändern nein
    $this->actingAs($this->max)->patch(route('packlist.update', [$this->trip, $this->entry]), ['quantity' => 5])->assertForbidden();
    $this->actingAs($this->max)->delete(route('packlist.destroy', [$this->trip, $this->entry]))->assertForbidden();

    // Lea hat keine Freigabe
    $this->actingAs($this->lea)->patchJson(route('packlist.toggle', [$this->trip, $this->entry]), ['packed' => false])->assertForbidden();
});

it('gilt nur in eine Richtung und lässt sich zurücknehmen', function () {
    $this->actingAs($this->anna)->put(route('sharing.packlist', $this->trip), ['grantee_ids' => [$this->max->id]]);
    $maxEntry = app(PacklistService::class)->addEntry($this->trip, $this->max, ['custom_name' => 'Max’ Pass']);

    $this->actingAs($this->anna)->patchJson(route('packlist.toggle', [$this->trip, $maxEntry]), ['packed' => true])->assertForbidden();

    $this->actingAs($this->anna)->put(route('sharing.packlist', $this->trip), ['grantee_ids' => []]);
    $this->actingAs($this->max)->patchJson(route('packlist.toggle', [$this->trip, $this->entry]), ['packed' => true])->assertForbidden();
});

it('gibt nur an Mitreisende frei', function () {
    $outsider = User::factory()->create();

    $this->actingAs($this->anna)->put(route('sharing.packlist', $this->trip), ['grantee_ids' => [$outsider->id]]);

    $this->actingAs($outsider)->patchJson(route('packlist.toggle', [$this->trip, $this->entry]), ['packed' => true])->assertForbidden();
});

it('zeigt freigegebene Outfits lesend', function () {
    $days = app(OutfitService::class)->days($this->trip, $this->anna);
    $dress = $this->anna->items()->create(['name' => 'Sommerkleid', 'category' => 'tops']);
    $days->first()->slots->first()->items()->attach($dress);

    $this->actingAs($this->max)->get(route('trips.show', ['trip' => $this->trip, 'tab' => 'outfits']))->assertDontSee('Sommerkleid');

    $this->actingAs($this->anna)->put(route('sharing.outfits', $this->trip), ['shared' => '1']);
    $this->actingAs($this->max)->get(route('trips.show', ['trip' => $this->trip, 'tab' => 'outfits']))->assertSee('Sommerkleid');

    $this->actingAs($this->anna)->put(route('sharing.outfits', $this->trip), ['shared' => '0']);
    $this->actingAs($this->max)->get(route('trips.show', ['trip' => $this->trip, 'tab' => 'outfits']))->assertDontSee('Sommerkleid');
});

it('lädt als Admin neue Personen direkt in eine Reise ein', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    $trip = Trip::factory()->group()->for($admin, 'owner')->create(['name' => 'Porto']);

    $this->actingAs($this->anna)->post(route('trips.invite', $this->trip), ['name' => 'Neu', 'email' => 'neu@example.com'])->assertForbidden();

    $this->actingAs($admin)->post(route('trips.invite', $trip), ['name' => 'Neu', 'email' => 'neu@example.com'])
        ->assertRedirect(route('trips.edit', $trip));
    $this->post(route('logout'));

    $token = Mail::sent(InvitationMail::class)->last()->token;
    expect(Invitation::sole()->trip_id)->toBe($trip->id);

    $this->post(route('invitation.accept', $token), ['name' => 'Neu']);
    $user = User::where('email', 'neu@example.com')->first();
    expect($trip->hasMember($user))->toBeTrue();
    $this->actingAs($user)->get(route('home'))->assertSee('Porto');
});
