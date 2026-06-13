<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StudentStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Student\ListStudentsRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Requests\Student\UpdateStudentStatusRequest;
use App\Http\Requests\Student\UploadStudentPhotoRequest;
use App\Http\Resources\StudentListResource;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Services\StudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends ApiController
{
    public function __construct(private readonly StudentService $students) {}

    /**
     * Display a paginated, filterable listing of students (compact rows).
     */
    public function index(ListStudentsRequest $request): JsonResponse
    {
        $students = $this->students->list(
            $request->only(['class_id', 'section_id', 'session_id', 'status', 'search']),
            $request->integer('per_page', 15),
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => StudentListResource::collection($students)->resolve($request),
            'meta' => [
                'current_page' => $students->currentPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
                'last_page' => $students->lastPage(),
            ],
        ]);
    }

    /**
     * Display a student's full bilingual profile with enrollment history.
     *
     * Authorization is the StudentPolicy::view rule. A denial here hides the
     * record's existence (404) rather than leaking it via 403 — personal data.
     */
    public function show(Request $request, Student $student): JsonResponse
    {
        if ($request->user()->cannot('view', $student)) {
            abort(404);
        }

        return $this->success(StudentResource::make($this->students->loadProfile($student)));
    }

    /**
     * Update a student's mutable profile fields (identity columns are immutable).
     */
    public function update(UpdateStudentRequest $request, Student $student): JsonResponse
    {
        $student = $this->students->update($student, $request->validated());

        return $this->success(StudentResource::make($student), 'Student updated');
    }

    /**
     * Flip a student's status between active and inactive (tc via TC module).
     */
    public function updateStatus(UpdateStudentStatusRequest $request, Student $student): JsonResponse
    {
        $student = $this->students->setStatus($student, StudentStatus::from($request->validated('status')));

        return $this->success(StudentResource::make($student), 'Student status updated');
    }

    /**
     * Store/replace the student's profile photo.
     */
    public function photo(UploadStudentPhotoRequest $request, Student $student): JsonResponse
    {
        $student = $this->students->setPhoto($student, $request->file('photo'));

        return $this->success(StudentResource::make($student), 'Student photo updated');
    }
}
