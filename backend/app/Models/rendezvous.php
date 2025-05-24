<?php
// app/Models/rendezvous.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rendezvous extends Model
{
    protected $table = 'rendezvous';
    protected $fillable = ['patient_id', 'doctor_id', 'date_heure', 'statut'];
    protected $casts = [
        'date_heure' => 'datetime',
        'statut' => 'string'
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    // Fix: patient_id references users table directly
    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    // Add relationship to patient profile if needed
    public function patientProfile(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'user_id');
    }
}
