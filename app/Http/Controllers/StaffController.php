<?php

namespace App\Http\Controllers;

use App\Core\Controller\BaseController;
use App\Http\Requests\StaffCreateRequest;
use App\Http\Requests\StaffUpdateRequest;
use App\Services\StaffService;
use Illuminate\Http\Request;

class StaffController extends BaseController
{
    public function __construct(
        protected StaffService $staffService
    ) {}

    public function createStaff(StaffCreateRequest $request)
    {
        $result = $this->staffService->createStaff($request->validated());
        if ($result->isSuccess()) {
            $this->success($result->getMessage());
            return redirect()->back();
        }
        return back()->withErrors(['error' => $result->getMessage()]);
    }

    public function updateStaff(StaffUpdateRequest $request, int $staffId)
    {
        $result = $this->staffService->updateStaff($staffId, $request->validated());
        if ($result->isSuccess()) {
            $this->success($result->getMessage());
            return redirect()->back();
        }

        return back()->withErrors(['error' => $result->getMessage()]);
    }

    public function deleteStaff(int $staffId)
    {
        $result = $this->staffService->deleteStaff($staffId);

        if ($result->isSuccess()) {
            $this->success($result->getMessage());
            return redirect()->back();
        }

        return back()->withErrors(['error' => $result->getMessage()]);
    }

    public function listStaff(Request $request)
    {
        $result = $this->staffService->getListStaff($request->all());

        if ($result->isSuccess()) {
            return view('staff.index', [
                'staff' => $result->getData()
            ]);
        }

        return back()->withErrors(['error' => $result->getMessage()]);
    }
}
