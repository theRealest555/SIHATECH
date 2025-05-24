<?php
// app/Http/Requests/BookAppointmentRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class BookAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Remove patient_id since we'll use authenticated user
            'date_heure' => ['required', 'date', 'after_or_equal:' . now()],
        ];
    }

    public function messages(): array
    {
        return [
            'date_heure.required' => 'La date et l\'heure sont obligatoires.',
            'date_heure.date' => 'La date et l\'heure doivent être une date valide.',
            'date_heure.after_or_equal' => 'La date et l\'heure doivent être futures.',
        ];
    }
}
