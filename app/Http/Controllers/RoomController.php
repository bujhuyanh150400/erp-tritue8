<?php


namespace App\Http\Controllers;

use App\Core\Controller\BaseController;
use App\Http\Requests\ListRoomRequest;
use App\Http\Requests\RoomCreateRequest;
use App\Http\Requests\RoomUpdateRequest;
use App\Http\Resources\RoomItemResource;
use App\Http\Resources\RoomListResource;
use App\Services\RoomService;
use Illuminate\Http\Request;

class RoomController extends BaseController
{
    public function __construct(
        protected RoomService $roomService
    )
    {
    }

    public function listRoom(ListRoomRequest $request)
    {
        $params = $request->getFilterOptions();

        $result = $this->roomService->getListRooms($params);

        if ($result->isError()) {
            return $this->error($result->getMessage());
        }

        $data = $result->getData() ?? [];

        return $this->rendering('rooms/list', [
            'rooms' => RoomListResource::collection($data),
            'filters' => $params->toArray(),
        ]);
    }

    public function viewCreate()
    {
        return $this->rendering('rooms/form');
    }

    public function create(RoomCreateRequest $request)
    {
        $result = $this->roomService->createRoom($request->validated());

        if ($result->isSuccess()) {
            $this->success($result->getMessage());
            return redirect()->route('room.list');
        }

        $this->error($result->getMessage());

        return back()->withInput();
    }

    public function viewUpdate(int $id, Request $request)
    {
        $result = $this->roomService->getRoomById($id);

        if ($result->isSuccess()) {
            return $this->rendering('rooms/form', [
                'room' => RoomItemResource::make($result->getData())->toArray($request),
            ]);
        }

        $this->error($result->getMessage());

        return redirect()->back();
    }

    public function update(RoomUpdateRequest $request, int $id)
    {
        $result = $this->roomService->updateRoom($id, $request->validated());

        if ($result->isSuccess()) {
            $this->success($result->getMessage());
            return redirect()->route('room.list');
        }

        $this->error($result->getMessage());

        return back()->withInput();
    }

    public function delete(int $id)
    {
        $result = $this->roomService->deleteRoom($id);

        if ($result->isSuccess()) {
            $this->success($result->getMessage());
            return redirect()->route('room.list');
        }

        $this->error($result->getMessage());

        return redirect()->back();
    }
}
