<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobAssignment extends Model
{
    use HasFactory;

    protected $primaryKey = 'assignment_id';

    protected $fillable = [
        'request_id',
        'technician_id',
        'assigned_by',
        'assigned_at',
        'status',
        'remarks',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function repairRequest()
    {
        return $this->belongsTo(
            RepairRequest::class,
            'request_id',
            'request_id'
        );
    }

    public function technician()
    {
        return $this->belongsTo(
            TechnicianProfile::class,
            'technician_id',
            'technician_id'
        );
    }

    public function assignedBy()
    {
        return $this->belongsTo(
            User::class,
            'assigned_by',
            'user_id'
        );
    }

    public function schedule()
    {
        return $this->hasOne(
            Schedule::class,
            'assignment_id',
            'assignment_id'
        );
    }

    public function repairUpdates()
    {
        return $this->hasMany(
            RepairUpdate::class,
            'assignment_id',
            'assignment_id'
        );
    }
}