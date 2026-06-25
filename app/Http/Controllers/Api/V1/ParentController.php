<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Parent\LinkStudentRequest;
use App\Http\Requests\Parent\ListParentsRequest;
use App\Http\Requests\Parent\StoreParentRequest;
use App\Http\Resources\LinkedStudentResource;
use App\Http\Resources\ParentResource;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Services\ParentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentController extends ApiController
{
    public function __construct(private readonly ParentService $parents) {}

    /**
     * Display a paginated, searchable listing of parents.
     */
    public function index(ListParentsRequest $request): JsonResponse
    {
        $parents = $this->parents->list(
            $request->only(['search']),
            $request->integer('per_page', 15),
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => ParentResource::collection($parents)->resolve($request),
            'meta' => [
                'current_page' => $parents->currentPage(),
                'per_page' => $parents->perPage(),
                'total' => $parents->total(),
                'last_page' => $parents->lastPage(),
            ],
        ]);
    }

    /**
     * Store a new parent (login + profile + student links); queues credentials.
     */
    public function store(StoreParentRequest $request): JsonResponse
    {
        $parent = $this->parents->create($request->validated());

        return $this->success(
            ParentResource::make($parent),
            'Parent created. Credentials are being sent.',
            201,
        );
    }

    /**
     * Link a student to a parent (duplicate link → 409).
     */
    public function linkStudent(LinkStudentRequest $request, ParentProfile $parent): JsonResponse
    {
        $parent = $this->parents->linkStudent($parent, $request->integer('student_id'));

        return $this->success(ParentResource::make($parent), 'Student linked to parent.');
    }

    /**
     * Unlink a student from a parent (not linked → 404).
     */
    public function unlinkStudent(ParentProfile $parent, Student $student): JsonResponse
    {
        $this->parents->unlinkStudent($parent, $student);

        return $this->success(null, 'Student unlinked from parent.');
    }

    /**
     * Regenerate the parent's password, revoke tokens, and queue new credentials.
     */
    public function resendCredentials(ParentProfile $parent): JsonResponse
    {
        $this->parents->resendCredentials($parent);

        return $this->success(null, 'New credentials are being sent to the parent.');
    }

    /**
     * The authenticated parent's linked students (compact shape). Restricted to
     * the parent role — other roles read their children elsewhere or not at all.
     */
    public function meStudents(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('parent')) {
            abort(403);
        }

        $students = $this->parents->studentsForUser($request->user());

        return $this->success(LinkedStudentResource::collection($students)->resolve($request));
    }
}
