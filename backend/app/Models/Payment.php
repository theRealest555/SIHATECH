<?php
// app/Models/Payment.php - Update the existing one

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'user_subscription_id',
        'transaction_id',
        'amount',
        'currency',
        'status',
        'payment_method',
        'payment_data'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_data' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userSubscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class);
    }
}
