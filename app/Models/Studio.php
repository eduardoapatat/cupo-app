<?php

namespace App\Models;

use App\Concerns\HasUniqueSlug;
use App\Enums\Commune;
use Database\Factories\StudioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Commune $commune
 * @property string $enrollment_token
 * @property int $cancellation_window_hours
 * @property int $booking_cutoff_minutes
 * @property int $waitlist_cutoff_hours
 * @property int $plan_expiry_warning_days
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'commune',
    'cancellation_window_hours',
    'booking_cutoff_minutes',
    'waitlist_cutoff_hours',
    'plan_expiry_warning_days',
])]
class Studio extends Model
{
    /** @use HasFactory<StudioFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * Every studio gets its enrollment token when it is created.
     */
    protected static function booted(): void
    {
        static::creating(function (Studio $studio): void {
            if (blank($studio->enrollment_token)) {
                $studio->enrollment_token = self::newEnrollmentToken();
            }
        });
    }

    /**
     * The rules a new studio starts with, copied from config/studio.php.
     * Once the studio exists, its own copy is the one that applies.
     *
     * @return array{cancellation_window_hours: int, booking_cutoff_minutes: int, waitlist_cutoff_hours: int, plan_expiry_warning_days: int}
     */
    public static function defaultRules(): array
    {
        return [
            'cancellation_window_hours' => config()->integer('studio.defaults.cancellation_window_hours'),
            'booking_cutoff_minutes' => config()->integer('studio.defaults.booking_cutoff_minutes'),
            'waitlist_cutoff_hours' => config()->integer('studio.defaults.waitlist_cutoff_hours'),
            'plan_expiry_warning_days' => config()->integer('studio.defaults.plan_expiry_warning_days'),
        ];
    }

    /**
     * A random token for the studio's public enrollment link.
     */
    public static function newEnrollmentToken(): string
    {
        return Str::random(40);
    }

    /**
     * /estudios/crear is the form to create a studio, so no studio can
     * take "crear" as its slug.
     *
     * @return list<string>
     */
    protected function reservedSlugs(): array
    {
        return ['crear'];
    }

    /**
     * Studios are identified by their slug in URLs (/estudios/{slug}).
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'commune' => Commune::class,
            'cancellation_window_hours' => 'integer',
            'booking_cutoff_minutes' => 'integer',
            'waitlist_cutoff_hours' => 'integer',
            'plan_expiry_warning_days' => 'integer',
        ];
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}
