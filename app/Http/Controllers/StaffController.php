<?php

namespace App\Http\Controllers;

use App\Core\Controller\BaseController;
use App\Http\Requests\ListStaffRequest;
use App\Http\Requests\StaffCreateRequest;
use App\Http\Requests\StaffUpdateRequest;
use App\Http\Resources\StaffItemResource;
use App\Http\Resources\StaffListResource;
use App\Services\StaffService;
use Illuminate\Http\Request;

class StaffController extends BaseController
{
    public function __construct(
        protected StaffService $staffService
    ) {}

    public function listStaff(ListStaffRequest $request)
    {
        $params = $request->getFilterOptions();

        $result = $this->staffService->getListStaff($params);

        if ($result->isError()) {
            return $this->error($result->getMessage());
        }

        $data = $result->getData() ?? [];

        return $this->rendering('staff/list', [
            'staff' => StaffListResource::collection($data),
            'filters' => $params->toArray(),
        ]);
    }

    public function viewCreate()
    {
        return $this->rendering('staff/form');
    }

    public function createStaff(StaffCreateRequest $request)
    {
        $data = $request->validated();

        $result = $this->staffService->createStaff($data);

        if ($result->isSuccess()) {
            $this->success($result->getMessage());
            return redirect()->route('staff.list');
        }

        $this->error($result->getMessage());

        return back()->withInput();
    }

    public function viewUpdate(int $id, Request $request)
    {
        $result = $this->staffService->getStaffById($id);

        if ($result->isSuccess()) {
            return $this->rendering('staff/form', [
                'staff' => StaffItemResource::make($result->getData())->toArray($request),
            ]);
        }

        $this->error($result->getMessage());

        return redirect()->back();
    }

    public function updateStaff(StaffUpdateRequest $request, int $id)
    {
        $data = $request->validated();

        $result = $this->staffService->updateStaff($id, $data);

        if ($result->isSuccess()) {
            $this->success($result->getMessage());
            return redirect()->route('staff.list');
        }

        $this->error($result->getMessage());

        return back()->withInput();
    }

    public function deleteStaff(int $id)
    {
        $result = $this->staffService->deleteStaff($id);

        if ($result->isSuccess()) {
            $this->success($result->getMessage());
            return redirect()->route('staff.list');
        }

        $this->error($result->getMessage());

        return redirect()->back();
    }
}
