<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Benutzerbilder wie in „unterwegs“: Der Browser schneidet ein Quadrat (256 px) zu und
 * schickt es als Data-URL. Gespeichert wird privat unter einem zufälligen Namen.
 */
class AvatarService
{
    public const DISK = 'local';

    public const MAX_BYTES = 4 * 1024 * 1024;

    /** @return bool false, wenn die Daten kein gültiges Bild sind */
    public function store(User $user, string $dataUrl): bool
    {
        if (! preg_match('#^data:image/(jpeg|png|webp);base64,#', $dataUrl, $match)) {
            return false;
        }

        $binary = base64_decode(substr($dataUrl, strlen($match[0])), true);
        if ($binary === false || strlen($binary) > self::MAX_BYTES) {
            return false;
        }

        // Die Endung richtet sich nach dem Inhalt, nicht nach der Angabe in der Data-URL.
        $extension = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][@getimagesizefromstring($binary)[2] ?? 0] ?? null;
        if ($extension === null) {
            return false;
        }

        $path = 'avatars/a-'.Str::lower(Str::random(16)).'.'.$extension;
        Storage::disk(self::DISK)->put($path, $binary);

        $this->delete($user);
        $user->forceFill(['avatar_path' => $path])->save();

        return true;
    }

    public function delete(User $user): void
    {
        if ($user->avatar_path !== null) {
            Storage::disk(self::DISK)->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }
    }
}
