<?php

namespace App\Http\Requests\Doctor;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompleteProfileRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'expected_profile_revision' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'speciality_id' => ['required', 'exists:specialities,id'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'sexe' => ['nullable', 'string', 'in:homme,femme'],
            'date_de_naissance' => ['nullable', 'date', 'before:today'],
        ];
    }
}
