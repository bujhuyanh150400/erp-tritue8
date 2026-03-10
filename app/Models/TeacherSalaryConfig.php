<?php

namespace App\Models;

use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherSalaryConfig extends Model
{
    use HasBigIntId;

    protected $table = 'teacher_salary_configs';

    protected $fillable = [
        'teacher_id',
        'class_id',
        'salary_per_session',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'salary_per_session' => 'decimal:0',
            'effective_from'     => 'date',
            'effective_to'       => 'date',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function isActive(): bool
    {
        return $this->effective_to === null || $this->effective_to->isFuture();
    }
}
