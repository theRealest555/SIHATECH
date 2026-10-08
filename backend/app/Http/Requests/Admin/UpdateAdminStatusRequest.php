<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminStatusRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => preg_replace('/^\s+|\s+$/u', '', $this->input('reason'))]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'admin_status' => ['required', 'in:1,0'],
            'expected_status_revision' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'reason' => ['required_if:admin_status,0', 'nullable', 'string', 'max:500'],
        ];
    }
}
