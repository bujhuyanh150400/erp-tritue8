<?php

namespace App\Models;

use App\Constants\SalaryType;
use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffSalaryConfig extends Model
{
    use HasBigIntId;

    protected $table = 'staff_salary_configs';
    protected $fillable = [
        'staff_id',
        'salary_type',
        'salary_amount',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'salary_type'    => SalaryType::class,
            'salary_amount'  => 'decimal:0',
            'effective_from' => 'date',
            'effective_to'   => 'date',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
