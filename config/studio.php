<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Studio Timezone
    |--------------------------------------------------------------------------
    |
    | Timestamps are stored in UTC, but the weekly schedule, sessions and
    | every date shown to people are interpreted in this timezone. Each
    | date's own offset is used, so daylight saving changes are handled.
    |
    */

    'timezone' => env('STUDIO_TIMEZONE', 'America/Santiago'),

    /*
    |--------------------------------------------------------------------------
    | Default Rules
    |--------------------------------------------------------------------------
    |
    | Every new studio starts with these rules and its owner can change them
    | later. They are only defaults: once a studio exists, its own rules
    | apply and the code must read them from the studio, not from here.
    |
    */

    'defaults' => [

        // A student may cancel for free up to this many hours before the
        // session starts and gets the credit back. Later, it is lost.
        'cancellation_window_hours' => (int) env('STUDIO_CANCELLATION_WINDOW_HOURS', 6),

        // Bookings close this many minutes before the session starts, so
        // the instructor knows in time who is coming.
        'booking_cutoff_minutes' => (int) env('STUDIO_BOOKING_CUTOFF_MINUTES', 60),

        // A freed spot goes to the first student on the waitlist while the
        // session is at least this many hours away. Closer to the start it
        // stays open for anyone, so nobody gets a booking they can no longer
        // cancel for free. It follows the cancellation window by default.
        'waitlist_cutoff_hours' => (int) env('STUDIO_WAITLIST_CUTOFF_HOURS', env('STUDIO_CANCELLATION_WINDOW_HOURS', 6)),

        // How many sessions a day an unlimited plan may book. Each plan can
        // set its own limit; this is the value new unlimited plans start with.
        'unlimited_plan_daily_limit' => (int) env('STUDIO_UNLIMITED_PLAN_DAILY_LIMIT', 1),

        // Students are told this many days before their plan expires.
        'plan_expiry_warning_days' => (int) env('STUDIO_PLAN_EXPIRY_WARNING_DAYS', 3),

    ],

];
