<?php


namespace App\Repositories;


use App\Constants\ClassStatus;
use App\Core\Interfaces\FilterFilament;
use App\Core\Repository\BaseRepository;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;

class SubjectRepository extends BaseRepository implements FilterFilament
{
    public function getModel(): string
    {
        return Subject::class;
    }

    public function getListingQuery(Builder $query): Builder
    {
        return $this->query()
            ->withCount(['classes as active_classes_count' => function (Builder $query) {
                $query->where('status', ClassStatus::Active);
            }]);
    }

    public function setFilters(Builder $query, array $filters = []): Builder
    {
        if (!empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function (Builder $q) use ($keyword) {
                $q->where('name', 'ilike', '%' . $keyword . '%');
                if (is_numeric($keyword)) {
                    $q->orWhere('id', $keyword);
                }
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', $filters['is_active']);
        }

        return $query;
    }

}
