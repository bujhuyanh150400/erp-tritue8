<?php


namespace App\Services;

use App\Constants\ClassStatus;
use App\Constants\RoomStatus;
use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Interface\SelectableServiceInterface;
use App\Models\Room;
use App\Repositories\ClassRepository;
use App\Repositories\ClassScheduleTemplateRepository;
use App\Repositories\RoomRepository;
use Illuminate\Database\Eloquent\Builder;

class RoomService extends BaseService implements SelectableServiceInterface
{
    public function __construct(
        protected RoomRepository $roomRepository,
        protected ClassRepository $classRepository,
        protected ClassScheduleTemplateRepository $classScheduleTemplateRepository,
    )
    {
    }

    /**
     * Thay đổi trạng thái khóa phòng học
     * @param Room $room
     * @return ServiceReturn
     * @throws \Throwable
     */

    public function toggleLock(Room $room): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($room) {

                // So sánh thẳng với Enum
                if ($room->status === RoomStatus::Active) {

                    // Kiểm tra xem phòng có đang được sử dụng bởi lớp nào không
                    $activeClassesCount = $this->classScheduleTemplateRepository->countClassesActive($room->id);
                    if ($activeClassesCount > 0) {
                        throw new ServiceException("Phòng đang được sử dụng bởi {$activeClassesCount} lớp học, không thể tạm khóa.");
                    }

                    // Cập nhật bằng Enum
                    $room->update(['status' => RoomStatus::Locked]);
                    $actionName = 'Khóa phòng học';
                }
                else {
                    // Cập nhật bằng Enum
                    $room->update(['status' => RoomStatus::Active]);
                    $actionName = 'Mở khóa phòng học';
                }
                Logging::userActivity(
                    action: $actionName,
                    description: "{$actionName}: {$room->name}"
                );
                return ServiceReturn::success();
            }
        );
    }

    /**
     * Thay đổi trạng thái phòng học
     * @param Room $room
     * @param RoomStatus $newStatus
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function changeStatus(Room $room, RoomStatus $newStatus): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($room, $newStatus) {

                // Nếu là khóa phòng, kiểm tra xem có lớp nào đang diễn ra không
                if ($newStatus === RoomStatus::Locked) {
                    $hasSchedules = $this->classScheduleTemplateRepository->hasActiveSchedulesForRoom($room->id);

                    if ($hasSchedules) {
                        throw new ServiceException('Không thể khóa phòng khi đang có lớp diễn ra ở phòng này.');
                    }
                }

                // 2. Cập nhật trạng thái
                $oldStatusLabel = $room->status->label();
                $room->update(['status' => $newStatus]);

                Logging::userActivity(
                    action: 'Đổi trạng thái phòng học',
                    description: "Đổi trạng thái phòng {$room->name} từ [{$oldStatusLabel}] sang [{$newStatus->label()}]"
                );

                return ServiceReturn::success();
            },
            useTransaction: true
        );
    }

    /**
     * Tạo phòng học mới
     * @param array $data
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function createRoom(array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($data) {
                $nameExists = $this->roomRepository->query()
                    ->where('name', $data['name'])
                    ->exists();
                if ($nameExists) {
                    throw new ServiceException('Tên phòng học đã tồn tại trong hệ thống.');
                }
                $room = $this->roomRepository->create([
                    'name' => $data['name'],
                    'capacity' => $data['capacity'] ?? 0,
                    'note' => $data['note'] ?? null,
                    'status' => RoomStatus::Active,
                ]);

                Logging::userActivity(
                    action: 'Tạo phòng học',
                    description: 'Tạo phòng học ' . $room->name
                );

                return ServiceReturn::success(
                    message: 'Tạo phòng học thành công'
                );
            },
            useTransaction: true
        );
    }

    /**
     * Cập nhật thông tin phòng học
     * @param Room $record
     * @param array $data
     * @return ServiceReturn
     * @throws \Throwable
     */
    public function updateRoom(Room $record, array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($record, $data) {
                // Kiểm tra trùng tên phòng học
                $nameExists = $this->roomRepository->query()
                    ->where('name', $data['name'])
                    ->where('id', '!=', $record->id)
                    ->exists();
                if ($nameExists) {
                    throw new ServiceException('Tên phòng học đã tồn tại trong hệ thống.');
                }

                // NGHIỆP VỤ: Kiểm tra khi GIẢM sức chứa
                $newCapacity = (int) $data['capacity'];

                if ($newCapacity < $record->capacity) {
                    // Kiểm tra xem có lớp nào đang dùng phòng và có sĩ số vượt quá sức chứa mới không
                    $overcrowdedClasses = $this->classRepository->getClassesExceedingCapacity(
                        roomId:     $record->id,
                        capacity:   $newCapacity
                    );
                    // Nếu có lớp nào vượt quá sức chứa mới, báo lỗi
                    if ($overcrowdedClasses->isNotEmpty()) {
                        // Gom danh sách các lớp bị vượt giới hạn để báo lỗi chi tiết
                        $errorDetails = [];
                        foreach ($overcrowdedClasses as $cls) {
                            $errorDetails[] = "- Lớp {$cls->name} đang có {$cls->si_so} học sinh.";
                        }

                        $msg = "Không thể giảm sức chứa xuống {$newCapacity} chỗ vì:<br>" . implode('<br>', $errorDetails);
                        throw new ServiceException($msg);
                    }
                }
                // Thực hiện Cập nhật
                $updated = $this->roomRepository->updateById($record->id, [
                    'name'     => $data['name'],
                    'capacity' => $data['capacity'],
                    'note'     => $data['note'] ?? null,
                ]);

                // Ghi Log
                Logging::userActivity(
                    action: 'Cập nhật phòng học',
                    description: "Cập nhật phòng: {$record->name} (Sức chứa: {$record->capacity}, Trạng thái: {$record->status->label()})"
                );

                return ServiceReturn::success($updated, 'Cập nhật phòng học thành công');
            },
            useTransaction: true
        );
    }

    /**
     * Lấy danh sách phòng học cho dropdown
     * @param string|null $search
     * @param array $filters
     * @return ServiceReturn
     */
    public function getOptions(?string $search = null, array $filters = []) : ServiceReturn{
        return $this->execute(function () use ($search, $filters) {
            return $this->roomRepository->query()
                ->select(['id', 'name', 'capacity']) // Select thêm cột capacity
                ->where('status', RoomStatus::Active)
                ->when(!empty($search), function (Builder $query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'ilike', "%{$search}%");
                    });
                })
                ->orderBy('id', 'DESC')
                ->limit(10)
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->id => "{$item->name} - (Sức chứa: {$item->capacity})"];
                })
                ->toArray();
        });
    }
    /**
     * Lấy tên phòng học theo ID
     * @param mixed $id
     * @return ServiceReturn
     */
    public function getLabelOption(mixed $id) : ServiceReturn{
        return $this->execute(function () use ($id) {
            if (empty($id)) {
                return null;
            }
            $class = $this->roomRepository->query()
                ->select(['name', 'capacity'])
                ->where('id', $id)
                ->first();
            return $class ? "{$class->name} - (Sức chứa: {$class->capacity})" : null;
        });
    }
}
