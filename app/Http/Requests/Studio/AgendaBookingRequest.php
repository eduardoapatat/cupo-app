<?php

namespace App\Http\Requests\Studio;

use App\Enums\BookingKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The front desk or the owner adds someone to a session: a student of the
 * studio, or someone of the team. The controller checks the membership.
 */
class AgendaBookingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'],
            'kind' => ['required', Rule::enum(BookingKind::class)],
        ];
    }

    public function kind(): BookingKind
    {
        return $this->enum('kind', BookingKind::class) ?? BookingKind::Student;
    }
}
