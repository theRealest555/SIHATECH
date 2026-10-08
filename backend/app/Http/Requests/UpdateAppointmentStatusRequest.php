<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'statut' => ['required', Rule::in($this->user()?->role === 'patient' ? ['annulé'] : ['confirmé', 'annulé', 'terminé'])],
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
            'statut.required' => 'Le statut est obligatoire.',
            'statut.in' => 'Le statut doit être l\'une des valeurs suivantes: confirmé, en_attente, annulé, terminé.',
        ];
    }
}
