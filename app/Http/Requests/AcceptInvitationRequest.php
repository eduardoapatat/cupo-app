<?php

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Accepting an invitation. A guest creates their account on the way, with
 * the invited email; someone signed in only confirms.
 */
class AcceptInvitationRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->user() !== null) {
            return [];
        }

        return [
            'name' => $this->nameRules(),
            'password' => $this->passwordRules(),
            'adult' => $this->adultRules(),
        ];
    }
}
