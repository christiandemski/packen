<?php

namespace App\Support;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Web Push ohne Zusatzbibliothek: Wir schicken nur ein leeres Signal (VAPID-signiert),
 * der Service Worker holt den Text danach selbst bei der App ab (/push/hinweise).
 * So entfällt die Verschlüsselung des Inhalts.
 *
 * Schlüssel: VAPID_PRIVATE_KEY = base64(PEM eines P-256-Schlüssels), siehe packen:push-einrichten.
 */
final class WebPush
{
    public static function enabled(): bool
    {
        return self::privateKey() !== null;
    }

    /** Öffentlicher Schlüssel für pushManager.subscribe() (base64url, unkomprimierter Punkt). */
    public static function publicKey(): ?string
    {
        $key = self::privateKey();
        if ($key === null) {
            return null;
        }

        $ec = openssl_pkey_get_details($key)['ec'];

        return self::base64url("\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT));
    }

    /**
     * Schickt das Signal an ein Gerät. Abgemeldete Geräte (404/410) werden entfernt.
     *
     * @return bool ob der Push-Dienst das Signal angenommen hat
     */
    public static function send(PushSubscription $subscription): bool
    {
        $publicKey = self::publicKey();
        if ($publicKey === null) {
            return false;
        }

        $parts = parse_url($subscription->endpoint);
        $audience = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'TTL' => '43200',
                    'Urgency' => 'high',
                    'Authorization' => 'vapid t='.self::jwt($audience).', k='.$publicKey,
                ])
                ->withBody('', 'application/octet-stream')
                ->post($subscription->endpoint);
        } catch (Throwable) {
            return false;
        }

        if (in_array($response->status(), [404, 410], true)) {
            $subscription->delete();
        }

        return $response->successful();
    }

    private static function jwt(string $audience): string
    {
        $header = self::base64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = self::base64url(json_encode([
            'aud' => $audience,
            'exp' => time() + 12 * 3600,
            'sub' => config('services.webpush.subject'),
        ]));

        openssl_sign("{$header}.{$payload}", $der, self::privateKey(), OPENSSL_ALGO_SHA256);

        return "{$header}.{$payload}.".self::base64url(self::derToRaw($der));
    }

    /** ES256 erwartet r‖s mit je 32 Byte, OpenSSL liefert DER: SEQUENCE { INTEGER r, INTEGER s }. */
    private static function derToRaw(string $der): string
    {
        $offset = 2;
        $raw = '';
        for ($i = 0; $i < 2; $i++) {
            $length = ord($der[$offset + 1]);
            $raw .= str_pad(ltrim(substr($der, $offset + 2, $length), "\0"), 32, "\0", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return $raw;
    }

    private static function privateKey(): ?\OpenSSLAsymmetricKey
    {
        $encoded = config('services.webpush.private_key');
        if (blank($encoded)) {
            return null;
        }

        return openssl_pkey_get_private((string) base64_decode($encoded, true)) ?: null;
    }

    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
