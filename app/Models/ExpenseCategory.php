<?php

namespace App\Models;

use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use HasBigIntId;

    protected $table = 'expense_categories';

    protected $fillable = [
        'name',
        'description',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(ExpenseInvoice::class, 'category_id');
    }
}
