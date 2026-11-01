<?php

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Eigene Reiseart, z. B. „Geschäftsreise“ oder „Festival“. */
class CustomTripType extends Model
{
    use OwnedByUser;

    /** Schlüssel-Präfix, mit dem eigene Reisearten neben den festen auftauchen („t:12“). */
    public const PREFIX = 't:';

    /** Zur Auswahl stehende Icons (siehe x-icon). */
    public const ICONS = [
        'briefcase', 'building', 'house', 'heart', 'gift', 'star',
        'music', 'camera', 'book-open', 'utensils', 'coffee', 'flame',
        'mountain', 'tent', 'footprints', 'bike', 'dumbbell', 'trophy',
        'waves', 'umbrella', 'sun', 'snowflake', 'compass', 'map-pin',
        'plane', 'train', 'car', 'bus', 'ship', 'backpack',
    ];

    /** Verläufe für Bilder ohne eigenes Foto, passend zu den festen Reisearten. */
    public const COLORS = [
        'pine' => 'linear-gradient(135deg, #17211E 0%, #0E6B5E 100%)',
        'sea' => 'linear-gradient(135deg, #0A5248 0%, #2B8A7C 100%)',
        'forest' => 'linear-gradient(135deg, #263D30 0%, #55704A 100%)',
        'rust' => 'linear-gradient(135deg, #5E2A0E 0%, #B04F18 100%)',
        'sand' => 'linear-gradient(135deg, #5C4318 0%, #A9802F 100%)',
        'rose' => 'linear-gradient(135deg, #521C35 0%, #A4466C 100%)',
        'violet' => 'linear-gradient(135deg, #33284F 0%, #5F4A8C 100%)',
        'ice' => 'linear-gradient(135deg, #1F3A54 0%, #47729A 100%)',
        'stone' => 'linear-gradient(135deg, #17211E 0%, #4A5652 100%)',
    ];

    protected $fillable = ['owner_id', 'name', 'icon', 'color'];

    /** @return HasMany<Trip, $this> */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function key(): string
    {
        return self::PREFIX.$this->id;
    }

    public static function idFromKey(?string $key): ?int
    {
        return $key !== null && str_starts_with($key, self::PREFIX) ? ((int) substr($key, strlen(self::PREFIX)) ?: null) : null;
    }

    public function gradient(): string
    {
        return self::COLORS[$this->color] ?? self::COLORS['stone'];
    }
}
