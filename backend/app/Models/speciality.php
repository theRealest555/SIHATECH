<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Speciality extends Model
{
    protected $table = 'specialities';
    protected $fillable = ['nom', 'description'];

    public function doctors()
    {
        return $this->hasMany(Doctor::class);
    }
}

