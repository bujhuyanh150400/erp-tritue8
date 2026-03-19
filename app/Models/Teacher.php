<?php

namespace App\Models;

use App\Constants\EmployeeStatus;
use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
    use HasBigIntId;

    protected $table = 'teachers';

    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'email',
        'address',
        'bank_name',
        'bank_bin',
        'bank_account_number',
        'bank_account_holder',
        'status',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmployeeStatus::class,
            'joined_at' => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function scheduleTemplates(): HasMany
    {
        return $this->hasMany(ClassScheduleTemplate::class);
    }

    public function scheduleInstances(): HasMany
    {
        return $this->hasMany(ScheduleInstance::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function salaryConfigs(): HasMany
    {
        return $this->hasMany(TeacherSalaryConfig::class);
    }

    public function salaryInvoices(): HasMany
    {
        return $this->hasMany(TeacherSalaryInvoice::class);
    }

    public function scheduleChangeRequests(): HasMany
    {
        return $this->hasMany(ScheduleChangeRequest::class, 'requested_by');
    }

    public function monthlyReports(): HasMany
    {
        return $this->hasMany(MonthlyReport::class);
    }
}
