<?php

namespace App\Http\Requests\Doctor;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240'], // 10MB max
            'type' => ['required', 'in:licence,cni,diplome,autre'],
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
            'file.required' => 'Le fichier est obligatoire.',
            'file.file' => 'Le fichier doit être un fichier valide.',
            'file.max' => 'Le fichier ne doit pas dépasser 10 Mo.',
            'type.required' => 'Le type de document est obligatoire.',
            'type.in' => 'Le type de document doit être l\'un des suivants: licence, cni, diplome, autre.',
        ];
    }
}
