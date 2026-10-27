<?php

use App\Enums\TripMemberRole;
use App\Enums\TripMode;
use App\Models\Trip;
use App\Models\User;
use App\Services\AccountService;
use App\Services\TripService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->friend = User::factory()->create(['email' => 'anna@example.com']);
});

it('sperrt und entsperrt Freunde', function () {
    $this->actingAs($this->admin)->post(route('admin.users.block', $this->friend))->assertRedirect(route('admin.index'));
    expect($this->friend->fresh()->active)->toBeFalse();

    $this->actingAs($this->admin)->post(route('admin.users.unblock', $this->friend));
    expect($this->friend->fresh()->active)->toBeTrue();
});

it('wirft gesperrte Personen aus laufenden Sitzungen', function () {
    $this->actingAs($this->friend)->get(route('home'))->assertOk();

    $this->friend->update(['active' => false]);

    $this->actingAs($this->friend)->get(route('home'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('beendet beim Sperren alle gespeicherten Sitzungen', function () {
    config(['session.driver' => 'database']);
    DB::table('sessions')->insert([
        'id' => 'abc', 'user_id' => $this->friend->id, 'payload' => '', 'last_activity' => time(),
    ]);
    $rememberToken = $this->friend->remember_token;

    app(AccountService::class)->block($this->friend);

    expect(DB::table('sessions')->where('user_id', $this->friend->id)->count())->toBe(0)
        ->and($this->friend->fresh()->remember_token)->not->toBe($rememberToken);
});

it('verbietet Freunden das Sperren und Löschen', function () {
    $other = User::factory()->create();

    $this->actingAs($this->friend)->post(route('admin.users.block', $other))->assertForbidden();
    $this->actingAs($this->friend)->delete(route('admin.users.destroy', $other), ['confirm_email' => $other->email])->assertForbidden();
});

it('lässt den Admin sich nicht selbst sperren oder löschen', function () {
    $this->actingAs($this->admin)->post(route('admin.users.block', $this->admin))->assertForbidden();
    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin), ['confirm_email' => $this->admin->email])->assertForbidden();
});

it('löscht nur mit bestätigter E-Mail-Adresse', function () {
    $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $this->friend), ['confirm_email' => 'falsch@example.com'])
        ->assertSessionHasErrorsIn('delete-'.$this->friend->id, 'confirm_email');

    expect($this->friend->fresh())->not->toBeNull();
});

it('löscht Konten samt privaten Reisen und übergibt gemeinsame Reisen', function () {
    $trips = app(TripService::class);
    $max = User::factory()->create();
    $solo = $trips->create($this->friend, Trip::factory()->raw(['mode' => TripMode::Solo->value]));
    $group = $trips->create($this->friend, Trip::factory()->raw(['mode' => TripMode::Group->value]), memberIds: [$max->id]);

    $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $this->friend), ['confirm_email' => 'anna@example.com'])
        ->assertRedirect(route('admin.index'));

    expect(User::find($this->friend->id))->toBeNull()
        ->and(Trip::find($solo->id))->toBeNull();

    $group->refresh();
    expect($group->owner_id)->toBe($max->id)
        ->and($group->members()->pluck('users.id')->all())->toBe([$max->id])
        ->and($group->members->first()->pivot->role)->toBe(TripMemberRole::Owner);
});
