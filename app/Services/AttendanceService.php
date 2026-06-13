<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\StudentAttendance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceService
{
    /**
     * Build the attendance entry sheet for a section on a given date: the active
     * enrollments of the section in roll order, each carrying that date's
     * existing mark (or null when not yet taken). TC/inactive enrollments are
     * excluded by the active-status filter, so they never appear on the roster.
     *
     * The roster is two queries — enrollments (with student + photo media) and
     * the day's attendance rows keyed by enrollment — so there is no N+1.
     *
     * @return array{date: string, section: Section, enrollments: \Illuminate\Database\Eloquent\Collection<int, Enrollment>, records: Collection<int, StudentAttendance>}
     */
    public function sheet(int $sectionId, Carbon $date): array
    {
        $section = Section::with('schoolClass')->findOrFail($sectionId);

        $enrollments = Enrollment::query()
            ->where('section_id', $sectionId)
            ->where('status', EnrollmentStatus::Active)
            ->with('student.media')
            ->orderBy('roll_no')
            ->get();

        $records = StudentAttendance::query()
            ->whereIn('enrollment_id', $enrollments->modelKeys())
            ->whereDate('date', $date->toDateString())
            ->get()
            ->keyBy('enrollment_id');

        return [
            'date' => $date->toDateString(),
            'section' => $section,
            'enrollments' => $enrollments,
            'records' => $records,
        ];
    }
}
