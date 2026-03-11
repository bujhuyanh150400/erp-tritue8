<?php

namespace App\Http\Controllers;

use App\Core\Controller\BaseController;
use App\Http\Requests\TeacherCreateRequest;
use App\Http\Requests\TeacherUpdateRequest;
use App\Services\TeacherService;
use Illuminate\Http\Request;

class TeacherController extends BaseController
{
    public function __construct(
        protected TeacherService $teacherService
    ) {}

    public function createTeacher(TeacherCreateRequest $request)
    {
        $result = $this->teacherService->createTeacher($request->validated());

        if ($result->isSuccess()) {
            $this->success($result->getMessage());
            return redirect()->back();
        }

        return back()->withErrors(['error' => $result->getMessage()]);
    }

    public function updateTeacher(TeacherUpdateRequest $request, int $teacherId)
    {
        $result = $this->teacherService->updateTeacher($teacherId, $request->validated());

        if ($result->isSuccess()) {
            $this->success($result->getMessage());
            return redirect()->back();
        }

        return back()->withErrors(['error' => $result->getMessage()]);
    }

    public function deleteTeacher(int $teacherId)
    {
        $result = $this->teacherService->deleteTeacher($teacherId);

        if ($result->isSuccess()) {
            $this->success($result->getMessage());
            return redirect()->back();
        }

        return back()->withErrors(['error' => $result->getMessage()]);
    }

    public function listTeacher(Request $request)
    {
        $result = $this->teacherService->getListTeachers($request->all());

        if ($result->isSuccess()) {
            return view('teachers.index', [
                'teachers' => $result->getData()
            ]);
        }

        return back()->withErrors(['error' => $result->getMessage()]);
    }
}
