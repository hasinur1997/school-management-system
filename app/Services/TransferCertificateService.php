<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\TransferCertificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Owns the transfer certificate lifecycle (12.3): issuing a TC retires a
 * student from active operations in one transaction (student + enrollment
 * statuses flip to tc) and persists the rendered PDF as the project's one
 * stored legal document. Exclusion from attendance/invoicing/promotion is
 * enforced by those modules' own status scopes — not here.
 */
class TransferCertificateService
{
    public function __construct(private readonly TcNoGenerator $tcNumbers) {}

    /**
     * Issue a transfer certificate for a student. Atomic: a TC row is created,
     * the student and any active enrollment flip to tc, and the rendered PDF is
     * persisted via medialibrary — all or nothing. A student who already holds a
     * TC is rejected 409.
     *
     * @param  array{reason: string, issue_date: string}  $data
     */
    public function issue(Student $student, array $data): TransferCertificate
    {
        if ($student->transferCertificate()->exists()) {
            abort(Response::HTTP_CONFLICT, 'Transfer certificate already issued');
        }

        return DB::transaction(function () use ($student, $data): TransferCertificate {
            $tc = TransferCertificate::create([
                'student_id' => $student->id,
                'tc_no' => $this->tcNumbers->generate($student->branch_id),
                'reason' => $data['reason'],
                'issue_date' => $data['issue_date'],
                'issued_by' => Auth::id(),
            ]);

            // Retire the student and close any active enrollment. The status
            // scopes in attendance/invoicing/promotion key off these values, so
            // flipping them is what enforces exclusion across modules.
            $student->update(['status' => StudentStatus::Tc]);
            $student->enrollments()
                ->where('status', EnrollmentStatus::Active->value)
                ->update(['status' => EnrollmentStatus::Tc->value]);

            $this->attachPdf($tc, $student);

            return $tc;
        });
    }

    /**
     * Browse issued TCs in the caller's branch (scope automatic). Filters: an
     * issue_date window (from/to) and a search across tc_no plus the student's
     * name/admission_no. Newest first.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return TransferCertificate::query()
            ->with(['student.currentEnrollment.schoolClass:id,public_id,name', 'student.currentEnrollment.section:id,public_id,name'])
            ->when(isset($filters['from']), fn (Builder $query) => $query->whereDate('issue_date', '>=', $filters['from']))
            ->when(isset($filters['to']), fn (Builder $query) => $query->whereDate('issue_date', '<=', $filters['to']))
            ->when(isset($filters['search']), function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('tc_no', 'like', $term)
                        ->orWhereHas('student', fn (Builder $student) => $student
                            ->where('name_en', 'like', $term)
                            ->orWhere('name_bn', 'like', $term)
                            ->orWhere('admission_no', 'like', $term));
                });
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Stream the stored TC PDF (download). The file is the legal record; if the
     * underlying media is missing the failure is logged and surfaced as 500 —
     * an issued TC should always have its document.
     */
    public function downloadPdf(TransferCertificate $tc): Response
    {
        $media = $tc->getFirstMedia('certificate');

        if ($media === null || ! is_file($media->getPath())) {
            Log::error('Transfer certificate PDF missing', ['tc_id' => $tc->id, 'tc_no' => $tc->tc_no]);

            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Transfer certificate document is unavailable');
        }

        return response()->download($media->getPath(), "tc-{$tc->tc_no}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Render the TC PDF from live data and persist it to the certificate
     * collection. Runs inside the issue transaction so a render/store failure
     * rolls the whole issue back.
     */
    private function attachPdf(TransferCertificate $tc, Student $student): void
    {
        $student->loadMissing([
            'branch:id,public_id,name,code,address,phone',
            'enrollments' => fn ($query) => $query
                ->where('status', EnrollmentStatus::Tc->value)
                ->with(['session', 'schoolClass:id,public_id,name', 'section:id,public_id,name']),
        ]);

        $pdf = Pdf::loadView('pdf.tc', [
            'tc' => $tc,
            'student' => $student,
            'branch' => $student->branch,
            'enrollment' => $student->enrollments->first(),
        ]);

        $tc->addMediaFromString($pdf->output())
            ->usingFileName("tc-{$tc->tc_no}.pdf")
            ->toMediaCollection('certificate');
    }
}
