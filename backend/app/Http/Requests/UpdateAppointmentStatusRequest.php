<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Adjust based on your auth logic
    }

    public function rules(): array
    {
        return [
            'statut' => ['required', Rule::in(['confirmé', 'en_attente', 'annulé', 'terminé'])],
        ];
    }
}