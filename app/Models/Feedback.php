<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    use HasFactory;

    protected $primaryKey = 'feedback_id';

    protected $fillable = [
        'request_id',
        'client_id',
        'technician_id',
        'rating',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    public function repairRequest()
    {
        return $this->belongsTo(
            RepairRequest::class,
            'request_id',
            'request_id'
        );
    }

    public function client()
    {
        return $this->belongsTo(
            ClientProfile::class,
            'client_id',
            'client_id'
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
}
