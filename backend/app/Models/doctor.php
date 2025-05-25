<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Doctor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'speciality_id',
        'description',
        'horaires',
        'is_verified',
        'is_active',
        'average_rating',
        'total_reviews'
    ];

    protected $casts = [
        'horaires' => 'array',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'average_rating' => 'float',
        'total_reviews' => 'integer'
    ];

    /**
     * The attributes that should be appended to the model's array form.
     */
    protected $appends = ['full_name', 'formatted_rating'];

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

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class, 'doctor_id');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class, 'doctor_language')
            ->withTimestamps();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Rendezvous::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Avis::class, 'doctor_id', 'user_id');
    }

    /**
     * Get approved reviews only
     */
    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('status', 'approved');
    }

    /**
     * Update the doctor's average rating
     */
    public function updateAverageRating(): void
    {
        $stats = $this->approvedReviews()
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total')
            ->first();

        $this->update([
            'average_rating' => round($stats->avg_rating ?? 0, 2),
            'total_reviews' => $stats->total ?? 0
        ]);
    }

    /**
     * Get full name attribute
     */
    public function getFullNameAttribute(): string
    {
        return $this->user ? "{$this->user->prenom} {$this->user->nom}" : 'N/A';
    }

    /**
     * Get formatted rating attribute
     */
    public function getFormattedRatingAttribute(): string
    {
        return number_format($this->average_rating, 1);
    }

    /**
     * Scope for verified doctors
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope for active doctors
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for filtering by minimum rating
     */
    public function scopeWithMinimumRating($query, float $rating)
    {
        return $query->where('average_rating', '>=', $rating);
    }

    /**
     * Scope for filtering by languages
     */
    public function scopeWithLanguages($query, array $languageIds)
    {
        return $query->whereHas('languages', function ($q) use ($languageIds) {
            $q->whereIn('languages.id', $languageIds);
        });
    }

    /**
     * Scope for advanced search
     */
    public function scopeSearch($query, array $filters)
    {
        // Filter by speciality
        if (!empty($filters['speciality_id'])) {
            $query->where('speciality_id', $filters['speciality_id']);
        }

        // Filter by location
        if (!empty($filters['location'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->where('adresse', 'LIKE', '%' . $filters['location'] . '%');
            });
        }

        // Filter by languages
        if (!empty($filters['language_ids'])) {
            $query->withLanguages($filters['language_ids']);
        }

        // Filter by minimum rating
        if (!empty($filters['min_rating'])) {
            $query->withMinimumRating($filters['min_rating']);
        }

        // Filter by name
        if (!empty($filters['name'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->where(DB::raw("CONCAT(prenom, ' ', nom)"), 'LIKE', '%' . $filters['name'] . '%');
            });
        }

        return $query;
    }

    /**
     * Get monthly statistics
     */
    public function getMonthlyStats(int $year, int $month): array
    {
        $startDate = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $appointments = $this->appointments()
            ->whereBetween('date_heure', [$startDate, $endDate])
            ->selectRaw('statut, COUNT(*) as count')
            ->groupBy('statut')
            ->pluck('count', 'statut')
            ->toArray();

        return [
            'total' => array_sum($appointments),
            'confirmed' => $appointments['confirmé'] ?? 0,
            'completed' => $appointments['terminé'] ?? 0,
            'cancelled' => $appointments['annulé'] ?? 0,
            'no_show' => $appointments['no_show'] ?? 0,
            'pending' => $appointments['en_attente'] ?? 0,
        ];
    }

    public function verify(): void
    {
        $this->update(['is_verified' => true]);
    }
}
