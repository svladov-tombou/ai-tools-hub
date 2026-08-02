<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * A column default is a DATABASE default: Eloquent does not read it back after an
     * insert, so a freshly created User would carry `is_active = null` in memory while
     * the stored row says 1. That null is falsy, so `! $user->is_active` would read a
     * brand-new user as deactivated, and the JSON returned by a create endpoint would
     * say null where the database says true. This keeps model and schema in agreement.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // Deliberately NOT in #[Fillable]: the flag is changed only by a dedicated,
            // authorized action, never by whatever a request body happens to carry.
            'is_active' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $name): bool
    {
        return $this->roles->contains('name', $name);
    }

    public function hasAtLeastLevel(int $level): bool
    {
        return $this->roles->max('level') >= $level;
    }

    public function highestRole(): ?Role
    {
        return $this->roles->sortByDesc('level')->first();
    }

    public function createdTools(): HasMany
    {
        return $this->hasMany(Tool::class, 'created_by');
    }
}
