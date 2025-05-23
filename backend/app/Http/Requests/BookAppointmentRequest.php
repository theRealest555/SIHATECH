<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class BookAppointmentRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'exists:users,id,role,patient'],
            'date_heure' => ['required', 'date', 'after_or_equal:' . now()],
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
            'patient_id.required' => 'Le patient est obligatoire.',
            'patient_id.exists' => 'Le patient sélectionné n\'existe pas.',
            'date_heure.required' => 'La date et l\'heure sont obligatoires.',
            'date_heure.date' => 'La date et l\'heure doivent être une date valide.',
            'date_heure.after_or_equal' => 'La date et l\'heure doivent être futures.',
        ];
    }
}
