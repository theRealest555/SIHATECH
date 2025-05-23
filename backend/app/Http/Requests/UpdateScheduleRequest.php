<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleRequest extends FormRequest
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
            'schedule' => ['required', 'array'],
            'schedule.*' => ['array'],
            'schedule.*.*' => ['string', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]-([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
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
            'schedule.required' => 'L\'horaire est obligatoire.',
            'schedule.array' => 'L\'horaire doit être un tableau.',
            'schedule.*.array' => 'Chaque jour de l\'horaire doit être un tableau.',
            'schedule.*.*.regex' => 'Le format des horaires doit être HH:mm-HH:mm (ex. 09:00-17:00).'
        ];
    }
}
