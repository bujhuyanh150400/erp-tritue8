<?php

namespace App\Http\Controllers;

use App\Core\Controller\BaseController;
use App\Http\Requests\ListStudentRequest;
use App\Http\Requests\StudentCreateRequest;
use App\Http\Requests\StudentUpdateRequest;
use App\Http\Resources\StudentItemResource;
use App\Http\Resources\StudentListResource;
use App\Services\StudentService;
use Illuminate\Http\Request;

class StudentController extends BaseController
{
    public function __construct(
        protected StudentService $studentService
    ) {}

    public function listStudent(ListStudentRequest $request)
    {
        $params = $request->getFilterOptions();
        $result = $this->studentService->getListStudents($params);
        if ($result->isError()) {
            $this->error($result->getMessage());
        }
        $data = $result->getData() ?? [];
        return $this->rendering('students/list', [
            'students' => StudentListResource::collection($data),
            'filters' => $params->toArray(),
        ]);
    }

    public function viewCreate()
    {
        return $this->rendering('students/form');
    }

    public function create(StudentCreateRequest $request)
    {
        $data = $request->validated();
        $result = $this->studentService->createStudent($data);
        if ($result->isSuccess()) {
            $this->success($result->getMessage());

            return redirect()->route('student.list');
        }
        $this->error($result->getMessage());

        return back()->withInput();
    }

    public function viewUpdate(int $id, Request $request)
    {
        $result = $this->studentService->getStudentById($id);
        if ($result->isSuccess()) {
            return $this->rendering('students/form', [
                'student' => StudentItemResource::make($result->getData())->toArray($request),
            ]);
        }
        $this->error($result->getMessage());

        return redirect()->back();
    }

    public function update(StudentUpdateRequest $request, int $id)
    {
        $data = $request->validated();
        $result = $this->studentService->updateStudent($id, $data);
        if ($result->isSuccess()) {
            $this->success($result->getMessage());

            return redirect()->route('student.list');
        }
        $this->error($result->getMessage());

        return back()->withInput();
    }
}
