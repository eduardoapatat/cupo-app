<?php

namespace App\Http\Requests\Studio;

use App\Models\ClassType;
use App\Models\Studio;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A class type of the studio, shared by the create and edit forms.
 */
class ClassTypeRequest extends FormRequest
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
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:480'],
            'capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Two class types of the same studio cannot share a name.
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

                $sameName = $this->studio()->classTypes()->whereRaw('lower(name) = ?', [mb_strtolower($this->className())]);
                $edited = $this->route('classType');

                if ($edited instanceof ClassType) {
                    $sameName->whereKeyNot($edited->id);
                }

                $taken = $sameName->exists();

                if ($taken) {
                    $validator->errors()->add('name', __('The studio already has a class with that name.'));
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

    public function className(): string
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
