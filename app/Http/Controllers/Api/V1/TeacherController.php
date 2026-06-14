<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TeacherStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Teacher\ListTeachersRequest;
use App\Http\Requests\Teacher\StoreTeacherRequest;
use App\Http\Requests\Teacher\UpdateTeacherRequest;
use App\Http\Requests\Teacher\UpdateTeacherStatusRequest;
use App\Http\Requests\Teacher\UploadTeacherPhotoRequest;
use App\Http\Resources\TeacherResource;
use App\Models\Teacher;
use App\Services\TeacherService;
use Illuminate\Http\JsonResponse;

class TeacherController extends ApiController
{
    public function __construct(private readonly TeacherService $teachers) {}

    /**
     * Display a paginated, filterable listing of teachers.
     */
    public function index(ListTeachersRequest $request): JsonResponse
    {
        $teachers = $this->teachers->list(
            $request->only(['status', 'search', 'sort', 'direction']),
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
     * Update a teacher's mutable profile fields (email is immutable).
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
}
