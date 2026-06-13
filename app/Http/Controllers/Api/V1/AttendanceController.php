<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Attendance\AttendanceSheetRequest;
use App\Http\Requests\Attendance\ListAttendanceRequest;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Http\Requests\Attendance\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\AttendanceSheetResource;
use App\Models\StudentAttendance;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class AttendanceController extends ApiController
{
    public function __construct(private readonly AttendanceService $attendance) {}

    /**
     * Return the attendance entry sheet for a section on a date (default today):
     * the active roster in roll order with any marks already taken that day.
     */
    public function sheet(AttendanceSheetRequest $request): JsonResponse
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->validated('date'))
            : Carbon::today();

        $sheet = $this->attendance->sheet($request->integer('section_id'), $date);

        return $this->success(AttendanceSheetResource::make($sheet));
    }

    /**
     * Display a paginated, filterable listing of attendance records.
     */
    public function index(ListAttendanceRequest $request): JsonResponse
    {
        $records = $this->attendance->list(
            $request->only(['class_id', 'section_id', 'date', 'status']),
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
     * Bulk-save a section's attendance for one date (idempotent upsert).
     */
    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $saved = $this->attendance->saveBulk(
            $request->integer('section_id'),
            $request->validated('date'),
            $request->validated('records'),
            $request->user(),
        );

        return $this->success(['saved' => $saved]);
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
