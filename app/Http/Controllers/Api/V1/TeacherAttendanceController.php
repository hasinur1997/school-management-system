<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\TeacherAttendanceResource;
use App\Models\Teacher;
use App\Services\CheckinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherAttendanceController extends ApiController
{
    public function __construct(private readonly CheckinService $checkin) {}

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
     * The Teacher profile of the authenticated user (branch-scoped).
     */
    private function teacher(Request $request): Teacher
    {
        return Teacher::where('user_id', $request->user()->id)->firstOrFail();
    }
}
