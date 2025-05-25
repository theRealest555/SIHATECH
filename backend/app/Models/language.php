<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Language extends Model
{
    use HasFactory;

    protected $fillable = ['nom'];

    /**
     * Get the doctors that speak this language.
     */
    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'doctor_language')
            ->withTimestamps();
    }

    /**
     * Scope for commonly used languages
     */
    public function scopeCommon($query)
    {
        return $query->whereIn('nom', ['Français', 'Anglais', 'Arabe', 'Espagnol']);
    }

    /**
     * Get language by name
     */
    public static function findByName(string $name): ?self
    {
        return static::where('nom', $name)->first();
    }
}
