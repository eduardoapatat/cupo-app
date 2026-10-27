<?php

namespace App\Http\Requests\Studio;

use App\Concerns\ProfileValidationRules;
use App\Models\Membership;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A student enrolled at the counter, or her contact data, as the front desk
 * types it. The phone is a Chilean mobile, stored as +569XXXXXXXX.
 */
class StudentRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Accept the phone the way people write it (9 1234 5678, +56 9 1234-5678)
     * and keep it in one format.
     */
    protected function prepareForValidation(): void
    {
        $digits = preg_replace('/[^0-9]/', '', $this->string('phone')->value()) ?? '';

        $phone = match (true) {
            strlen($digits) === 9 && str_starts_with($digits, '9') => '+56'.$digits,
            strlen($digits) === 11 && str_starts_with($digits, '569') => '+'.$digits,
            default => $this->string('phone')->trim()->value(),
        };

        $this->merge([
            'phone' => $phone,
            'email' => $this->filled('email') ? mb_strtolower($this->string('email')->trim()->value()) : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request. Only a new
     * enrollment carries the declaration of being 18 or older.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => $this->nameRules(),
            'phone' => ['required', 'string', 'regex:/^\+569[0-9]{8}$/'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            ...($this->isMethod('POST') ? ['adult' => ['accepted']] : []),
        ];
    }

    /**
     * An email that already has an account belongs to someone who can join
     * through the enrollment link; the front desk does not take it over.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $email = $this->studentEmail();

                if ($email === null || $validator->errors()->has('email')) {
                    return;
                }

                $taken = User::withEmail($email)
                    ->when($this->editedStudent(), fn ($query, User $student) => $query->whereKeyNot($student->id))
                    ->exists();

                if ($taken) {
                    $validator->errors()->add('email', __('That email already has a Cupo account. Share the studio link with her so she joins with it.'));
                }
            },
        ];
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => __('Type a Chilean mobile number, for example 9 1234 5678.'),
            'adult.accepted' => __('Confirm that she is 18 or older.'),
        ];
    }

    public function studentName(): string
    {
        return $this->string('name')->trim()->value();
    }

    public function studentPhone(): string
    {
        return $this->string('phone')->value();
    }

    public function studentEmail(): ?string
    {
        $email = $this->input('email');

        return is_string($email) ? $email : null;
    }

    private function editedStudent(): ?User
    {
        $membership = $this->route('membership');

        return $membership instanceof Membership ? $membership->user : null;
    }
}
