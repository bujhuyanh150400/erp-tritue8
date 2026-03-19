<?php


namespace App\Services;

use App\Core\DTOs\FilterDTO;
use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Interface\SelectableServiceInterface;
use App\Repositories\SubjectRepository;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class SubjectService extends BaseService implements SelectableServiceInterface
{
    public function __construct(
        protected SubjectRepository $subjectRepository
    )
    {
    }

    /**
     * Tìm kiếm môn học theo keyword
     * @param string $search
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function getOptions(?string $search = null): ServiceReturn
    {
        return $this->execute(function () use ($search) {
            return $this->subjectRepository->query()
                ->when($search, fn($q) => $q->where('name', 'ilike', "%{$search}%"))
                ->orderBy('name')
                ->limit(10)
                ->pluck('name', 'id')
                ->toArray();
        });
    }

    public function getLabelOption($id): ServiceReturn
    {
        return $this->execute(function () use ($id) {
            if (empty($id)) {
                return null;
            }
            return $this->subjectRepository->query()
                ->where('id', $id)
                ->value('name');
        });
    }

}
