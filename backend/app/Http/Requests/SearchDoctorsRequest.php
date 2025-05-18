<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchDoctorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Allow all users for testing
    }

    public function rules(): array
    {
        return [
            'speciality_id' => 'nullable|integer',
            'location' => 'nullable|string|max:255',
            'date' => 'nullable|date',
        ];
    }
}