<?php

namespace App\Http\Controllers;

use App\Core\Controller\BaseController;
use App\Http\Requests\StudentCreateRequest;
use App\Services\StudentService;
use App\Http\Requests\StudentUpdateRequest;


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
        return back()->withErrors(['error' => $result->getMessage(),]);
    }

    public function updateStudent(StudentUpdateRequest $request, int $studentId)
    {
        $result = $this->studentService->updateStudent($studentId, $request->validated());
        if ($result->isSuccess()) {
            $this->success($result->getMessage());
            return redirect()->back();
        }
        return back()->withErrors(['error' => $result->getMessage(),]);
    }

    public function deletedStudent(int $studentId)
    {
        $result = $this->studentService->deleteStudent($studentId);
        if ($result->isSuccess()) {
            $this->success($result->getMessage());
            return redirect()->back();
        }
        return back()->withErrors(['error' => $result->getMessage(),]);
    }

    public function listStudent()
    {
        $result = $this->studentService->getListStudents();
        if ($result->isSuccess()) {
            return view('students.index', ['students' => $result->getData()]);
        }
        return back()->withErrors(['error' => $result->getMessage()
        ]);
    }
}
