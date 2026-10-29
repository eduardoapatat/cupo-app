<?php

namespace App\Http\Requests\Studio;

use App\Models\Plan;
use App\Models\Studio;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A plan of the studio, shared by the create and edit forms. An unlimited
 * plan has no number of classes.
 */
class PlanRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60', 'regex:/[\pL\pN]/u'],
            'price' => ['required', 'integer', 'min:0', 'max:10000000'],
            'unlimited' => ['required', 'boolean'],
            'credits' => ['exclude_if:unlimited,true', 'required', 'integer', 'min:1', 'max:200'],
            'validity_days' => ['required', 'integer', 'min:1', 'max:365'],
            'daily_limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    /**
     * Two plans the studio still sells cannot share a name.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('name')) {
                    return;
                }

                $sameName = $this->studio()->plans()->active()->whereRaw('lower(name) = ?', [mb_strtolower($this->planName())]);
                $edited = $this->route('plan');

                if ($edited instanceof Plan) {
                    $sameName->whereKeyNot($edited->id);
                }

                if ($sameName->exists()) {
                    $validator->errors()->add('name', __('The studio already sells a plan with that name.'));
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
            'name.regex' => __('The name must include letters or numbers.'),
        ];
    }

    /**
     * The plan as the model stores it.
     *
     * @return array{name: string, price: int, credits: int|null, validity_days: int, daily_limit: int|null}
     */
    public function plan(): array
    {
        return [
            'name' => $this->planName(),
            'price' => $this->integer('price'),
            'credits' => $this->boolean('unlimited') ? null : $this->integer('credits'),
            'validity_days' => $this->integer('validity_days'),
            'daily_limit' => $this->filled('daily_limit') ? $this->integer('daily_limit') : null,
        ];
    }

    private function planName(): string
    {
        return $this->string('name')->trim()->value();
    }

    private function studio(): Studio
    {
        $studio = $this->route('studio');

        assert($studio instanceof Studio);

        return $studio;
    }
}
