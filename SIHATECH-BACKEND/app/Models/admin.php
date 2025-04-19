<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use app\Models\User as User;

class admin extends Model
{
    protected $fillable = ['user_id'];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
