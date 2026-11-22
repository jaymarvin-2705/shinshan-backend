<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $primaryKey = 'notification_id';

    protected $fillable = [
        'user_id',
        'announcement_id',
        'notification_type',
        'priority',
        'related_request_id',
        'event_key',
        'title',
        'message',
        'is_read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(
            Announcement::class,
            'announcement_id',
            'announcement_id'
        );
    }

    public function repairRequest(): BelongsTo
    {
        return $this->belongsTo(
            RepairRequest::class,
            'related_request_id',
            'request_id'
        );
    }
}
