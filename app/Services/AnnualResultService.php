<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\AnnualResult;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Owns the annual result engine: the 25/25/50 weighted GPA, repeatable
 * generation into annual_results, and the publication freeze. Requires all
 * three exams of a (session, class) to be published; generation is idempotent
 * until publication, after which the annual results are frozen (409).
 */
class AnnualResultService
{
    public function __construct(private readonly GradeResolver $grades) {}

    /**
     * (Re)generate the annual result for every active enrollment of the given
     * (session, class) that has all three published per-exam results.
     *
     * Annual GPA = 0.25·S1 + 0.25·S2 + 0.50·Final (2 dp, half-up); the overall
     * grade is the band the annual GPA maps to. is_passed requires the final
     * exam to have passed and the annual grade to not be a failing grade.
     * Enrollments missing any of the three exam results are skipped and
     * reported, never written.
     *
     * @return array{generated: int, skipped: list<array{enrollment_id: int, reason: string}>}
     */
    public function generate(int $sessionId, int $classId): array
    {
        $exams = $this->exams($sessionId, $classId);

        $allPublished = collect(ExamType::cases())->every(
            fn (ExamType $type): bool => isset($exams[$type->value])
                && $exams[$type->value]->status === ExamStatus::Published,
        );

        abort_unless($allPublished, 409, 'All three exams must be published first');

        $enrollmentIds = $this->enrollmentIds($sessionId, $classId);

        abort_if($this->hasPublished($enrollmentIds), 409, 'Annual results are already published');

        // Published per-exam results keyed by [enrollment_id][exam type].
        $resultsByEnrollment = ExamResult::query()
            ->whereIn('exam_id', collect($exams)->pluck('id'))
            ->whereIn('enrollment_id', $enrollmentIds)
            ->get()
            ->groupBy('enrollment_id');

        $examTypeById = collect($exams)->mapWithKeys(
            fn (Exam $exam, string $type): array => [$exam->id => $type],
        );

        $failGrades = $this->grades->all()
            ->where('is_fail', true)
            ->pluck('grade')
            ->all();

        $rows = [];
        $skipped = [];
        $now = now();

        foreach ($enrollmentIds as $enrollmentId) {
            /** @var Collection<int, ExamResult> $results */
            $results = ($resultsByEnrollment->get($enrollmentId) ?? collect())
                ->keyBy(fn (ExamResult $result): string => $examTypeById[$result->exam_id]);

            $missing = $this->firstMissing($results);

            if ($missing !== null) {
                $skipped[] = [
                    'enrollment_id' => $enrollmentId,
                    'reason' => "missing {$missing} result",
                ];

                continue;
            }

            $rows[] = $this->computeRow($enrollmentId, $results, $failGrades, $now);
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            AnnualResult::upsert(
                $chunk,
                ['enrollment_id'],
                ['first_semester_gpa', 'second_semester_gpa', 'final_exam_gpa', 'annual_gpa', 'grade', 'is_passed', 'updated_at'],
            );
        }

        return [
            'generated' => count($rows),
            'skipped' => $skipped,
        ];
    }

    /**
     * Freeze the (session, class) annual results: stamp published_at on every
     * row in one transaction. Re-publishing → 409.
     *
     * @return array{published: int}
     */
    public function publish(int $sessionId, int $classId): array
    {
        // Ensure the tuple is valid (in-branch class with its three exams).
        $this->exams($sessionId, $classId);

        $enrollmentIds = $this->enrollmentIds($sessionId, $classId);

        abort_if($this->hasPublished($enrollmentIds), 409, 'Annual results are already published');

        return DB::transaction(function () use ($enrollmentIds): array {
            $published = AnnualResult::query()
                ->whereIn('enrollment_id', $enrollmentIds)
                ->update(['published_at' => now()]);

            return ['published' => $published];
        });
    }

