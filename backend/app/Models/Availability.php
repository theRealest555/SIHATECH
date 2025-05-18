<?php

// app/Models/Availability.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Availability extends Model
{
    protected $fillable = ['doctor_id', 'day_of_week', 'time_range'];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
}
