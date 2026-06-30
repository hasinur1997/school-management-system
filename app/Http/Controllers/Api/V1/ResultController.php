<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Result\ListExamResultsRequest;
use App\Http\Requests\Result\MeResultsRequest;
use App\Http\Requests\Result\SearchResultsRequest;
use App\Http\Resources\ExamResultResource;
use App\Http\Resources\ResultBundleResource;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Services\ResultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResultController extends ApiController
{
    public function __construct(private readonly ResultService $results) {}

    /**
     * (Re)generate the exam's results for every enrollment with a complete set
     * of marks, reporting any skipped enrollments and their missing subjects.
     */
    public function generate(Exam $exam): JsonResponse
    {
        $result = $this->results->generateExamResults($exam);

        return $this->success($result, 'Results generated');
    }

    /**
     * Freeze the exam's results: stamp published_at and move the exam to
     * published status.
     */
    public function publish(Exam $exam): JsonResponse
    {
        $result = $this->results->publishExamResults($exam);

        return $this->success($result, 'Results published');
    }

    /**
     * Display a paginated, filterable listing of an exam's results, ordered by
     * GPA descending.
     */
    public function index(ListExamResultsRequest $request, Exam $exam): JsonResponse
    {
        $filters = $request->only(['section_id']);

        if ($request->has('is_passed')) {
            $filters['is_passed'] = $request->boolean('is_passed');
        }

        $results = $this->results->listExamResults(
            $exam,
            $filters,
            $request->integer('per_page', 15),
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => ExamResultResource::collection($results)->resolve($request),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'last_page' => $results->lastPage(),
            ],
        ]);
    }

    /**
     * Search any student's full result bundle by admission_no or by class
     * coordinates (session/class/section/roll). Staff-only (result.view), so the
     * bundle includes unpublished results flagged as such.
     */
    public function search(SearchResultsRequest $request): JsonResponse
    {
        $enrollment = $this->results->searchEnrollment($request->criteria());

        return $this->bundleResponse($enrollment, publishedOnly: false);
    }

    /**
     * Return the full result bundle for one enrollment. Authorized by
     * StudentPolicy::viewResults — staff (result.view), the student itself, or a
     * linked parent; a denial hides existence (404). Staff see unpublished
     * results flagged; students/parents see published results only.
     */
    public function enrollmentResults(Request $request, string $enrollment): JsonResponse
    {
        $enrollment = $this->results->resolveEnrollment($enrollment);
        $user = $request->user();

        if ($user->cannot('viewResults', $enrollment->student)) {
            abort(404);
        }

        return $this->bundleResponse($enrollment, publishedOnly: ! $user->can('result.view'));
    }

    /**
     * Return the caller's own results (student) or a linked child's results
     * (parent, via student_id) for a session. Students always get their own —
     * any student_id is ignored; a parent must pass a linked student_id (an
     * unlinked or missing one → 404). Published results only.
     */
    public function meResults(MeResultsRequest $request): JsonResponse
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->first();

        if ($student === null) {
            $parent = ParentProfile::where('user_id', $user->id)->first();
            abort_if($parent === null, 403);

            $studentId = $request->integer('student_id');
            abort_unless($studentId !== 0 && $parent->isLinkedTo($studentId), 404);

            $student = Student::findOrFail($studentId);
        }

        $enrollment = $this->results->enrollmentForStudent(
            $student,
            $request->filled('session_id') ? $request->integer('session_id') : null,
        );

        return $this->bundleResponse($enrollment, publishedOnly: true);
    }

    /**
     * Shared bundle response builder for the search/enrollment/me reads.
     */
    private function bundleResponse(Enrollment $enrollment, bool $publishedOnly): JsonResponse
    {
        $bundle = $this->results->bundle($enrollment, $publishedOnly);

        return $this->success(ResultBundleResource::make($bundle));
    }
}
