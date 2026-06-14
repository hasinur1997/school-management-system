<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\PromotionType;
use App\Models\AnnualResult;
use App\Models\Enrollment;
use App\Models\Promotion;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Owns the promotion engine: the read-only preview (9.1) and the bulk execute
 * (9.2). Preview reports who moves up (passed, published annual result), who
 * doesn't (failed / no result / TC) and the resolved next class. Bulk closes the
 * old enrollments and opens new ones for the target session in one transaction.
 */
class PromotionService
{
    /** Bulk operations are chunked at this size per the performance rules. */
    private const CHUNK = 500;

    /**
     * Execute the "Promote button" for a whole class in one transaction:
     * passed students get a new enrollment in the next class/section, failed
     * students are re-enrolled in the **same** class for the new session, and
     * every move is logged. All writes are bulk and chunked.
     *
     * Runtime guards (409): the class's annual results must be published, and
     * the cohort must not already be promoted (any of its students already
     * holding a target-session enrollment). The structural 422 checks (same
     * session, section/class pairing, in-branch class) are enforced upstream by
     * BulkPromotionRequest.
     *
     * @param  array{from_session_id: int, from_class_id: int, to_session_id: int, to_section_id: int, roll_strategy: string}  $data
     * @return array{promoted: int, held: int}
     */
    public function bulk(array $data, User $actor): array
    {
        return DB::transaction(function () use ($data, $actor): array {
            $fromSessionId = $data['from_session_id'];
            $fromClassId = $data['from_class_id'];
            $toSessionId = $data['to_session_id'];
            $toSectionId = $data['to_section_id'];
            $byMerit = $data['roll_strategy'] === 'by_merit';

            // 409 re-run guard first: if any student who sat in the class this
            // session already holds a target-session enrollment, the class was
            // already promoted. Checked against every status (a successful run
            // closes the active rows, so an active-only check would miss it and
            // fall through to the "publish first" guard below).
            $cohortStudentIds = Enrollment::query()
                ->where('session_id', $fromSessionId)
                ->where('class_id', $fromClassId)
                ->pluck('student_id');

            $alreadyPromoted = Enrollment::query()
                ->whereIn('student_id', $cohortStudentIds)
                ->where('session_id', $toSessionId)
                ->exists();
            abort_if($alreadyPromoted, 409, 'This class has already been promoted for the target session');

            // The cohort to process: this session's active enrollments of the
            // class. TC / already-closed rows are excluded for free.
            $enrollments = Enrollment::query()
                ->where('session_id', $fromSessionId)
                ->where('class_id', $fromClassId)
                ->where('status', EnrollmentStatus::Active->value)
                ->get();

            $results = AnnualResult::query()
                ->whereIn('enrollment_id', $enrollments->pluck('id'))
                ->whereNotNull('published_at')
                ->get()
                ->keyBy('enrollment_id');

            // 409: promotion is meaningless until annual results are published.
            abort_if($results->isEmpty(), 409, 'Publish annual results first');

            // The promoted cohort lands in the next class; the section's class
            // is its class (the pairing was validated in the Form Request).
            $toClassId = Section::query()->whereKey($toSectionId)->value('class_id');

            // Split into passed (promote) and failed (hold). Active enrollments
            // lacking a published result are left untouched.
            $passed = [];
            $failed = [];

            foreach ($enrollments as $enrollment) {
                $result = $results->get($enrollment->id);

                if ($result === null) {
                    continue;
                }

                if ($result->is_passed) {
                    $passed[] = ['enrollment' => $enrollment, 'gpa' => (float) $result->annual_gpa];
                } else {
                    $failed[] = $enrollment;
                }
            }

            // by_merit: rank passers by annual GPA desc and reassign rolls 1..n
            // in the target section; keep: carry the old roll across.
            if ($byMerit) {
                usort($passed, fn (array $a, array $b): int => $b['gpa'] <=> $a['gpa']);
            }

            $now = now();

            $promotedRows = [];
            $rank = 1;

            foreach ($passed as $entry) {
                $enrollment = $entry['enrollment'];

                $promotedRows[] = [
                    'student_id' => $enrollment->student_id,
                    'session_id' => $toSessionId,
                    'class_id' => $toClassId,
                    'section_id' => $toSectionId,
                    'roll_no' => $byMerit ? $rank++ : $enrollment->roll_no,
                    'status' => EnrollmentStatus::Active->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $heldRows = [];

            foreach ($failed as $enrollment) {
                // Re-enrolled in the same class for the new session, keeping
                // their section and roll.
                $heldRows[] = [
                    'student_id' => $enrollment->student_id,
                    'session_id' => $toSessionId,
                    'class_id' => $fromClassId,
                    'section_id' => $enrollment->section_id,
                    'roll_no' => $enrollment->roll_no,
                    'status' => EnrollmentStatus::Active->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Close old enrollments (bulk, chunked): promoted vs failed.
            $passedIds = array_map(fn (array $entry): int => $entry['enrollment']->id, $passed);
            $failedIds = array_map(fn (Enrollment $enrollment): int => $enrollment->id, $failed);

            foreach (array_chunk($passedIds, self::CHUNK) as $chunk) {
                Enrollment::query()->whereIn('id', $chunk)->update(['status' => EnrollmentStatus::Promoted->value]);
            }

            foreach (array_chunk($failedIds, self::CHUNK) as $chunk) {
                Enrollment::query()->whereIn('id', $chunk)->update(['status' => EnrollmentStatus::Failed->value]);
            }

            // Insert the new enrollments (bulk, chunked).
            foreach (array_chunk($promotedRows, self::CHUNK) as $chunk) {
                Enrollment::query()->insert($chunk);
            }

            foreach (array_chunk($heldRows, self::CHUNK) as $chunk) {
                Enrollment::query()->insert($chunk);
            }

            // Log every move (type bulk). Promoted rows link to the new
            // enrollment; held rows carry a null to_enrollment_id (the student
            // did not advance). Re-fetch the promoted students' new enrollments
            // by their unique (student, session) to resolve to_enrollment_id.
            $newEnrollmentIds = Enrollment::query()
                ->where('session_id', $toSessionId)
                ->where('class_id', $toClassId)
                ->where('section_id', $toSectionId)
                ->whereIn('student_id', array_column($promotedRows, 'student_id'))
                ->pluck('id', 'student_id');

            $logs = [];

            foreach ($passed as $entry) {
                $enrollment = $entry['enrollment'];

                $logs[] = [
                    'student_id' => $enrollment->student_id,
                    'from_enrollment_id' => $enrollment->id,
                    'to_enrollment_id' => $newEnrollmentIds->get($enrollment->student_id),
                    'type' => PromotionType::Bulk->value,
                    'promoted_by' => $actor->id,
                    'promoted_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach ($failed as $enrollment) {
                $logs[] = [
                    'student_id' => $enrollment->student_id,
                    'from_enrollment_id' => $enrollment->id,
                    'to_enrollment_id' => null,
                    'type' => PromotionType::Bulk->value,
                    'promoted_by' => $actor->id,
                    'promoted_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($logs, self::CHUNK) as $chunk) {
                Promotion::query()->insert($chunk);
            }

            return ['promoted' => count($passed), 'held' => count($failed)];
        });
    }

    /**
     * Build the promotion preview for a (session, class): the cohort split into
     * eligible (published annual result, passed) and not_eligible (with reason
     * failed | no_result | tc), plus the resolved next class.
     *
     * Requires the class's annual results to be published — otherwise 409.
     *
     * @return array{
     *     to_class: array{id: int, name: string}|null,
     *     eligible: list<array{student_id: int, name_en: string, roll_no: int, annual_gpa: string}>,
     *     not_eligible: list<array{student_id: int, name_en: string, reason: string}>,
     * }
     */
    public function preview(int $sessionId, int $classId): array
    {
        // Branch-scoped: the request already validated the class is in-branch.
        $class = SchoolClass::findOrFail($classId);

        // The cohort: this session's active (movers) and TC enrollments. Closed
        // promoted/failed rows are history and never appear in a fresh preview.
        $enrollments = Enrollment::query()
            ->where('session_id', $sessionId)
            ->where('class_id', $classId)
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Tc->value])
            ->with('student:id,name_en')
            ->orderBy('roll_no')
            ->get();

        $publishedResults = AnnualResult::query()
            ->whereIn('enrollment_id', $enrollments->pluck('id'))
            ->whereNotNull('published_at')
            ->get()
            ->keyBy('enrollment_id');

        // Guard: a preview is only meaningful once the annual results are
        // published for the class.
        abort_if($publishedResults->isEmpty(), 409, 'Publish annual results first');

        $eligible = [];
        $notEligible = [];

        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;
            $result = $publishedResults->get($enrollment->id);

            // TC students are excluded from promotion outright.
            if ($enrollment->status === EnrollmentStatus::Tc) {
                $notEligible[] = $this->notEligibleRow($student, 'tc');

                continue;
            }

            // No published annual result for this enrollment.
            if ($result === null) {
                $notEligible[] = $this->notEligibleRow($student, 'no_result');

                continue;
            }

            // Has a result but did not pass the year.
            if (! $result->is_passed) {
                $notEligible[] = $this->notEligibleRow($student, 'failed');

                continue;
            }

            $eligible[] = [
                'student_id' => $student->id,
                'name_en' => $student->name_en,
                'roll_no' => $enrollment->roll_no,
                'annual_gpa' => $result->annual_gpa,
            ];
        }

        return [
            'to_class' => $this->nextClass($class),
            'eligible' => $eligible,
            'not_eligible' => $notEligible,
        ];
    }

    /**
     * Resolve the target class by numeric_level + 1 within the branch. The top
     * class (no next level) returns null — those students have nowhere to be
     * promoted to.
     *
     * @return array{id: int, name: string}|null
     */
    private function nextClass(SchoolClass $class): ?array
    {
        // Branch-scoped: only same-branch classes are visible.
        $next = SchoolClass::query()
            ->where('numeric_level', $class->numeric_level + 1)
            ->first();

        return $next === null
            ? null
            : ['id' => $next->id, 'name' => $next->name];
    }

    /**
     * Shape one not_eligible entry.
     *
     * @return array{student_id: int, name_en: string, reason: string}
     */
    private function notEligibleRow(Student $student, string $reason): array
    {
        return [
            'student_id' => $student->id,
            'name_en' => $student->name_en,
            'reason' => $reason,
        ];
    }
}
