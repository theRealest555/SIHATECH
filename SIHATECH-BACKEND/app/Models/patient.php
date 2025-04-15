<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Doctor;

class patient extends Model
{
    protected $fillable = ['user_id', 'medecin_favori_id'];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function medecinFavori() {
        return $this->belongsTo(Doctor::class, 'medecin_favori_id');
    }
}
