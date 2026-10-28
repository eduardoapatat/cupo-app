<?php

namespace App\Http\Requests\Studio;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The studio's own rules, which start as a copy of config/studio.php.
 */
class StudioRulesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cancellation_window_hours' => ['required', 'integer', 'min:0', 'max:72'],
            'booking_cutoff_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'waitlist_cutoff_hours' => ['required', 'integer', 'min:0', 'max:72'],
            'booking_horizon_days' => ['required', 'integer', 'min:1', 'max:60'],
        ];
    }

    /**
     * @return array{cancellation_window_hours: int, booking_cutoff_minutes: int, waitlist_cutoff_hours: int, booking_horizon_days: int}
     */
    public function studioRules(): array
    {
        return [
            'cancellation_window_hours' => $this->integer('cancellation_window_hours'),
            'booking_cutoff_minutes' => $this->integer('booking_cutoff_minutes'),
            'waitlist_cutoff_hours' => $this->integer('waitlist_cutoff_hours'),
            'booking_horizon_days' => $this->integer('booking_horizon_days'),
        ];
    }
}
