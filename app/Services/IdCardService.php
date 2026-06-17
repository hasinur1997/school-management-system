<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\IdCardBatchStatus;
use App\Enums\StudentStatus;
use App\Jobs\BuildIdCardBatch;
use App\Models\IdCardBatch;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders student ID cards: a single card on demand (no table, live data) and
 * whole-class batches via a queued, chunked job that produces one merged PDF.
 */
class IdCardService
{
    /** CR80 card, portrait (54mm × 85.6mm) — shared by single and batch render. */
    private const PAPER = [0, 0, 153.01, 242.65];

    /**
     * Build and stream the single ID card PDF for a student.
     *
     * A student with no active enrollment, or one that is no longer active
     * (TC / inactive), cannot be issued a card → 422.
     */
    public function render(Student $student): Response
    {
        if ($student->status !== StudentStatus::Active) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Student has no active enrollment');
        }

        $enrollment = $student->enrollments()
            ->where('status', EnrollmentStatus::Active)
            ->whereHas('session', fn ($query) => $query->where('is_current', true))
            ->with(['session', 'schoolClass:id,name', 'section:id,name'])
            ->first();

        if ($enrollment === null) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Student has no active enrollment');
        }

        $student->loadMissing('branch:id,name,address,phone');

        $pdf = Pdf::loadView('pdf.id-card', [
            'student' => $student,
            'enrollment' => $enrollment,
            'branch' => $student->branch,
            'session' => $enrollment->session,
            'photoPath' => $this->photoPath($student),
        ])->setPaper(self::PAPER);

        return $pdf->stream("idcard-{$student->admission_no}.pdf");
    }

    /**
     * Queue a batch build for a class (optionally a single section) in a
     * session. Rejects an empty cohort up front (422) so callers do not poll a
     * batch that can never produce a card.
     */
    public function queueBatch(int $classId, ?int $sectionId, int $sessionId): IdCardBatch
    {
        // Auth context here, so the branch-scoped student query already isolates
        // the cohort to the caller's branch.
        $branchId = Auth::user()->branch_id;

        if (! $this->eligibleStudents($branchId, $classId, $sectionId, $sessionId)->exists()) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'No eligible students');
        }

        $batch = IdCardBatch::create([
            'class_id' => $classId,
            'section_id' => $sectionId,
            'session_id' => $sessionId,
            'requested_by' => Auth::id(),
        ]);

        BuildIdCardBatch::dispatch($batch);

        return $batch;
    }

    /**
     * Render the merged PDF for a batch and mark it done. Runs inside the queued
     * job (no auth context), so eligibility is scoped by the batch's branch_id
     * explicitly — the global BranchScope is inert without an authenticated user.
     * Students are chunked to bound query memory.
     */
    public function buildBatch(IdCardBatch $batch): void
    {
        $cards = [];

        $this->eligibleStudents($batch->branch_id, $batch->class_id, $batch->section_id, $batch->session_id)
            ->with([
                'branch:id,name,address,phone',
                'enrollments' => fn ($query) => $this->enrollmentFor(
                    $query, $batch->class_id, $batch->section_id, $batch->session_id,
                )->with(['session', 'schoolClass:id,name', 'section:id,name']),
            ])
            ->chunkById(500, function ($students) use (&$cards): void {
                foreach ($students as $student) {
                    $enrollment = $student->enrollments->first();

                    if ($enrollment === null) {
                        continue;
                    }

                    $cards[] = [
                        'student' => $student,
                        'enrollment' => $enrollment,
                        'branch' => $student->branch,
                        'session' => $enrollment->session,
                        'photoPath' => $this->photoPath($student),
                    ];
                }
            });

        $pdf = Pdf::loadView('pdf.id-card-batch', ['cards' => $cards])->setPaper(self::PAPER);

        Storage::disk('local')->put($batch->storagePath(), $pdf->output());

        $batch->update([
            'status' => IdCardBatchStatus::Done,
            'file_path' => $batch->storagePath(),
        ]);
    }

    /**
     * Stream a finished batch's merged PDF. 409 while the batch is still
     * processing/failed or its file is missing.
     */
    public function downloadBatch(IdCardBatch $batch): Response
    {
        if (
            $batch->status !== IdCardBatchStatus::Done
            || $batch->file_path === null
            || ! Storage::disk('local')->exists($batch->file_path)
        ) {
            abort(Response::HTTP_CONFLICT, 'ID card batch is not ready');
        }

        return Storage::disk('local')->download(
            $batch->file_path,
            "idcards-batch-{$batch->id}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Eligible cohort: active students of the branch with an active enrollment
     * in the target class/session (and section when given). TC and inactive
     * students are excluded by the active-status filters.
     */
    private function eligibleStudents(int $branchId, int $classId, ?int $sectionId, int $sessionId): Builder
    {
        return Student::query()
            ->where('students.branch_id', $branchId)
            ->where('students.status', StudentStatus::Active)
            ->whereHas('enrollments', fn ($query) => $this->enrollmentFor(
                $query, $classId, $sectionId, $sessionId,
            ));
    }

    /**
     * Constrain an enrollment query to the active enrollment for the target
     * class/session (and section when given) — shared by the cohort filter and
     * the eager load so both select the same row.
     */
    private function enrollmentFor(Builder|HasMany $query, int $classId, ?int $sectionId, int $sessionId): Builder|HasMany
    {
        return $query
            ->where('session_id', $sessionId)
            ->where('class_id', $classId)
            ->where('status', EnrollmentStatus::Active)
            ->when($sectionId !== null, fn ($q) => $q->where('section_id', $sectionId));
    }

    /**
     * The student photo's local disk path for dompdf (it reads disk, not HTTP),
     * or null when none is set so the template renders a placeholder.
     */
    private function photoPath(Student $student): ?string
    {
        $path = $student->getFirstMedia('photo')?->getPath();

        return $path !== null && is_file($path) ? $path : null;
    }
}
