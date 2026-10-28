<?php

namespace App\Models;

use App\Enums\AuthSource;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'auth_source',
        'bearid_sub',
        'role',
        'active',
        'password',
        'email_notifications',
        'starter_templates_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'auth_source' => AuthSource::class,
            'role' => Role::class,
            'active' => 'boolean',
            'email_notifications' => 'boolean',
            'last_login_at' => 'datetime',
            'starter_templates_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path === null ? null : route('users.avatar', ['user' => $this, 'v' => pathinfo($this->avatar_path, PATHINFO_FILENAME)]);
    }

    /** Farbton für die Initialen, wie in „unterwegs“ aus der E-Mail-Adresse abgeleitet. */
    public function avatarHue(): int
    {
        $hue = 0;
        foreach (mb_str_split($this->email ?: $this->name) as $char) {
            $hue = ($hue * 31 + mb_ord($char)) % 360;
        }

        return $hue;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];

        return Str::upper(collect($parts)->take(2)->map(fn ($part) => Str::substr($part, 0, 1))->implode(''));
    }

    /** @return BelongsToMany<Trip, $this> */
    public function trips(): BelongsToMany
    {
        return $this->belongsToMany(Trip::class, 'trip_members')
            ->using(TripMember::class)
            ->withPivot(['role', 'outfits_shared', 'joined_at']);
    }

    /** @return HasMany<Trip, $this> */
    public function ownedTrips(): HasMany
    {
        return $this->hasMany(Trip::class, 'owner_id');
    }

    /** @return HasMany<Item, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'owner_id');
    }

    /** @return HasMany<Container, $this> */
    public function containers(): HasMany
    {
        return $this->hasMany(Container::class, 'owner_id');
    }

    /** @return HasMany<Template, $this> */
    public function templates(): HasMany
    {
        return $this->hasMany(Template::class, 'owner_id');
    }

    /** @return HasMany<LoginToken, $this> */
    public function loginTokens(): HasMany
    {
        return $this->hasMany(LoginToken::class);
    }

    /** @return HasMany<ApiToken, $this> */
    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }
}
