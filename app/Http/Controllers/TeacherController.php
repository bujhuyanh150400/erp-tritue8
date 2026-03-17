<?php

namespace App\Http\Controllers;

use App\Core\Controller\BaseController;
use App\Http\Requests\ListTeacherRequest;
use App\Http\Requests\TeacherCreateRequest;
use App\Http\Requests\TeacherUpdateRequest;
use App\Http\Resources\TeacherItemResource;
use App\Http\Resources\TeacherListResource;
use App\Services\TeacherService;
use Illuminate\Http\Request;

class TeacherController extends BaseController
{
    public function __construct(
        protected TeacherService $teacherService
    ) {}

    public function listTeacher(ListTeacherRequest $request)
    {
        $params = $request->getFilterOptions();

        $result = $this->teacherService->getListTeachers($params);

        $data = $result->getData();

        return $this->rendering('teachers/list', [
            'teachers' => TeacherListResource::collection($data),
            'filters' => $params->toArray(),
        ]);
    }

    public function viewCreate()
    {
        return $this->rendering('teachers/form');
    }

    public function createTeacher(TeacherCreateRequest $request)
    {
        $data = $request->validated();

        $result = $this->teacherService->createTeacher($data);

        if ($result->isSuccess()) {
            $this->success($result->getMessage());
            return redirect()->route('teacher.list');
        }

        $this->error($result->getMessage());

        return back()->withInput();
    }

    public function viewUpdate(int $id, Request $request)
    {
        $result = $this->teacherService->getTeacherById($id);

        if ($result->isSuccess()) {
            return $this->rendering('teachers/form', [
                'teacher' => TeacherItemResource::make($result->getData())->toArray($request),
            ]);
        }

        $this->error($result->getMessage());

        return redirect()->back();
    }

    public function updateTeacher(TeacherUpdateRequest $request, int $id)
    {
        $data = $request->validated();

        $result = $this->teacherService->updateTeacher($id, $data);

        if ($result->isSuccess()) {
            $this->success($result->getMessage());
            return redirect()->route('teacher.list');
        }

        $this->error($result->getMessage());

        return back()->withInput();
    }

    public function deleteTeacher(int $id)
    {
        $result = $this->teacherService->deleteTeacher($id);

        if ($result->isSuccess()) {
            $this->success($result->getMessage());
            return redirect()->route('teacher.list');
        }

        $this->error($result->getMessage());

        return redirect()->back();
    }
}
