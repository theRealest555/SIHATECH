<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\speciality;
use App\Models\Language;
use App\Models\Abonnement;

class doctor extends Model
{
    protected $fillable = ['user_id', 'specialty_id', 'adresse', 'description', 'photo', 'horaires'];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function specialty() {
        return $this->belongsTo(speciality::class);
    }

    public function languages() {
        return $this->belongsToMany(Language::class, 'doctor_language');
    }

    public function abonnements() {
        return $this->hasMany(Abonnement::class);
    }
}
