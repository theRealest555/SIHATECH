<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class rendezvous extends Model
{
    protected $table = 'rendezvous';
    protected $fillable = ['patient_id', 'doctor_id', 'date_heure', 'statut'];
    protected $casts = ['date_heure' => 'datetime', 'statut' => 'string'];

    public function doctor() {
        return $this->belongsTo(Doctor::class);
    }
    public function patient() {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
    
}
