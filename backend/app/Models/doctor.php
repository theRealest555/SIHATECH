<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Doctor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'speciality_id',
        'description',
        'horaires',
        'is_verified',
        'is_active'
    ];

    protected $casts = ['horaires' => 'array', 'is_verified' => 'boolean', 'is_active' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function speciality(): BelongsTo
    {
        return $this->belongsTo(Speciality::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function availabilities()
    {
        return $this->hasMany(Availability::class, 'doctor_id');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class, 'doctor_language');
    }

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class);
    }

    public function verify(): void
    {
        $this->update(['is_verified' => true]);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Rendezvous::class);
    }
}
