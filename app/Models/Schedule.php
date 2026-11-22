<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $primaryKey = 'schedule_id';

    protected $fillable = [
        'assignment_id',
        'date',
        'time',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function assignment()
    {
        return $this->belongsTo(
            JobAssignment::class,
            'assignment_id',
            'assignment_id'
        );
    }
}