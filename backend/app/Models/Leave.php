<?php

// app/Models/Leave.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Leave extends Model
{
    protected $fillable = ['doctor_id', 'start_date', 'end_date', 'reason'];
    protected $table = 'leaves';
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
}