<?php

namespace App\Http\Controllers;

use App\Core\Controller\BaseController;
use App\Http\Requests\ListStudentRequest;
use App\Http\Requests\StudentCreateRequest;
use App\Http\Requests\StudentUpdateRequest;
use App\Http\Resources\StudentListResource;
use App\Services\StudentService;

class StudentController extends BaseController
{
    public function __construct(
        protected StudentService $studentService
    ) {}

    public function createStudent(StudentCreateRequest $request)
    {
        $result = $this->studentService->createStudent($request->validated());
        if ($result->isSuccess()) {
            $this->success($result->getMessage());

            return redirect()->back();
        }

        return back()->withErrors(['error' => $result->getMessage()]);
    }

    public function updateStudent(StudentUpdateRequest $request, int $studentId)
    {
        $result = $this->studentService->updateStudent($studentId, $request->validated());
        if ($result->isSuccess()) {
            $this->success($result->getMessage());

            return redirect()->back();
        }

        return back()->withErrors(['error' => $result->getMessage()]);
    }

    public function deletedStudent(int $studentId)
    {
        $result = $this->studentService->deleteStudent($studentId);
        if ($result->isSuccess()) {
            $this->success($result->getMessage());

            return redirect()->back();
        }

        return back()->withErrors(['error' => $result->getMessage()]);
    }

    public function listStudent(ListStudentRequest $request)
    {
        $params = $request->getFilterOptions();
        $result = $this->studentService->getListStudents($params);
        if ($result->isSuccess()) {
            return $this->rendering('students/list', [
                'students' => StudentListResource::collection($result->getData()),
                'filters' => $params->toArray(),
            ]);
        }
        $this->error($result->getMessage());

        return back()->withErrors(['error' => $result->getMessage(),
        ]);
    }
}
