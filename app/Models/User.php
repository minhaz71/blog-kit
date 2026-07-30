<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    // Auth/security internals are set only by framework flows, never by a
    // form or a request payload — guard them so a careless request()->all()
    // can never verify an email, forge login history, or hijack the public
    // author slug. (is_active / password stay fillable: the admin form sets
    // them, and password is hashed on the way in.)
    protected $guarded = [
        'id', 'email_verified_at', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
        'last_login_at', 'last_login_ip', 'public_slug',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'accepts_marketing' => 'boolean',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'social_links' => 'array',
        ];
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar ? asset('storage/'.ltrim($this->avatar, '/')) : null;
    }

    // ── Public author identity (decoupled from the login account) ──

    protected static function booted(): void
    {
        // Random public slug — never derived from the login name/email, so
        // author URLs can't be used to enumerate or guess admin accounts.
        static::creating(function (self $user): void {
            $user->public_slug ??= strtolower(\Illuminate\Support\Str::random(12));
        });
    }

    /** The name shown publicly (byline, author box, schema). */
    public function publicName(): string
    {
        return trim((string) $this->display_name) ?: (string) $this->name;
    }

    public function authorUrl(): string
    {
        return route('blog.author', $this->public_slug ?: $this->getKey());
    }

    // ── Relationships ──────────────────────────────────────────────

    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function defaultAddress(string $type = 'shipping'): ?Address
    {
        return $this->addresses->where('type', $type)->firstWhere('is_default', true)
            ?? $this->addresses->firstWhere('type', $type);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function wishlist()
    {
        return $this->belongsToMany(Product::class, 'wishlists')->withTimestamps();
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'author_id');
    }

    // ── Customer metrics ───────────────────────────────────────────

    public function lifetimeValue(): float
    {
        return (float) $this->orders()
            ->whereIn('status', ['processing', 'completed'])
            ->sum('total');
    }

    public function orderCount(): int
    {
        return $this->orders()->whereNotIn('status', ['cancelled', 'failed'])->count();
    }

    public function isStaff(): bool
    {
        return $this->roles()->exists();
    }

    // ── Filament ───────────────────────────────────────────────────

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->isStaff();
    }
}