    /**
     * The three exams for the (session, class) tuple, keyed by type. Always
     * resolved through the branch-scoped Exam model so out-of-branch tuples
     * yield an empty set (and thus the published guard fails).
     *
     * @return array<string, Exam>
     */
    private function exams(int $sessionId, int $classId): array
    {
        return Exam::query()
            ->where('session_id', $sessionId)
            ->where('class_id', $classId)
            ->get()
            ->keyBy(fn (Exam $exam): string => $exam->type->value)
            ->all();
    }

    /**
     * The active enrollment ids of the (session, class) — TC/promoted/failed
     * enrollments are closed, so the active filter excludes them.
     *
     * @return list<int>
     */
    private function enrollmentIds(int $sessionId, int $classId): array
    {
        return Enrollment::query()
            ->where('session_id', $sessionId)
            ->where('class_id', $classId)
            ->where('status', EnrollmentStatus::Active)
            ->pluck('id')
            ->all();
    }

    /**
     * Whether any of the given enrollments already has a published annual
     * result (drives the idempotency-until-publish guard).
     *
     * @param  list<int>  $enrollmentIds
     */
    private function hasPublished(array $enrollmentIds): bool
    {
        return AnnualResult::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->whereNotNull('published_at')
            ->exists();
    }

    /**
     * The first exam type (in S1 → S2 → Final order) whose result is missing,
     * or null when all three are present.
     *
     * @param  Collection<string, ExamResult>  $results
     */
    private function firstMissing(Collection $results): ?string
    {
        foreach (ExamType::cases() as $type) {
            if (! $results->has($type->value)) {
                return $type->value;
            }
        }

        return null;
    }

    /**
     * Build one annual_results upsert row from the three published per-exam
     * results.
     *
     * @param  Collection<string, ExamResult>  $results
     * @param  list<string>  $failGrades
     * @return array<string, mixed>
     */
    private function computeRow(int $enrollmentId, Collection $results, array $failGrades, \DateTimeInterface $now): array
    {
        $first = $results->get(ExamType::FirstSemester->value);
        $second = $results->get(ExamType::SecondSemester->value);
        $final = $results->get(ExamType::Final->value);

        $annualGpa = $this->weightedGpa(
            (float) $first->gpa,
            (float) $second->gpa,
            (float) $final->gpa,
        );

        $grade = $this->gradeForGpa($annualGpa);

        // is_passed requires the final exam passed and the annual grade not a
        // failing grade — a failed final fails the year regardless of the
        // weighted number.
        $isPassed = (bool) $final->is_passed && ! in_array($grade, $failGrades, true);

        return [
            'public_id' => AnnualResult::newPublicId(),
            'enrollment_id' => $enrollmentId,
            'first_semester_gpa' => $first->gpa,
            'second_semester_gpa' => $second->gpa,
            'final_exam_gpa' => $final->gpa,
            'annual_gpa' => $annualGpa,
            'grade' => $grade,
            'is_passed' => $isPassed,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Annual GPA = 0.25·S1 + 0.25·S2 + 0.50·Final, to 2 dp, rounded half-up.
     *
     * Computed in integer hundredths so the rounding edges are exact: the three
     * GPAs are 2-dp values, so the weighted figure is (S1 + S2 + 2·Final) / 4
     * in hundredths, rounded half-up to the nearest hundredth. (Accumulating
     * the weights as floats loses edges such as 3.555, which must round to
     * 3.56.)
     */
    private function weightedGpa(float $first, float $second, float $final): float
    {
        $numerator = (int) round($first * 100)
            + (int) round($second * 100)
            + 2 * (int) round($final * 100);

        // Half-up division by 4 for a non-negative numerator.
        $hundredths = intdiv($numerator + 2, 4);

        return $hundredths / 100;
    }

    /**
     * Map a GPA to its grade letter via the scale's grade points: the highest
     * band whose grade point the GPA reaches.
     */
    private function gradeForGpa(float $gpa): string
    {
        $band = $this->grades->all()
            ->sortByDesc('grade_point')
            ->first(fn ($band): bool => $gpa >= (float) $band->grade_point);

        return $band?->grade ?? 'F';
    }
}
