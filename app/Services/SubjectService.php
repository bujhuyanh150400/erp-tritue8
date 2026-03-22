<?php


namespace App\Services;

use App\Constants\ClassStatus;
use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Interface\SelectableServiceInterface;
use App\Models\Subject;
use App\Repositories\ClassRepository;
use App\Repositories\SubjectRepository;

class SubjectService extends BaseService implements SelectableServiceInterface
{
    public function __construct(
        protected SubjectRepository $subjectRepository,
        protected ClassRepository $classRepository
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

    /**
     * Lấy tên môn học theo id
     * @param $id
     * @return ServiceReturn
     * @throws \Throwable
     */
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

    /**
     * Bật/tắt môn học
     * @param Subject $record
     * @return ServiceReturn
     */
    public function toggleActive(Subject $record): ServiceReturn
    {
        return $this->execute(function () use ($record) {
            if ($record->is_active) {
                $activeClasses = $this->classRepository->countClassActiveBySubjectId($record->id);

                if ($activeClasses > 0) {
                    throw new ServiceException("Môn học đang được dùng bởi {$activeClasses} lớp đang hoạt động, không thể tắt hoạt động.");

                }
            }
            // Nếu qua được vòng kiểm tra (hoặc là thao tác Bật) -> Cập nhật
            $record->update([
                'is_active' => !$record->is_active
            ]);
        });
    }

    /**
     * Tạo môn học
     * @param array $data
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function createSubject(array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($data) {
                // 1. Kiểm tra trùng lặp tên môn học
                $exists = $this->subjectRepository->query()
                    ->where('name', $data['name'])
                    ->exists();

                if ($exists) {
                    throw new ServiceException('Tên môn học đã tồn tại');
                }

                // 2. Thêm mới môn học
                $subject = $this->subjectRepository->query()->create([
                    'name'        => $data['name'],
                    'description' => $data['description'] ?? null,
                    'is_active'   => $data['is_active'] ?? true,
                ]);

                // 3. Ghi log hoạt động
                Logging::userActivity(
                    action: 'Tạo môn học',
                    description: "Tạo mới môn học: {$subject->name}"
                );
                return ServiceReturn::success(data: $subject);
            },
            useTransaction: true
        );
    }

    /**
     * Cập nhật môn học
     * @param Subject $record
     * @param array $data
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function updateSubject(Subject $record, array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($record, $data) {

                // Kiểm tra trùng tên (Loại trừ ID hiện tại)
                $exists = $this->subjectRepository->query()
                    ->where('name', $data['name'])
                    ->where('id', '!=', $record->id)
                    ->exists();

                if ($exists) {
                    throw new ServiceException('Tên môn học đã tồn tại trong hệ thống.');
                }

                //  Kiểm tra khi tắt hoạt động
                $newIsActive = $data['is_active'] ?? true;

                // Nếu trạng thái cũ đang là True, và trạng thái mới truyền lên là False
                if ($record->is_active === true && $newIsActive === false) {

                    // Đếm số lớp đang dùng môn này
                    $activeClassesCount = $this->classRepository->countClassActiveBySubjectId($record->id);
                    if ($activeClassesCount > 0) {
                        throw new ServiceException("Môn học đang được dùng bởi {$activeClassesCount} lớp đang hoạt động, không thể tắt.");
                    }
                }

                // 3. Thực hiện Cập nhật
                $record->update([
                    'name'        => $data['name'],
                    'description' => $data['description'] ?? null,
                    'is_active'   => $newIsActive,
                ]);

                // 4. Ghi Log
                Logging::userActivity(
                    action: 'Cập nhật môn học',
                    description: "Cập nhật môn học: {$record->name}"
                );

                return ServiceReturn::success(data: $record);
            },
            useTransaction: true
        );
    }

    /**
     * Xóa môn học
     * @param Subject $record
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function deleteSubject(Subject $record): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($record) {

                // Kiểm tra môn học có đang được dùng bởi lớp nào không
                $activeClassesCount = $this->classRepository->countClassActiveBySubjectId($record->id);

                if ($activeClassesCount > 0) {
                    throw new ServiceException("Môn học đang được dùng bởi {$activeClassesCount} lớp đang hoạt động, không thể xóa.");
                }

                // Lưu lại tên trước khi xóa để ghi Log
                $recordName = $record->name;

                // 2. Thực hiện xóa
                $record->delete();

                // 3. Ghi Log
                Logging::userActivity(
                    action: 'Xóa môn học',
                    description: "Xóa môn học: {$recordName}"
                );

                return ServiceReturn::success();
            },
            useTransaction: true
        );
    }

}
