<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'github_id',
        'apple_id',
        'auth_provider',
        'social_metadata',
        'current_organization_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'github_id',
        'apple_id',
        'social_metadata',
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
            'social_metadata' => 'array',
        ];
    }

    public function organizations(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot('role', 'accepted_at')
            ->withTimestamps();
    }

    public function currentOrganization(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function switchOrganization(Organization $organization): void
    {
        $this->update(['current_organization_id' => $organization->id]);
    }

    public function ownsOrganization(Organization $organization): bool
    {
        return $this->organizations()
            ->wherePivot('role', 'owner')
            ->where('organizations.id', $organization->id)
            ->exists();
    }

    public function roleInOrganization(Organization $organization): ?string
    {
        $membership = $this->organizations()
            ->where('organizations.id', $organization->id)
            ->first();

        return $membership?->pivot->role;
    }

    public function hasSocialProvider(string $provider): bool
    {
        return match ($provider) {
            'github' => ! empty($this->github_id),
            'apple' => ! empty($this->apple_id),
            default => false,
        };
    }

    public function hasPassword(): bool
    {
        return ! empty($this->password);
    }
}
