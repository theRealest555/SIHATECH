<?php 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use app\Models\doctor as Doctor;
use app\Models\admin as Admin;
// use app\Models\document as Document;

class Document extends Model
{
    protected $fillable = [
        'doctor_id',
        'type',
        'file_path',
        'original_name',
        'status',
        'rejection_reason',
        'admin_id',
        'verified_at'
    ];

    protected $casts = [
        'verified_at' => 'datetime'
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}