<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
});

function dataUrl(string $format = 'jpeg'): string
{
    $file = UploadedFile::fake()->image("bild.{$format}", 256, 256);

    return "data:image/{$format};base64,".base64_encode(file_get_contents($file->getRealPath()));
}

it('speichert ein zugeschnittenes Bild und ersetzt das alte', function () {
    $this->actingAs($this->user)->put(route('profile.avatar'), ['avatar' => dataUrl()])->assertRedirect(route('profile.edit'));
    $first = $this->user->fresh()->avatar_path;
    Storage::disk('local')->assertExists($first);
    expect($first)->toMatch('#^avatars/a-[a-z0-9]{16}\.jpg$#');

    $this->actingAs($this->user)->put(route('profile.avatar'), ['avatar' => dataUrl('png')]);
    $second = $this->user->fresh()->avatar_path;
    Storage::disk('local')->assertMissing($first);
    expect($second)->toEndWith('.png');
});

it('lehnt Daten ab, die kein Bild sind', function () {
    $this->actingAs($this->user)
        ->put(route('profile.avatar'), ['avatar' => 'data:image/png;base64,'.base64_encode('<?php echo 1;')])
        ->assertSessionHasErrors('avatar');

    $this->actingAs($this->user)
        ->put(route('profile.avatar'), ['avatar' => 'data:text/html;base64,'.base64_encode('<b>x</b>')])
        ->assertSessionHasErrors('avatar');

    expect($this->user->fresh()->avatar_path)->toBeNull();
});

it('entfernt das Bild', function () {
    $this->actingAs($this->user)->put(route('profile.avatar'), ['avatar' => dataUrl()]);
    $path = $this->user->fresh()->avatar_path;

    $this->actingAs($this->user)->put(route('profile.avatar'), ['avatar' => '']);

    expect($this->user->fresh()->avatar_path)->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

it('zeigt Bilder nur angemeldeten Personen', function () {
    $this->actingAs($this->user)->put(route('profile.avatar'), ['avatar' => dataUrl()]);
    $url = $this->user->fresh()->avatarUrl();

    $this->actingAs(User::factory()->create())->get($url)->assertOk();
    $this->post(route('logout'));
    $this->get($url)->assertRedirect(route('login'));
});

it('löscht das Bild mit dem Konto', function () {
    $this->actingAs($this->user)->put(route('profile.avatar'), ['avatar' => dataUrl()]);
    $path = $this->user->fresh()->avatar_path;

    $this->actingAs($this->user)->delete(route('profile.destroy'), ['confirm' => '1']);

    Storage::disk('local')->assertMissing($path);
});

it('zeigt ohne Bild die Initialen', function () {
    $this->user->update(['name' => 'Anna Berg']);

    $this->actingAs($this->user)->get(route('profile.edit'))->assertOk()->assertSee('AB');
});
