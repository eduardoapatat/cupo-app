<?php

namespace App\Http\Requests\Studio;

use App\Enums\StudioRole;
use App\Models\Studio;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InviteToStudioRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::enum(StudioRole::class)->only(StudioRole::invitable())],
        ];
    }

    /**
     * Someone already in the studio, or already invited, is not invited
     * again: a pending invitation is resent from the list instead.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('email')) {
                    return;
                }

                $studio = $this->studio();
                $email = $this->invitedEmail();

                $isMember = $studio->memberships()
                    ->whereHas('user', fn ($user) => $user->whereRaw('lower(email) = ?', [$email]))
                    ->exists();

                if ($isMember) {
                    $validator->errors()->add('email', __('That person is already part of the studio.'));
                } elseif ($studio->invitations()->pending()->where('email', $email)->exists()) {
                    $validator->errors()->add('email', __('You already invited that email. Resend the invitation from the list.'));
                }
            },
        ];
    }

    /**
     * The invited email, in lowercase as invitations store it.
     */
    public function invitedEmail(): string
    {
        return mb_strtolower($this->string('email')->trim()->value());
    }

    public function invitedRole(): StudioRole
    {
        return StudioRole::from($this->string('role')->value());
    }

    private function studio(): Studio
    {
        $studio = $this->route('studio');

        assert($studio instanceof Studio);

        return $studio;
    }
}
