<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rendezvous extends Model
{
    protected $table = 'rendezvous';

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'date_heure',
        'statut'
    ];

    protected $casts = [
        'date_heure' => 'datetime',
        'statut' => 'string'
    ];

    /**
     * Get the doctor that owns the appointment
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Get the patient (user) that owns the appointment
     * Since patient_id references users table directly after migration
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    /**
     * Get the patient profile if needed
     * This is optional - only use if you need patient-specific data
     */
    public function patientProfile(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'user_id');
    }

    /**
     * Check if appointment is in the future
     */
    public function isFuture(): bool
    {
        return $this->date_heure->isFuture();
    }

    /**
     * Check if appointment is today
     */
    public function isToday(): bool
    {
        return $this->date_heure->isToday();
    }

    /**
     * Check if appointment can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->statut, ['en_attente', 'confirmé']) && $this->isFuture();
    }

    /**
     * Scope for appointments with specific status
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('statut', $status);
    }

    /**
     * Scope for future appointments
     */
    public function scopeFuture($query)
    {
        return $query->where('date_heure', '>', now());
    }

    /**
     * Scope for today's appointments
     */
    public function scopeToday($query)
    {
        return $query->whereDate('date_heure', today());
    }
}
