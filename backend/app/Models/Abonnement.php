<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Abonnement extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'billing_cycle',
        'features',
        'is_active',
        'stripe_price_id',
    ];

    protected $casts = [
        'lock_version' => 'integer',
        'features' => 'array',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function userSubscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class, 'subscription_plan_id');
    }

    public function activeSubscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class, 'subscription_plan_id')->where('status', 'active');
    }
}
