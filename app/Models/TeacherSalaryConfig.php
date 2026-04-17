<?php

namespace App\Models;

use App\Constants\SalaryType;
use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherSalaryConfig extends Model
{
    use HasBigIntId;

    protected $table = 'teacher_salary_configs';

    protected $fillable = [
        'teacher_id',
        'salary_per_session',
        'salary_type',
    ];

    protected function casts(): array
    {
        return [
            'salary_per_session' => 'decimal:0',
            'salary_type' => SalaryType::class,
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }


    public function isActive(): bool
    {
        return $this->effective_to === null || $this->effective_to->isFuture();
    }
}
