<?php


namespace App\Repositories;

use App\Constants\ClassStatus;
use App\Core\Interfaces\FilterFilament;
use App\Core\Repository\BaseRepository;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;

use Illuminate\Support\Facades\DB;

class RoomRepository extends BaseRepository implements FilterFilament
{
    public function getModel(): string
    {
        return Room::class;
    }

    public function getListingQuery(Builder $query): Builder
    {
        return $this->query()
            ->withCount([
                'scheduleTemplates as active_classes_count' => function (Builder $q) {
                    $q->currentlyActive()
                        ->whereHas('class', function (Builder $classQuery) {
                            // Ràng buộc lớp đó phải đang Active
                            $classQuery->where('status', ClassStatus::Active->value);
                        })
                        // Ép đếm distinct class_id thay vì đếm số dòng template
                        ->select(DB::raw('COUNT(DISTINCT class_id)'));
                }
            ]);
    }

    public function setFilters(Builder $query, array $filters = []): Builder
    {
        if (!empty($filters['keyword'])) {
            $query->where('name', 'ilike', '%' . $filters['keyword'] . '%');
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }


}
