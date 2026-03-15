<?php


namespace App\Services;

use App\Core\DTOs\FilterDTO;
use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Repositories\SubjectRepository;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class SubjectService extends BaseService
{
    public function __construct(
        protected SubjectRepository $subjectRepository
    )
    {
    }

    public function getListSubjects(FilterDTO $dto): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($dto) {

                $subjects = $this->subjectRepository->paginate(
                    filters: $dto->getFilters(),
                    perPage: $dto->getPerPage(),
                    page: $dto->getPage(),
                    orderBy: $dto->getSortBy(),
                    orderDirection: $dto->getDirection()
                );

                return ServiceReturn::success($subjects);
            },
            returnCatchCallback: function () use ($dto) {
                return ServiceReturn::success(
                    data: new LengthAwarePaginator(
                        items: [],
                        total: 0,
                        perPage: $dto->getPerPage(),
                        currentPage: $dto->getPage()
                    )
                );
            }
        );
    }

    public function createSubject(array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($data) {

                $subject = $this->subjectRepository->create([
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'is_active' => true,
                ]);

                Logging::userActivity(
                    action: 'Tạo môn học',
                    description: 'Tạo môn học ' . $subject->name
                );

                return ServiceReturn::success(
                    message: 'Tạo môn học thành công'
                );
            },
            useTransaction: true
        );
    }

    public function getSubjectById(int $id): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($id) {

                $subject = $this->subjectRepository->findById($id);

                if (!$subject) {
                    throw new ServiceException('Môn học không tồn tại.');
                }

                return $subject;
            }
        );
    }

    public function updateSubject(int $id, array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($id, $data) {

                $subject = $this->subjectRepository->findById($id);

                if (!$subject) {
                    throw new ServiceException('Môn học không tồn tại.');
                }

                $updated = $this->subjectRepository->updateById($id, [
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'is_active' => $data['is_active'],
                    'updated_at' => now(),
                ]);

                Logging::userActivity(
                    action: 'Cập nhật môn học',
                    description: 'Cập nhật môn học ' . $subject->name
                );

                return ServiceReturn::success($updated, 'Cập nhật môn học thành công');
            },
            useTransaction: true
        );
    }

    public function deleteSubject(int $id): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($id) {

                $subject = $this->subjectRepository->findById($id);

                if (!$subject) {
                    throw new ServiceException('Môn học không tồn tại.');
                }

                $this->subjectRepository->deleteById($id);

                Logging::userActivity(
                    action: 'Xóa môn học',
                    description: 'Xóa môn học ' . $subject->name
                );

                return ServiceReturn::success(null, 'Xóa môn học thành công');
            },
            useTransaction: true
        );
    }
}
