<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchDoctorsAdvancedRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public search endpoint
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'speciality_id' => ['nullable', 'integer', 'exists:specialities,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'language_ids' => ['nullable', 'array'],
            'language_ids.*' => ['integer', 'exists:languages,id'],
            'min_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'name' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date', 'after_or_equal:today'],
            'sort_by' => ['nullable', 'string', 'in:rating,name,created_at'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
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
            'speciality_id.exists' => 'La spécialité sélectionnée n\'existe pas.',
            'language_ids.*.exists' => 'Une ou plusieurs langues sélectionnées n\'existent pas.',
            'min_rating.min' => 'La note minimale doit être d\'au moins 0.',
            'min_rating.max' => 'La note minimale ne peut pas dépasser 5.',
            'date.after_or_equal' => 'La date doit être aujourd\'hui ou dans le futur.',
            'sort_by.in' => 'Le champ de tri doit être l\'un des suivants : rating, name, created_at.',
            'sort_order.in' => 'L\'ordre de tri doit être asc ou desc.',
            'per_page.max' => 'Le nombre maximum d\'éléments par page est 100.',
        ];
    }
}
