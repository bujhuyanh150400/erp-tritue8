<?php

namespace App\Http\Controllers;

use App\Core\Controller\BaseController;
use App\Http\Requests\ListSubjectRequest;
use App\Http\Requests\SubjectCreateRequest;
use App\Http\Requests\SubjectUpdateRequest;
use App\Http\Resources\SubjectItemResource;
use App\Http\Resources\SubjectListResource;
use App\Services\SubjectService;
use Illuminate\Http\Request;
use Inertia\Response;

class SubjectController extends BaseController
{
    public function __construct(
        protected SubjectService $subjectService
    ) {}

    /**
     * Lấy danh sách môn học
     * @return Response
     */
    public function listSubject(ListSubjectRequest $request)
    {
        $params = $request->getFilterOptions();

        $result = $this->subjectService->getListSubjects($params);

        $data = $result->getData();

        return $this->rendering('subjects/list', [
            'subjects' => SubjectListResource::collection($data),
            'filters' => $params->toArray(),
        ]);
    }

    /**
     * Hiển thị form tạo môn học
     * @return Response
     */
    public function viewCreate()
    {
        return $this->rendering('subjects/form');
    }

    public function create(SubjectCreateRequest $request)
    {
        $result = $this->subjectService->createSubject($request->validated());

        if ($result->isSuccess()) {
            $this->success($result->getMessage());

            return redirect()->route('subjects.list');
        }

        $this->error($result->getMessage());

        return back()->withInput();
    }

    public function viewUpdate(int $id, Request $request)
    {
        $result = $this->subjectService->getSubjectById($id);

        if ($result->isSuccess()) {
            return $this->rendering('subjects/form', [
                'subjects' => SubjectItemResource::make($result->getData())->toArray($request),
            ]);
        }

        $this->error($result->getMessage());

        return redirect()->back();
    }

    public function update(SubjectUpdateRequest $request, int $id)
    {
        $result = $this->subjectService->updateSubject($id, $request->validated());

        if ($result->isSuccess()) {
            $this->success($result->getMessage());

            return redirect()->route('subjects.list');
        }

        $this->error($result->getMessage());

        return back()->withInput();
    }

    public function delete(int $id)
    {
        $result = $this->subjectService->deleteSubject($id);

        if ($result->isSuccess()) {
            $this->success($result->getMessage());

            return redirect()->route('subjects.list');
        }

        $this->error($result->getMessage());

        return redirect()->back();
    }
}
