<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientProfile extends Model
{
    use HasFactory;

    protected $primaryKey = 'client_id';

    protected $fillable = [
        'user_id',
        'birth_date',
        'barangay',
        'purok',
        'address',
        'profile_photo_path',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    // User relationship
    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id',
            'user_id'
        );
    }

    // Repair requests relationship
    public function repairRequests()
    {
        return $this->hasMany(
            RepairRequest::class,
            'client_id',
            'client_id'
        );
    }
}
