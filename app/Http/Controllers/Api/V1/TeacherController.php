<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TeacherStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Teacher\BulkTeachersRequest;
use App\Http\Requests\Teacher\ListTeachersRequest;
use App\Http\Requests\Teacher\StoreTeacherRequest;
use App\Http\Requests\Teacher\UpdateTeacherRequest;
use App\Http\Requests\Teacher\UpdateTeacherStatusRequest;
use App\Http\Requests\Teacher\UploadTeacherPhotoRequest;
use App\Http\Resources\TeacherResource;
use App\Models\Teacher;
use App\Services\TeacherService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherController extends ApiController
{
    public function __construct(private readonly TeacherService $teachers) {}

    /**
     * Display a paginated, filterable listing of teachers.
     */
    public function index(ListTeachersRequest $request): JsonResponse
    {
        $teachers = $this->teachers->list(
            $request->withBranchFilter($request->only(['status', 'search', 'sort', 'direction'])),
            $request->integer('per_page', 15),
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => TeacherResource::collection($teachers)->resolve($request),
            'meta' => [
                'current_page' => $teachers->currentPage(),
                'per_page' => $teachers->perPage(),
                'total' => $teachers->total(),
                'last_page' => $teachers->lastPage(),
            ],
        ]);
    }

    /**
     * Display a paginated listing of soft-deleted teachers.
     */
    public function trash(ListTeachersRequest $request): JsonResponse
    {
        $teachers = $this->teachers->listTrashed(
            $request->withBranchFilter($request->only(['status', 'search'])),
            $request->integer('per_page', 15),
        );

        return $this->paginated($teachers, $request);
    }

    /**
     * Build the standard paginated list envelope for teacher collections.
     */
    private function paginated(LengthAwarePaginator $teachers, Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => TeacherResource::collection($teachers)->resolve($request),
            'meta' => [
                'current_page' => $teachers->currentPage(),
                'per_page' => $teachers->perPage(),
                'total' => $teachers->total(),
                'last_page' => $teachers->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created teacher (login + profile + role).
     */
    public function store(StoreTeacherRequest $request): JsonResponse
    {
        $teacher = $this->teachers->create(
            $request->validated(),
            $request->targetBranchId(),
        );

        return $this->success(
            TeacherResource::make($teacher),
            'Teacher created. Credentials are being sent.',
            201,
        );
    }

    /**
     * Display a teacher's profile with current-session assignments.
     */
    public function show(Teacher $teacher): JsonResponse
    {
        return $this->success(TeacherResource::make($this->teachers->loadProfile($teacher)));
    }

    /**
     * Update a teacher's mutable profile fields and linked login email.
     */
    public function update(UpdateTeacherRequest $request, Teacher $teacher): JsonResponse
    {
        $teacher = $this->teachers->update($teacher, $request->validated());

        return $this->success(TeacherResource::make($teacher), 'Teacher updated');
    }

    /**
     * Flip a teacher's status; inactive disables the login and revokes tokens.
     */
    public function updateStatus(UpdateTeacherStatusRequest $request, Teacher $teacher): JsonResponse
    {
        $teacher = $this->teachers->setStatus($teacher, TeacherStatus::from($request->validated('status')));

        return $this->success(TeacherResource::make($teacher), 'Teacher status updated');
    }

    /**
     * Store/replace the teacher's profile photo.
     */
    public function photo(UploadTeacherPhotoRequest $request, Teacher $teacher): JsonResponse
    {
        $teacher = $this->teachers->setPhoto($teacher, $request->file('photo'));

        return $this->success(TeacherResource::make($teacher), 'Teacher photo updated');
    }

    /**
     * Regenerate the teacher's password, revoke tokens, and queue new credentials.
     */
    public function resendCredentials(Teacher $teacher): JsonResponse
    {
        $this->teachers->resendCredentials($teacher);

        return $this->success(null, 'New credentials are being sent to the teacher.');
    }

    /**
     * Soft-delete a teacher (move to trash).
     */
    public function destroy(Teacher $teacher): JsonResponse
    {
        $this->teachers->delete($teacher);

        return $this->success(null, 'Teacher moved to trash.');
    }

    /**
     * Soft-delete many teachers by public id.
     */
    public function bulkDestroy(BulkTeachersRequest $request): JsonResponse
    {
        $deleted = $this->teachers->bulkDelete($request->validated('ids'));

        return $this->success(['deleted' => $deleted], 'Teachers moved to trash.');
    }

    /**
     * Restore a trashed teacher.
     */
    public function restore(Teacher $teacher): JsonResponse
    {
        $this->teachers->restore($teacher);

        return $this->success(null, 'Teacher restored.');
    }

    /**
     * Restore many trashed teachers by public id.
     */
    public function bulkRestore(BulkTeachersRequest $request): JsonResponse
    {
        $restored = $this->teachers->bulkRestore($request->validated('ids'));

        return $this->success(['restored' => $restored], 'Teachers restored.');
    }

    /**
     * Permanently delete a trashed teacher.
     */
    public function forceDestroy(Teacher $teacher): JsonResponse
    {
        $this->teachers->forceDelete($teacher);

        return $this->success(null, 'Teacher permanently deleted.');
    }

    /**
     * Permanently delete many trashed teachers by public id.
     */
    public function bulkForceDestroy(BulkTeachersRequest $request): JsonResponse
    {
        $deleted = $this->teachers->bulkForceDelete($request->validated('ids'));

        return $this->success(['deleted' => $deleted], 'Teachers permanently deleted.');
    }
}
