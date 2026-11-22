<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TechnicianProfile extends Model
{
    use HasFactory;

    protected $primaryKey = 'technician_id';

    protected $fillable = [
        'user_id',
        'service_area',
        'profile_photo_path',
        'availability',
        'current_latitude',
        'current_longitude',
        'current_location_updated_at',
    ];

    protected $casts = [
        'current_latitude' => 'decimal:8',
        'current_longitude' => 'decimal:8',
        'current_location_updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id',
            'user_id'
        );
    }
    public function assignments()
{
    return $this->hasMany(
        JobAssignment::class,
        'technician_id',
        'technician_id'
    );
}
}