<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookAppointmentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'doctor_id' => 'required|exists:doctors,id',
            'patient_id' => 'required|exists:users,id',
            'date_heure' => 'required|date',
        ];
    }

    public function messages()
    {
        return [
            'doctor_id.required' => 'The doctor ID is required.',
            'doctor_id.exists' => 'The selected doctor does not exist.',
            'patient_id.required' => 'The patient ID is required.',
            'patient_id.exists' => 'The selected patient does not exist.',
            'date_heure.required' => 'The appointment date and time are required.',
            'date_heure.date' => 'The appointment date and time must be a valid date.',
        ];
    }
}