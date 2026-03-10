<?php

namespace App\Models;

use App\Constants\Gender;
use App\Constants\GradeLevel;
use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasBigIntId;

    protected $table = 'students';

    protected $fillable = [
        'user_id',
        'full_name',
        'dob',
        'gender',
        'grade_level',
        'parent_name',
        'parent_phone',
        'address',
        'zalo_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'gender' => Gender::class,
            'grade_level' => GradeLevel::class,
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function enrollments()
    {
        return $this->hasMany(ClassEnrollment::class);
    }

    public function activeEnrollments()
    {
        return $this->hasMany(ClassEnrollment::class)
            ->whereNull('left_at');
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function rewardPoints()
    {
        return $this->hasMany(RewardPoint::class);
    }

    public function rewardRedemptions()
    {
        return $this->hasMany(RewardRedemption::class);
    }

    public function tuitionInvoices()
    {
        return $this->hasMany(TuitionInvoice::class);
    }

    public function monthlyReports()
    {
        return $this->hasMany(MonthlyReport::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function getTotalRewardPoints(): int
    {
        return $this->rewardPoints()->sum('amount');
    }
}
