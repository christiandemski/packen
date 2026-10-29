<?php

use App\Enums\PushKind;
use App\Models\Item;
use App\Models\PushSubscription;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\Channels\PushChannel;
use App\Notifications\TripNotification;
use App\Services\PacklistService;
use App\Support\WebPush;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// Nur für Tests – kein echter Schlüssel.
const TEST_VAPID_PEM = <<<'PEM'
-----BEGIN EC PRIVATE KEY-----
MHcCAQEEIPcNON1rzFuKrcCyUcLg4Vj4qTFob4mUjEzZlPRn00KmoAoGCCqGSM49
AwEHoUQDQgAEZTh+aYw4OK0Za7RrW23ZKi/iE0VUsixfVd+FSpKWbzqvCiOGhd1O
uiq3JYqIOBU1fqGWNjVrEBGvm7LRa1Tgiw==
-----END EC PRIVATE KEY-----
PEM;

beforeEach(function () {
    config(['services.webpush.private_key' => base64_encode(TEST_VAPID_PEM), 'services.cron.token' => 'geheim']);
    // 12. Mai 2027, 7:30 Uhr in Berlin (Sommerzeit)
    $this->travelTo(Carbon::parse('2027-05-12 07:30', 'Europe/Berlin'));
    $this->anna = User::factory()->create();
    $this->trip = Trip::factory()->for($this->anna, 'owner')->create(['name' => 'Mallorca', 'start_date' => '2027-05-12', 'end_date' => '2027-05-19']);
    $this->device = PushSubscription::create([
        'user_id' => $this->anna->id,
        'endpoint' => 'https://push.example/abc',
        'endpoint_hash' => PushSubscription::hash('https://push.example/abc'),
    ]);

    $packlists = app(PacklistService::class);
    $charger = Item::factory()->for($this->anna, 'owner')->create(['name' => 'Ladekabel', 'pack_last' => true]);
    $packlists->addEntry($this->trip, $this->anna, ['item_id' => $charger->id]);
    $packlists->addEntry($this->trip, $this->anna, ['custom_name' => 'Herd aus', 'group' => 'tasks']);
    $packlists->addEntry($this->trip, $this->anna, ['custom_name' => 'Buch']);
});

function base64urlDecode(string $value): string
{
    return base64_decode(strtr($value, '-_', '+/'));
}

it('meldet Geräte an und ab', function () {
    $this->actingAs($this->anna)->postJson(route('push.store'), ['endpoint' => 'https://push.example/neu'])->assertCreated();
    $this->actingAs($this->anna)->postJson(route('push.store'), ['endpoint' => 'http://unsicher.example/x'])->assertUnprocessable();
    expect(PushSubscription::where('user_id', $this->anna->id)->count())->toBe(2);

    $this->actingAs($this->anna)->deleteJson(route('push.destroy'), ['endpoint' => 'https://push.example/neu'])->assertOk();
    expect(PushSubscription::count())->toBe(1);
});

it('erinnert am Abfahrtstag einmal mit gültiger VAPID-Signatur', function () {
    Http::fake(['push.example/*' => Http::response('', 201)]);

    $this->get(route('cron.departure', ['token' => 'falsch']))->assertForbidden();
    $this->get(route('cron.departure', ['token' => 'geheim']))->assertOk()->assertJson(['sent' => 1]);
    $this->get(route('cron.departure', ['token' => 'geheim']))->assertJson(['sent' => 0]); // nur einmal

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) {
        expect($request->url())->toBe('https://push.example/abc')
            ->and($request->body())->toBe('')
            ->and($request->header('TTL')[0])->toBe('43200');

        // Authorization: vapid t=<JWT>, k=<öffentlicher Schlüssel>
        preg_match('/^vapid t=([^,]+), k=(.+)$/', $request->header('Authorization')[0], $m);
        [$header, $payload, $signature] = explode('.', $m[1]);
        expect($m[2])->toBe(WebPush::publicKey())
            ->and(json_decode(base64urlDecode($payload), true)['aud'])->toBe('https://push.example');

        // Signatur mit dem öffentlichen Schlüssel prüfen (r‖s → DER)
        $raw = base64urlDecode($signature);
        $int = fn (string $part) => ($part = ltrim($part, "\0")) !== '' && ord($part[0]) > 0x7F ? "\0".$part : $part;
        [$r, $s] = [$int(substr($raw, 0, 32)), $int(substr($raw, 32))];
        $der = "\x30".chr(4 + strlen($r) + strlen($s))."\x02".chr(strlen($r)).$r."\x02".chr(strlen($s)).$s;
        $public = openssl_pkey_get_details(openssl_pkey_get_private(TEST_VAPID_PEM))['key'];

        return openssl_verify("{$header}.{$payload}", $der, $public, OPENSSL_ALGO_SHA256) === 1;
    });

    // Der Service Worker bekommt danach den Text
    $this->actingAs($this->anna)->getJson(route('push.messages'))
        ->assertJsonPath('data.0.title', "Heute geht's los: Mallorca")
        ->assertJsonPath('data.0.body', 'Noch 1 Ding für den Abfahrtstag und 1 Aufgabe offen.')
        ->assertJsonPath('data.0.url', route('trips.show', $this->trip));
});

