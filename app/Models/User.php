<?php

namespace App\Models;

use App\Constants\UserRole;
use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasBigIntId, HasFactory;

    protected $fillable = [
        'username',
        'password',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────
    public function student()
    {
        return $this->hasOne(Student::class);
    }

    public function teacher()
    {
        return $this->hasOne(Teacher::class);
    }

    public function staff()
    {
        return $this->hasOne(Staff::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function logs()
    {
        return $this->hasMany(UserLog::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isTeacher(): bool
    {
        return $this->role === UserRole::Teacher;
    }

    public function isStaff(): bool
    {
        return $this->role === UserRole::Staff;
    }

    public function isStudent(): bool
    {
        return $this->role === UserRole::Student;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }

    public function getIsActive(): bool
    {
        return $this->is_active;
    }
}
