<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Admin;

class User extends Authenticatable
{
    protected $fillable = ['nom', 'prenom', 'email', 'telephone', 'mot_de_passe', 'role'];
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function doctor() {
        return $this->hasOne(Doctor::class);
    }

    public function patient() {
        return $this->hasOne(Patient::class);
    }

    public function admin() {
        return $this->hasOne(Admin::class);
    }
}
