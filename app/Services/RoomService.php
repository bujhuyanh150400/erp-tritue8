<?php


namespace App\Services;

use App\Constants\RoomStatus;
use App\Core\DTOs\FilterDTO;
use App\Core\Logs\Logging;
use App\Core\Services\BaseService;
use App\Core\Services\ServiceException;
use App\Core\Services\ServiceReturn;
use App\Repositories\RoomRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class RoomService extends BaseService
{
    public function __construct(
        protected RoomRepository $roomRepository
    )
    {
    }

    public function getListRooms(FilterDTO $dto): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($dto) {

                $rooms = $this->roomRepository->paginate(
                    filters: $dto->getFilters(),
                    perPage: $dto->getPerPage(),
                    page: $dto->getPage(),
                    orderBy: $dto->getSortBy(),
                    orderDirection: $dto->getDirection()
                );

                return ServiceReturn::success($rooms);
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

    public function createRoom(array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($data) {

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

    public function getRoomById(int $id): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($id) {

                $room = $this->roomRepository->findById($id);

                if (!$room) {
                    throw new ServiceException('Phòng học không tồn tại.');
                }

                return $room;
            }
        );
    }

    public function updateRoom(int $id, array $data): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($id, $data) {

                $room = $this->roomRepository->findById($id);

                if (!$room) {
                    throw new ServiceException('Phòng học không tồn tại.');
                }

                $updated = $this->roomRepository->updateById($id, [
                    'name' => $data['name'],
                    'capacity' => $data['capacity'],
                    'note' => $data['note'] ?? null,
                    'status' => $data['status'],
                    'updated_at' => now(),
                ]);

                Logging::userActivity(
                    action: 'Cập nhật phòng học',
                    description: 'Cập nhật phòng học ' . $room->name
                );

                return ServiceReturn::success($updated, 'Cập nhật phòng học thành công');
            },
            useTransaction: true
        );
    }

    public function deleteRoom(int $id): ServiceReturn
    {
        return $this->execute(
            callback: function () use ($id) {

                $room = $this->roomRepository->findById($id);

                if (!$room) {
                    throw new ServiceException('Phòng học không tồn tại.');
                }

                $this->roomRepository->deleteById($id);

                Logging::userActivity(
                    action: 'Xóa phòng học',
                    description: 'Xóa phòng học ' . $room->name
                );

                return ServiceReturn::success(null, 'Xóa phòng học thành công');
            },
            useTransaction: true
        );
    }
}
