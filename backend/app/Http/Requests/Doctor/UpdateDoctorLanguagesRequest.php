<?php

namespace App\Http\Requests\Doctor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDoctorLanguagesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'medecin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'language_ids' => ['required', 'array', 'min:1'],
            'language_ids.*' => ['required', 'integer', 'exists:languages,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'language_ids.required' => 'Vous devez sélectionner au moins une langue.',
            'language_ids.array' => 'Les langues doivent être fournies sous forme de tableau.',
            'language_ids.min' => 'Vous devez sélectionner au moins une langue.',
            'language_ids.*.exists' => 'Une ou plusieurs langues sélectionnées sont invalides.',
        ];
    }
}
