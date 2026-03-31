<?php

namespace App\Models;

use App\Constants\RewardType;
use App\Core\Traits\HasBigIntId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RewardItem extends Model
{
    use HasBigIntId;

    protected $table = 'reward_items';
    protected $fillable = [
        'name',
        'points_required',
        'reward_type',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'reward_type' => RewardType::class,
            'is_active' => 'boolean',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(RewardRedemption::class);
    }
}
