<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

// Import DB Facade

class Doctor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'speciality_id',
        'location_id',
        'description',
        'horaires',
        'is_verified',
        'is_active',
        'average_rating',
        'total_reviews',
    ];

    protected $casts = [
        'horaires' => 'array', // Ensures horaires is always an array
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'average_rating' => 'float',
        'total_reviews' => 'integer',
    ];

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
        return $this->belongsTo(Location::class);
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

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('status', 'approved');
    }

    public function updateAverageRating(): void
    {
        $stats = $this->approvedReviews()
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total')
            ->first();

        $this->update([
            'average_rating' => round($stats->avg_rating ?? 0, 2),
            'total_reviews' => $stats->total ?? 0,
        ]);
    }

    public function getFullNameAttribute(): string
    {
        return $this->user ? "{$this->user->prenom} {$this->user->nom}" : 'N/A';
    }

    public function getFormattedRatingAttribute(): string
    {
        return number_format($this->average_rating, 1);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->whereHas('user', fn ($user) => $user->where('status', 'actif')->whereNotNull('email_verified_at'));
    }

    public function scopeWithMinimumRating($query, float $rating)
    {
        return $query->where('average_rating', '>=', $rating);
    }

    public function scopeWithLanguages($query, array $languageIds)
    {
        return $query->whereHas('languages', function ($q) use ($languageIds) {
            $q->whereIn('languages.id', $languageIds);
        });
    }

    public function scopeSearch($query, array $filters)
    {
        if (! empty($filters['speciality_id'])) {
            $query->where('speciality_id', $filters['speciality_id']);
        }

        if (! empty($filters['location'])) {
            $query->whereHas('location', function ($q) use ($filters) {
                $q->where('name', 'LIKE', '%'.$filters['location'].'%');
            });
        }

        if (! empty($filters['language_ids'])) {
            $query->withLanguages($filters['language_ids']);
        }

        if (! empty($filters['min_rating'])) {
            $query->withMinimumRating($filters['min_rating']);
        }

        if (! empty($filters['name'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                // Use database-agnostic concatenation
                $concatExpression = DB::connection()->getDriverName() === 'sqlite'
                    ? "prenom || ' ' || nom"
                    : "CONCAT(prenom, ' ', nom)";
                $q->where(DB::raw($concatExpression), 'LIKE', '%'.$filters['name'].'%');
            });
        }

        return $query;
    }

    public function getMonthlyStats(int $year, int $month): array
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
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
