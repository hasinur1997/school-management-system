<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\TeacherAttendance\ListTeacherAttendanceRequest;
use App\Http\Requests\TeacherAttendance\MeTeacherAttendanceRequest;
use App\Http\Requests\TeacherAttendance\UpdateTeacherAttendanceRequest;
use App\Http\Resources\TeacherAttendanceResource;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Services\CheckinService;
use App\Services\TeacherAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherAttendanceController extends ApiController
{
    public function __construct(
        private readonly CheckinService $checkin,
        private readonly TeacherAttendanceService $attendance,
    ) {}

    /**
     * Self check-in from the request IP (validated against the branch whitelist).
     */
    public function checkIn(Request $request): JsonResponse
    {
        $record = $this->checkin->checkIn($this->teacher($request), $request->ip());

        return $this->success(TeacherAttendanceResource::make($record), 'Checked in');
    }

    /**
     * Self check-out on today's record.
     */
    public function checkOut(Request $request): JsonResponse
    {
        $record = $this->checkin->checkOut($this->teacher($request));

        return $this->success(TeacherAttendanceResource::make($record), 'Checked out');
    }

    /**
     * Browse teacher attendance for the branch, filtered and paginated.
     */
    public function index(ListTeacherAttendanceRequest $request): JsonResponse
    {
        $records = $this->attendance->list(
            $request->only(['teacher_id', 'date', 'month', 'year', 'status']),
            $request->integer('per_page', 15),
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => TeacherAttendanceResource::collection($records)->resolve($request),
            'meta' => [
                'current_page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
        ]);
    }

    /**
     * Admin correction of a single record (status / check-in / check-out),
     * stamping corrected_by. The record is branch-scoped by route binding.
     */
    public function update(UpdateTeacherAttendanceRequest $request, TeacherAttendance $teacherAttendance): JsonResponse
    {
        $record = $this->attendance->correct($teacherAttendance, $request->validated(), $request->user());

        return $this->success(TeacherAttendanceResource::make($record));
    }

    /**
     * The authenticated teacher's own monthly history plus a status summary.
     */
    public function me(MeTeacherAttendanceRequest $request): JsonResponse
    {
        $sheet = $this->attendance->monthly(
            $this->teacher($request),
            $request->month(),
            $request->year(),
        );

        return $this->success([
            'summary' => $sheet['summary'],
            'records' => TeacherAttendanceResource::collection($sheet['records'])->resolve($request),
        ]);
    }

    /**
     * The Teacher profile of the authenticated user (branch-scoped).
     */
    private function teacher(Request $request): Teacher
    {
        return Teacher::where('user_id', $request->user()->id)->firstOrFail();
    }
}
