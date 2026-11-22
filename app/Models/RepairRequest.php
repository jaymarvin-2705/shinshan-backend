<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepairRequest extends Model
{
    use HasFactory;

    protected $primaryKey = 'request_id';

    protected $fillable = [
        'client_id',
        'category_id',
        'title',
        'description',
        'barangay',
        'purok',
        'landmark',
        'service_latitude',
        'service_longitude',
        'photo',
        'priority',
        'status',
    ];

        // The 20 barangays of Trinidad, Bohol. The backend is the source of truth for validation.
    public const BARANGAYS = [
        'Banlasan',
        'Bongbong',
        'Catoogan',
        'Guinobatan',
        'Hinlayagan Ilaud',
        'Hinlayagan Ilaya',
        'Kauswagan',
        'Kinan-oan',
        'La Union',
        'La Victoria',
        'Mabuhay Cabigohan',
        'Mahagbu',
        'Manuel M. Roxas',
        'Poblacion',
        'San Isidro',
        'San Vicente',
        'Santo Tomas',
        'Soom',
        'Tagum Norte',
        'Tagum Sur',
    ];

    protected $casts = [
        'service_latitude' => 'decimal:8',
        'service_longitude' => 'decimal:8',
    ];

    public function client()
    {
        return $this->belongsTo(
            ClientProfile::class,
            'client_id',
            'client_id'
        );
    }

    public function category()
    {
        return $this->belongsTo(
            IssueCategory::class,
            'category_id',
            'category_id'
        );
    }

    public function feedback()
    {
        return $this->hasOne(
            Feedback::class,
            'request_id',
            'request_id'
        );
    }
    public function assignment()
{
    return $this->hasOne(
        JobAssignment::class,
        'request_id',
        'request_id'
    );
}
}