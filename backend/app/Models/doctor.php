<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User as User;
use App\Models\Speciality as Speciality;


class Doctor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'speciality_id',
        'description',
        'horaires',
        'is_verified'
    ];

    protected $casts = [
        'horaires' => 'json',
        'is_verified' => 'boolean'
    ];

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
}
