<?php

namespace App\Models;

use App\Enums\StudioRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $phone
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $adult_declared_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'phone', 'adult_declared_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'memberships'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'two_factor_confirmed_at' => 'datetime',
            'adult_declared_at' => 'datetime',
        ];
    }

    /**
     * Whether the person can sign in. A student enrolled at the counter has
     * no password until she activates her account.
     */
    public function usesApp(): bool
    {
        return $this->password !== null;
    }

    /**
     * Users with the given email, ignoring case: people type it either way,
     * and accounts keep it as they registered it.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithEmail(Builder $query, string $email): void
    {
        $query->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))]);
    }

    /**
     * People whose name (ignoring case and accents) or email contains the
     * search, or whose phone contains its digits. LIKE wildcards are
     * searched literally.
     *
     * @param  Builder<self>  $query
     */
    public function scopeMatchingSearch(Builder $query, string $search): void
    {
        $pattern = '%'.addcslashes($search, '%_\\').'%';
        $digits = preg_replace('/\D/', '', $search) ?? '';

        $query->where(function (Builder $query) use ($pattern, $digits): void {
            $query->whereRaw('unaccent(users.name) ilike unaccent(?)', [$pattern])
                ->orWhere('users.email', 'ilike', $pattern);

            if (strlen($digits) >= 3) {
                $query->orWhere('users.phone', 'like', '%'.$digits.'%');
            }
        });
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @return HasMany<WaitlistEntry, $this>
     */
    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class);
    }

    /**
     * The user's role in the given studio, or null when they are not a
     * member. It reads the loaded memberships, so repeated checks in one
     * request run a single query.
     */
    public function roleIn(Studio $studio): ?StudioRole
    {
        return $this->memberships->firstWhere('studio_id', $studio->id)?->role;
    }
}
