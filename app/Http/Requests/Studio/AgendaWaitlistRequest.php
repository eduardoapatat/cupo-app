<?php

namespace App\Http\Requests\Studio;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The front desk or the owner puts a student in a class's waitlist. The
 * controller checks she is a student of the studio.
 */
class AgendaWaitlistRequest extends FormRequest
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
        ];
    }
}