it('wartet mit jeder Erinnerung bis zur richtigen Uhrzeit', function () {
    Http::fake(['push.example/*' => Http::response('', 201)]);
    $this->travelTo(Carbon::parse('2027-05-12 06:30', 'Europe/Berlin'));

    $this->get(route('cron.departure', ['token' => 'geheim']))->assertJson(['sent' => 0]);
    $this->travelTo(Carbon::parse('2027-05-12 07:05', 'Europe/Berlin'));
    $this->get(route('cron.departure', ['token' => 'geheim']))->assertJson(['sent' => 1]);
});

it('erinnert am Vorabend an alles Offene', function () {
    Http::fake(['push.example/*' => Http::response('', 201)]);
    $this->travelTo(Carbon::parse('2027-05-11 17:30', 'Europe/Berlin'));
    $this->get(route('cron.departure', ['token' => 'geheim']))->assertJson(['sent' => 0]);

    $this->travelTo(Carbon::parse('2027-05-11 18:10', 'Europe/Berlin'));
    $this->get(route('cron.departure', ['token' => 'geheim']))->assertJson(['sent' => 1]);
    $this->actingAs($this->anna)->getJson(route('push.messages'))
        ->assertJsonPath('data.0.title', "Morgen geht's los: Mallorca")
        ->assertJsonPath('data.0.body', 'Noch 3 Dinge auf der Packliste offen.');
});

it('stößt am Tag nach der Reise den Rückblick an', function () {
    Http::fake(['push.example/*' => Http::response('', 201)]);
    $this->travelTo(Carbon::parse('2027-05-20 10:15', 'Europe/Berlin'));

    $this->get(route('cron.departure', ['token' => 'geheim']))->assertJson(['sent' => 1]);
    $this->actingAs($this->anna)->getJson(route('push.messages'))
        ->assertJsonPath('data.0.title', "Wie war's? Mallorca")
        ->assertJsonPath('data.0.url', route('trips.show', ['trip' => $this->trip, 'tab' => 'review']));
});

it('lässt Erledigtes und abgeschaltete Arten weg', function () {
    Http::fake();

    DB::table('packlist_entries')->update(['packed' => true]);
    $this->get(route('cron.departure', ['token' => 'geheim']))->assertJson(['sent' => 0]);

    DB::table('packlist_entries')->update(['packed' => false]);
    $this->actingAs($this->anna)->put(route('notifications.settings.update'), ['push' => ['eve' => '1', 'review' => '1', 'group' => '1']]);
    expect($this->anna->fresh()->wantsPush(PushKind::Departure))->toBeFalse();
    $this->get(route('cron.departure', ['token' => 'geheim']))->assertJson(['sent' => 0]);

    Http::assertNothingSent();
});

it('schickt Hinweise aus der Gruppe auch als Push', function () {
    Http::fake(['push.example/*' => Http::response('', 201)]);
    $notification = new TripNotification($this->trip, 'Max fragt: Hat jemand einen Föhn?', 'request');

    expect($notification->via($this->anna))->toContain(PushChannel::class);
    $this->anna->notify($notification);

    Http::assertSentCount(1);
    $this->actingAs($this->anna)->getJson(route('push.messages'))
        ->assertJsonPath('data.0.title', 'Mallorca')
        ->assertJsonPath('data.0.body', 'Max fragt: Hat jemand einen Föhn?');

    // Abgeschaltet → nur noch in der App
    $this->anna->update(['push_settings' => ['group' => false]]);
    expect($notification->via($this->anna->fresh()))->not->toContain(PushChannel::class);
});

it('entfernt Geräte, die sich beim Push-Dienst abgemeldet haben', function () {
    Http::fake(['push.example/*' => Http::response('', 410)]);

    $this->get(route('cron.departure', ['token' => 'geheim']))->assertJson(['sent' => 0]);

    expect(PushSubscription::count())->toBe(0);
});

it('schickt eine Probe-Nachricht nur an eigene Geräte', function () {
    Http::fake(['push.example/*' => Http::response('', 201)]);
    PushSubscription::create(['user_id' => User::factory()->create()->id, 'endpoint' => 'https://push.example/fremd', 'endpoint_hash' => PushSubscription::hash('https://push.example/fremd')]);

    $this->actingAs($this->anna)->postJson(route('push.test'))->assertJson(['sent' => 1]);
    $this->actingAs($this->anna)->getJson(route('push.messages'))->assertJsonPath('data.0.tag', 'packen-test');
    Http::assertSentCount(1);
});

it('ist ohne Schlüssel abgeschaltet', function () {
    config(['services.webpush.private_key' => null, 'services.cron.token' => null]);

    expect(WebPush::publicKey())->toBeNull();
    $this->actingAs($this->anna)->get(route('notifications.settings'))->assertOk()->assertSee('noch nicht eingerichtet');
    $this->actingAs($this->anna)->postJson(route('push.store'), ['endpoint' => 'https://push.example/x'])->assertNotFound();
    $this->get(route('cron.departure', ['token' => '']))->assertForbidden();
});

it('zeigt die Einstellungen und den Cron-Link nur Admins', function () {
    $this->actingAs($this->anna)->get(route('notifications.settings'))->assertOk()
        ->assertSee(WebPush::publicKey())
        ->assertSeeInOrder(['Hinweise aus der Gruppe', 'Am Vorabend', 'Am Abfahrtstag', 'Rückblick nach der Reise', 'per E-Mail']);
    $this->actingAs($this->anna)->get(route('profile.edit'))->assertSee(route('notifications.settings'));
    $this->actingAs($this->anna)->get(route('admin.index'))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.index'))->assertOk()->assertSee('/cron/abfahrt?token=geheim', false);
});
