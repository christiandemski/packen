<?php

use App\Models\Item;
use App\Models\PushSubscription;
use App\Models\Trip;
use App\Models\User;
use App\Services\PacklistService;
use App\Support\WebPush;
use Illuminate\Http\Client\Request;
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
    $this->travelTo('2027-05-12 07:00');
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
    $this->get(route('cron.departure', ['token' => 'geheim']))->assertJson(['sent' => 0]); // nur einmal am Tag

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
});

it('liefert dem Service Worker den Text und lässt Erledigtes weg', function () {
    $this->actingAs($this->anna)->getJson(route('push.messages'))
        ->assertOk()
        ->assertJsonPath('data.0.title', "Heute geht's los: Mallorca")
        ->assertJsonPath('data.0.body', 'Noch 1 Ding für den Abfahrtstag und 1 Aufgabe offen.')
        ->assertJsonPath('data.0.url', route('trips.show', $this->trip));

    // Alles erledigt → keine Erinnerung
    DB::table('packlist_entries')->update(['packed' => true]);
    Http::fake();
    $this->actingAs($this->anna)->getJson(route('push.messages'))->assertJsonCount(0, 'data');
    $this->get(route('cron.departure', ['token' => 'geheim']))->assertJson(['sent' => 0]);
    Http::assertNothingSent();
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
    $this->actingAs($this->anna)->get(route('profile.edit'))->assertOk()->assertDontSee('Erinnerung am Abfahrtstag');
    $this->actingAs($this->anna)->postJson(route('push.store'), ['endpoint' => 'https://push.example/x'])->assertNotFound();
    $this->get(route('cron.departure', ['token' => '']))->assertForbidden();
});

it('zeigt den Schalter im Profil und den Cron-Link nur Admins', function () {
    $this->actingAs($this->anna)->get(route('profile.edit'))->assertOk()->assertSee('Erinnerung am Abfahrtstag')->assertSee(WebPush::publicKey());
    $this->actingAs($this->anna)->get(route('admin.index'))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.index'))->assertOk()->assertSee('/cron/abfahrt?token=geheim', false);
});
