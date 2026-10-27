<?php

use App\Models\User;

it('ändert den Namen', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), ['name' => '  Anna B. '])->assertRedirect(route('profile.edit'));

    expect($user->fresh()->name)->toBe('Anna B.');
});

it('löscht das eigene Konto nach Bestätigung', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete(route('profile.destroy'))->assertSessionHasErrors('confirm');
    expect($user->fresh())->not->toBeNull();

    $this->actingAs($user)->delete(route('profile.destroy'), ['confirm' => '1'])->assertRedirect(route('login'));

    expect(User::find($user->id))->toBeNull();
    $this->assertGuest();
});

it('lässt Admins ihr Konto nicht über das Profil löschen', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->delete(route('profile.destroy'), ['confirm' => '1'])->assertForbidden();
});
