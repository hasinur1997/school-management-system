<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Models\Enrollment;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders a student's ID card on demand from live data — there is no id_cards
 * table. The card carries the photo, name, admission no, current
 * class/section/roll, session, branch and validity (session end date).
 */
class IdCardService
{
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

        $photoPath = $student->getFirstMedia('photo')?->getPath();
        $photoPath = $photoPath !== null && is_file($photoPath) ? $photoPath : null;

        $pdf = Pdf::loadView('pdf.id-card', [
            'student' => $student,
            'enrollment' => $enrollment,
            'branch' => $student->branch,
            'session' => $enrollment->session,
            'photoPath' => $photoPath,
        ])->setPaper([0, 0, 153.01, 242.65]); // CR80 card, portrait (54mm × 85.6mm)

        return $pdf->stream("idcard-{$student->admission_no}.pdf");
    }
}
