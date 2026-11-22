<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IssueCategory extends Model
{
    use HasFactory;

    protected $primaryKey = 'category_id';

    protected $fillable = [
        'category_name',
        'description',
    ];

    public function repairRequests()
    {
        return $this->hasMany(
            RepairRequest::class,
            'category_id',
            'category_id'
        );
    }
}