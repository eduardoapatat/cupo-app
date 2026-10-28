<?php

namespace App\Models;

use App\Enums\ClassSessionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ClassSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A concrete class, such as «pilates mat, Tuesday 4 at 19:00», usually
 * generated from a schedule slot. Times are stored in UTC.
 *
 * @property int $id
 * @property int $studio_id
 * @property int $class_type_id
 * @property int|null $schedule_slot_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property int $capacity
 * @property int $booked_count
 * @property int|null $instructor_id
 * @property ClassSessionStatus $status
 * @property CarbonImmutable|null $cancelled_at
 * @property int|null $cancelled_by_id
 * @property string|null $cancellation_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['class_type_id', 'schedule_slot_id', 'starts_at', 'ends_at', 'capacity', 'instructor_id'])]
class ClassSession extends Model
{
    /** @use HasFactory<ClassSessionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
            'booked_count' => 'integer',
            'status' => ClassSessionStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Studio, $this>
     */
    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    /**
     * @return BelongsTo<ClassType, $this>
     */
    public function classType(): BelongsTo
    {
        return $this->belongsTo(ClassType::class);
    }

    /**
     * @return BelongsTo<ScheduleSlot, $this>
     */
    public function scheduleSlot(): BelongsTo
    {
        return $this->belongsTo(ScheduleSlot::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
     * When the session starts, in the studio's time zone, for showing it.
     */
    public function localStartsAt(): CarbonImmutable
    {
        return $this->starts_at->timezone(config()->string('studio.timezone'));
    }

    /**
     * The last moment to book it: the studio's cutoff before it starts.
     */
    public function bookingClosesAt(): CarbonImmutable
    {
        return $this->starts_at->subMinutes($this->studio->booking_cutoff_minutes);
    }

    /**
     * The last moment to cancel it without losing the class: the studio's
     * cancellation window before it starts.
     */
    public function freeCancellationEndsAt(): CarbonImmutable
    {
        return $this->starts_at->subHours($this->studio->cancellation_window_hours);
    }

    /**
     * The last moment a freed spot goes to the first one waiting: the
     * studio's waitlist cutoff before it starts, and never after bookings
     * close. Later, the spot stays open for anyone, so joining the waitlist
     * closes then too.
     */
    public function waitlistClosesAt(): CarbonImmutable
    {
        return $this->starts_at->subHours($this->studio->waitlist_cutoff_hours)->min($this->bookingClosesAt());
    }

    /**
     * Until when cancelling keeps the class, in words: «las 13:00», or «el
     * lunes 2 a las 19:00» when that falls on an earlier day.
     */
    public function freeCancellationLabel(): string
    {
        $deadline = $this->freeCancellationEndsAt()->timezone(config()->string('studio.timezone'));

        return $deadline->isSameDay($this->localStartsAt())
            ? __('at :time', ['time' => $deadline->format('H:i')])
            : __('on :day at :time', ['day' => $deadline->translatedFormat('l j'), 'time' => $deadline->format('H:i')]);
    }

    /**
     * The last moment to mark who missed it: the end of the day after the
     * class, in the studio's time zone, so a forgotten mark can be fixed
     * the next day. Then the attendance closes (attendance:close).
     */
    public function attendanceClosesAt(): CarbonImmutable
    {
        return $this->localStartsAt()->addDay()->endOfDay()->utc();
    }

    /**
     * Whether its attendance can be marked now: from its start until
     * attendanceClosesAt(), unless the studio cancelled it.
     */
    public function attendanceIsOpen(): bool
    {
        return $this->status === ClassSessionStatus::Scheduled
            && now()->greaterThanOrEqualTo($this->starts_at)
            && now()->lessThanOrEqualTo($this->attendanceClosesAt());
    }

    /**
     * Until when its attendance can be marked, in words: «hoy a las 23:59»
     * or «el martes 3 a las 23:59».
     */
    public function attendanceClosesLabel(): string
    {
        $closesAt = $this->attendanceClosesAt()->timezone(config()->string('studio.timezone'));

        return $closesAt->isSameDay(now(config()->string('studio.timezone')))
            ? __('today at :time', ['time' => $closesAt->format('H:i')])
            : __('on :day at :time', ['day' => $closesAt->translatedFormat('l j'), 'time' => $closesAt->format('H:i')]);
    }

    /**
     * Sessions that still happen, from now on.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('status', ClassSessionStatus::Scheduled)->where('starts_at', '>', now());
    }

    /**
     * Upcoming sessions nobody booked yet: the only ones a schedule change
     * may remove or generate again.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUnbookedAhead(Builder $query): void
    {
        $query->upcoming()->where('booked_count', 0);
    }
}
