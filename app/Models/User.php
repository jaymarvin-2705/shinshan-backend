<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'user_id';

    protected $fillable = [
        'role_id',
        'firstname',
        'middlename',
        'lastname',
        'fullname',
        'email',
        'phone',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    // User role
    public function role()
    {
        return $this->belongsTo(
            Role::class,
            'role_id',
            'role_id'
        );
    }

    // Client profile
    public function clientProfile()
    {
        return $this->hasOne(
            ClientProfile::class,
            'user_id',
            'user_id'
        );
    }

    // Technician profile
    public function technicianProfile()
    {
        return $this->hasOne(
            TechnicianProfile::class,
            'user_id',
            'user_id'
        );
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(
            Announcement::class,
            'admin_id',
            'user_id'
        );
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(
            Notification::class,
            'user_id',
            'user_id'
        );
    }

    public function receivedAnnouncements(): HasManyThrough
    {
        return $this->hasManyThrough(
            Announcement::class,
            Notification::class,
            'user_id',
            'announcement_id',
            'user_id',
            'announcement_id'
        );
    }
}
