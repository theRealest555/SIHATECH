<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleRequest extends FormRequest
{
    public function authorize()
    {
        return true; // No auth required
    }

    public function rules()
    {
        return [
            'schedule' => ['required', 'array'],
            'schedule.*' => ['array'],
            'schedule.*.*' => ['string', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]-([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
        ];
    }

    public function messages()
    {
        return [
            'schedule.*.*.regex' => 'Le format des horaires doit être HH:mm-HH:mm (ex. 09:00-17:00).'
        ];
    }
}