<?php


namespace App\Repositories;

use App\Core\Interfaces\Paginate;
use App\Core\Repository\BaseRepository;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class SubjectRepository extends BaseRepository implements Paginate
{
    public function getModel(): string
    {
        return Subject::class;
    }

    public function paginate(
        array  $filters = [],
        int    $perPage = 10,
        int    $page = 1,
        string $orderBy = 'id',
        string $orderDirection = 'desc'
    ): LengthAwarePaginator
    {

        $query = $this->getQuery();

        $query = $this->filters($query, $filters);

        $query = $this->sort($query, $orderBy, $orderDirection);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function filters(Builder $query, array $filters = []): Builder
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
}
