<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admission\ApproveAdmissionRequest;
use App\Http\Requests\Admission\ListAdmissionsRequest;
use App\Http\Requests\Admission\RejectAdmissionRequest;
use App\Http\Resources\AdmissionDetailResource;
use App\Http\Resources\AdmissionListResource;
use App\Http\Resources\ApprovedStudentResource;
use App\Models\AdmissionApplication;
use App\Services\AdmissionService;
use Illuminate\Http\JsonResponse;

/**
 * Admin-facing admission review reads. Branch isolation is automatic via
 * BranchScope, so out-of-branch applications are model-not-found (404).
 */
class AdmissionController extends ApiController
{
    public function __construct(private readonly AdmissionService $admissions) {}

    /**
     * Display a paginated, filterable listing of applications (default pending).
     */
    public function index(ListAdmissionsRequest $request): JsonResponse
    {
        $applications = $this->admissions->list(
            $request->only(['status', 'desired_class_id', 'from', 'to', 'search']),
            $request->integer('per_page', 15),
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => AdmissionListResource::collection($applications)->resolve($request),
            'meta' => [
                'current_page' => $applications->currentPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
                'last_page' => $applications->lastPage(),
            ],
        ]);
    }

    /**
     * Display the full application detail incl. media + previous educations.
     */
    public function show(AdmissionApplication $admission): JsonResponse
    {
        return $this->success(
            AdmissionDetailResource::make($this->admissions->loadDetail($admission)),
        );
    }

    /**
     * Approve an application: create the student (login, enrollment, optional
     * parent) in one transaction and mark the application approved.
     */
    public function approve(ApproveAdmissionRequest $request, AdmissionApplication $admission): JsonResponse
    {
        $result = $this->admissions->approve($admission, $request->validated());

        return $this->success([
            'student' => ApprovedStudentResource::make($result['student']),
            'parent_created' => $result['parent_created'],
        ], 'Admission approved. Student account created.');
    }

    /**
     * Reject an application with a reason.
     */
    public function reject(RejectAdmissionRequest $request, AdmissionApplication $admission): JsonResponse
    {
        $this->admissions->reject($admission, $request->validated()['rejection_reason']);

        return $this->success(null, 'Application rejected.');
    }
}
