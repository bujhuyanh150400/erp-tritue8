<?php

namespace App\Models;

use App\Constants\EmployeeStatus;
use App\Constants\StaffRoleType;
use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Staff extends Model
{
    use HasBigIntId;

    protected $table = 'staff';

    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'role_type',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'status',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'role_type' => StaffRoleType::class,
            'status' => EmployeeStatus::class,
            'joined_at' => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(StaffShift::class);
    }

    public function salaryConfig(): HasOne
    {
        return $this->hasOne(StaffSalaryConfig::class)
            ->whereNull('effective_to')
            ->latest('effective_from');
    }

    public function salaryConfigs(): HasMany
    {
        return $this->hasMany(StaffSalaryConfig::class);
    }

    public function salaryInvoices(): HasMany
    {
        return $this->hasMany(StaffSalaryInvoice::class);
    }
}
