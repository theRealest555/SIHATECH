<?php

namespace App\Services;

use App\Models\Doctor;
use Illuminate\Support\Facades\Storage;

class DoctorCredentials
{
    public function requiredTypes(): array
    {
        return config('verification.required_document_types', ['licence']);
    }

    public function missingTypes(Doctor $doctor): array
    {
        $approved = $doctor->documents()->where('status', 'approved')->get()
            ->filter(fn ($document) => Storage::disk('documents')->exists($document->file_path))->pluck('type')->all();

        return array_values(array_diff($this->requiredTypes(), $approved));
    }
}
