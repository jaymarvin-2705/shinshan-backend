<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepairUpdate extends Model
{
    use HasFactory;

    protected $primaryKey = 'update_id';

    protected $fillable = [
        'assignment_id',
        'status',
        'remarks',
        'image',
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