<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Attendance\AttendanceSheetRequest;
use App\Http\Resources\AttendanceSheetResource;
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
}
