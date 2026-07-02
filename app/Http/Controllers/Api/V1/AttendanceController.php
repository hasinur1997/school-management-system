<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Attendance\AttendanceSheetRequest;
use App\Http\Requests\Attendance\ListAttendanceRequest;
use App\Http\Requests\Attendance\MonthlyAttendanceRequest;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Http\Requests\Attendance\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\AttendanceSheetResource;
use App\Http\Resources\MonthlyAttendanceResource;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class AttendanceController extends ApiController
{
    public function __construct(private readonly AttendanceService $attendance) {}

    /**
     * Return the attendance entry sheet for a class on a date (default today):
     * the active roster in roll order with any marks already taken that day.
     * The section is optional — omitted, the roster spans the whole class.
     */
    public function sheet(AttendanceSheetRequest $request): JsonResponse
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->validated('date'))
            : Carbon::today();

        $sheet = $this->attendance->sheet(
            $request->integer('class_id'),
            $request->filled('section_id') ? $request->integer('section_id') : null,
            $date,
        );

        return $this->success(AttendanceSheetResource::make($sheet));
    }

    /**
     * Display a paginated, filterable listing of attendance records.
     */
    public function index(ListAttendanceRequest $request): JsonResponse
    {
        $records = $this->attendance->list(
            $request->withBranchFilter($request->only(['class_id', 'section_id', 'date', 'status'])),
            $request->integer('per_page', 15),
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => AttendanceResource::collection($records)->resolve($request),
            'meta' => [
                'current_page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
        ]);
    }

    /**
     * Bulk-save attendance for one date (idempotent upsert), scoped to a
     * section — or a whole class when no section is given.
     */
    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $saved = $this->attendance->saveBulk(
            $request->filled('section_id') ? $request->integer('section_id') : null,
            $request->filled('class_id') ? $request->integer('class_id') : null,
            $request->validated('date'),
            $request->validated('records'),
            $request->user(),
        );

        return $this->success(['saved' => $saved]);
    }

    /**
     * Return a student's monthly attendance sheet (summary + day list).
     *
     * Authorized by StudentPolicy::viewAttendance — staff with attendance.view,
     * the student itself, or a linked parent. A denial hides existence (404)
     * rather than leaking it via 403, like the student profile reads.
     */
    public function studentMonthly(MonthlyAttendanceRequest $request, Student $student): JsonResponse
    {
        if ($request->user()->cannot('viewAttendance', $student)) {
            abort(404);
        }

        return $this->monthly($student, $request->month(), $request->year());
    }

    /**
     * Return the authenticated student's own monthly attendance sheet. The
     * endpoint is intrinsically the student role: a non-student (no student
     * profile) gets 403.
     */
    public function meMonthly(MonthlyAttendanceRequest $request): JsonResponse
    {
        $student = Student::where('user_id', $request->user()->id)->first();

        if ($student === null) {
            abort(403);
        }

        return $this->monthly($student, $request->month(), $request->year());
    }

    /**
     * Shared monthly-sheet response builder for the staff/self/parent and the
     * student-self endpoints.
     */
    private function monthly(Student $student, int $month, int $year): JsonResponse
    {
        $sheet = $this->attendance->monthlySheet($student, $month, $year);

        return $this->success(MonthlyAttendanceResource::make($sheet));
    }

    /**
     * Correct a single attendance record's status.
     */
    public function update(UpdateAttendanceRequest $request, StudentAttendance $attendance): JsonResponse
    {
        $record = $this->attendance->update($attendance, $request->validated('status'));

        return $this->success(AttendanceResource::make($record));
    }
}
