<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class speciality extends Model
{
    protected $fillable = ['nom', 'description'];

    public function doctors() {
        return $this->hasMany(Doctor::class);
    }
}
