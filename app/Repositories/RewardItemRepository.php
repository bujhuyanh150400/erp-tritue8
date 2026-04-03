<?php

namespace App\Repositories;

use App\Core\Interfaces\Paginate;
use App\Core\Repository\BaseRepository;
use App\Models\RewardItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class RewardItemRepository extends BaseRepository implements Paginate
{
    public function getModel(): string
    {
        return RewardItem::class;
    }

    public function paginate(
        array $filters = [],
        int $perPage = 10,
        int $page = 1,
        string $orderBy = 'points_required',
        string $orderDirection = 'asc'
    ): LengthAwarePaginator {
        $query = $this->getQuery();
        $query = $this->filters($query, $filters);
        $query = $this->sort($query, $orderBy, $orderDirection);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function filters($query, array $filters = []): Builder
    {
        if (!empty($filters['keyword'])) {
            $keyword = trim($filters['keyword']);
            $query->where('name', 'like', "%{$keyword}%");
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query;
    }

    public function sort(Builder $query, string $orderBy, string $orderDirection): Builder
    {
        return $query->orderBy($orderBy, $orderDirection);
    }

    /**
     * Kiểm tra xem phần thưởng đã được đổi chưa
     */
    public function hasRedemptions(int $id): bool
    {
        return \DB::table('reward_redemptions')->where('reward_item_id', $id)->exists();
    }

    public function getActiveCatalog(): Collection
    {
        return $this->query()
            ->select(['id', 'name', 'points_required', 'reward_type'])
            ->where('is_active', true)
            ->orderBy('points_required')
            ->get();
    }

    public function findActiveById(int $id): ?RewardItem
    {
        return $this->query()
            ->where('id', $id)
            ->where('is_active', true)
            ->first();
    }
}
