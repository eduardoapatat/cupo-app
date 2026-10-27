<?php

use App\Models\Studio;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('rejects negative rules at the database level', function (string $rule) {
    $studio = Studio::factory()->create();

    $invalid = fn () => DB::table('studios')->where('id', $studio->id)->update([$rule => -1]);

    expect($invalid)->toThrow(function (QueryException $exception) {
        expect($exception->getCode())->toBe('23514');
    });
})->with(['cancellation_window_hours', 'booking_cutoff_minutes', 'waitlist_cutoff_hours', 'plan_expiry_warning_days']);

it('derives a unique slug from the name', function () {
    $first = Studio::factory()->create(['name' => 'Estudio Ñuñoa']);
    $second = Studio::factory()->create(['name' => 'Estudio Ñuñoa']);

    expect($first->slug)->toBe('estudio-nunoa')
        ->and($second->slug)->toBe('estudio-nunoa-2');
});

it('keeps the slug when the name changes', function () {
    $studio = Studio::factory()->create(['name' => 'Estudio Flow']);

    $studio->update(['name' => 'Flow Pilates']);

    expect($studio->fresh()->slug)->toBe('estudio-flow');
});

it('gives every studio its own enrollment token', function () {
    $first = Studio::factory()->create();
    $second = Studio::factory()->create();

    expect($first->enrollment_token)->toHaveLength(40)
        ->and($first->enrollment_token)->not->toBe($second->enrollment_token);
});
