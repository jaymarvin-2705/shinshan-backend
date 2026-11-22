<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'general',
        'maintenance',
        'service_interruption',
        'system_update',
        'important_notice',
        'promotional',
    ];

    public const AUDIENCES = [
        'clients',
        'technicians',
        'all',
    ];

    public const STATUSES = [
        'draft',
        'scheduled',
        'published',
        'archived',
    ];

    protected $primaryKey = 'announcement_id';

    protected $fillable = [
        'admin_id',
        'title',
        'content',
        'category',
        'target_audience',
        'status',
        'attachment_path',
        'publish_at',
        'published_at',
        'expires_at',
    ];

    protected $hidden = [
        'attachment_path',
    ];

    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id', 'user_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(
            Notification::class,
            'announcement_id',
            'announcement_id'
        );
    }

    public function scopeAvailableTo(Builder $query, string $audience): Builder
    {
        return $query
            ->where('status', 'published')
            ->where(function (Builder $query) use ($audience) {
                $query->where('target_audience', 'all')
                    ->orWhere('target_audience', $audience);
            })
            ->where(function (Builder $query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
