<?php

namespace App\Http\Requests\Studio;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Mark that someone came to a class, or undo it.
 */
class AttendanceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attended' => ['required', 'boolean'],
        ];
    }

    public function attended(): bool
    {
        return $this->boolean('attended');
    }
}
