<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StudentStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Student\BulkStudentsRequest;
use App\Http\Requests\Student\ListStudentsRequest;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateEnrollmentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Requests\Student\UpdateStudentStatusRequest;
use App\Http\Requests\Student\UploadStudentPhotoRequest;
use App\Http\Resources\EnrollmentResource;
use App\Http\Resources\StudentListResource;
use App\Http\Resources\StudentResource;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\StudentService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

        return $this->paginated($students, $request);
    }

    /**
     * Display a paginated listing of soft-deleted students.
     */
    public function trash(ListStudentsRequest $request): JsonResponse
    {
        $students = $this->students->listTrashed(
            $request->only(['class_id', 'section_id', 'session_id', 'status', 'search']),
            $request->integer('per_page', 15),
        );

        return $this->paginated($students, $request);
    }

    /**
     * Build the standard paginated list envelope for student collections.
     */
    private function paginated(LengthAwarePaginator $students, Request $request): JsonResponse
    {
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
     * Create a student directly (login + profile + active enrollment + optional
     * linked parent), bypassing the admission application flow.
     */
    public function store(StoreStudentRequest $request): JsonResponse
    {
        $student = $this->students->create($request->validated(), $request->targetBranchId());

        return $this->success(
            StudentResource::make($student),
            'Student created. Credentials are being sent.',
            201,
        );
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
     * List a student's class history (enrollments), newest first.
     *
     * Authorized by the same StudentPolicy::view rule as the profile, with the
     * same 404-not-403 hiding so a student/parent can never probe others.
     */
    public function enrollments(Request $request, Student $student): JsonResponse
    {
        if ($request->user()->cannot('view', $student)) {
            abort(404);
        }

        return $this->success(
            EnrollmentResource::collection($this->students->enrollmentHistory($student))
        );
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
     * Update a single enrollment row in a student's class history.
     *
     * The {student} binding is branch-scoped (out-of-branch → 404). {enrollment}
     * is not branch-scoped (it derives its branch through the student), so we
     * confirm it belongs to this student and 404 otherwise — never editing or
     * leaking another student's enrollment.
     */
    public function updateEnrollment(
        UpdateEnrollmentRequest $request,
        Student $student,
        Enrollment $enrollment
    ): JsonResponse {
        if ($enrollment->student_id !== $student->id) {
            abort(404);
        }

        $enrollment = $this->students->updateEnrollment($enrollment, $request->validated());

        return $this->success(EnrollmentResource::make($enrollment), 'Enrollment updated');
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

    /**
     * Regenerate the student's password, revoke tokens, and queue new credentials.
     */
    public function resendCredentials(Student $student): JsonResponse
    {
        $this->students->resendCredentials($student);

        return $this->success(null, 'New credentials are being sent to the student.');
    }

    /**
     * Soft delete a student — moves it to trash and disables the login.
     */
    public function destroy(Student $student): JsonResponse
    {
        $this->students->delete($student);

        return $this->success(null, 'Student moved to trash.');
    }

    /**
     * Soft delete several students by public id.
     */
    public function bulkDestroy(BulkStudentsRequest $request): JsonResponse
    {
        $deleted = $this->students->bulkDelete($request->validated('ids'));

        return $this->success(['deleted' => $deleted], 'Students moved to trash.');
    }

    /**
     * Restore a trashed student.
     */
    public function restore(Student $student): JsonResponse
    {
        $this->students->restore($student);

        return $this->success(null, 'Student restored.');
    }

    /**
     * Restore several trashed students by public id.
     */
    public function bulkRestore(BulkStudentsRequest $request): JsonResponse
    {
        $restored = $this->students->bulkRestore($request->validated('ids'));

        return $this->success(['restored' => $restored], 'Students restored.');
    }

    /**
     * Permanently delete a trashed student.
     */
    public function forceDestroy(Student $student): JsonResponse
    {
        $this->students->forceDelete($student);

        return $this->success(null, 'Student permanently deleted.');
    }

    /**
     * Permanently delete several trashed students by public id.
     */
    public function bulkForceDestroy(BulkStudentsRequest $request): JsonResponse
    {
        $deleted = $this->students->bulkForceDelete($request->validated('ids'));

        return $this->success(['deleted' => $deleted], 'Students permanently deleted.');
    }
}
