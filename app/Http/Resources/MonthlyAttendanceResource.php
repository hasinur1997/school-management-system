<?php

namespace App\Http\Resources;

use App\Models\StudentAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A student's monthly attendance view: the month/year, the SQL-aggregated
 * summary counts, and the ordered list of recorded days with their status.
 *
 * Wraps the array AttendanceService::monthlySheet() returns.
 */
class MonthlyAttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'month' => $this->resource['month'],
            'year' => $this->resource['year'],
            'summary' => $this->resource['summary'],
            'days' => $this->resource['days']->map(fn (StudentAttendance $day): array => [
                'date' => $day->date->toDateString(),
                'status' => $day->status->value,
            ])->all(),
        ];
    }
}
