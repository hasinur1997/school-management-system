<?php

namespace App\Services;

use App\Enums\StudentStatus;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

class StudentService
{
    /**
     * List students in the caller's branch (branch scope is automatic), filtered
     * by class/section/session enrollment and status, with a free-text search
     * over name/admission_no/father_mobile. The current-session enrollment and
     * its class/section plus media are eager loaded so the compact resource
     * never lazy loads under strict mode.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return Student::query()
            ->with([
                'media',
                'currentEnrollment.schoolClass',
                'currentEnrollment.section',
            ])
            ->when(
                isset($filters['class_id']) || isset($filters['section_id']) || isset($filters['session_id']),
                fn (Builder $query) => $query->whereHas('enrollments', function (Builder $enrollment) use ($filters): void {
                    $enrollment
                        ->when(isset($filters['class_id']), fn (Builder $q) => $q->where('class_id', $filters['class_id']))
                        ->when(isset($filters['section_id']), fn (Builder $q) => $q->where('section_id', $filters['section_id']))
                        ->when(isset($filters['session_id']), fn (Builder $q) => $q->where('session_id', $filters['session_id']));
                })
            )
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['search']), function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where(fn (Builder $q) => $q
                    ->where('name_en', 'like', $term)
                    ->orWhere('name_bn', 'like', $term)
                    ->orWhere('admission_no', 'like', $term)
                    ->orWhere('father_mobile', 'like', $term));
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Load a student for the show endpoint: media plus the full enrollment
     * history (newest first), each with its session/class/section names.
     */
    public function loadProfile(Student $student): Student
    {
        return $student->load([
            'media',
            'user',
            'application',
            'enrollments' => fn ($query) => $query
                ->latest('id')
                ->with(['session', 'schoolClass', 'section']),
        ]);
    }

    /**
     * A student's class history, newest first, each row carrying its session,
     * class and section (eager loaded so the resource never lazy loads).
     *
     * @return Collection<int, Enrollment>
     */
    public function enrollmentHistory(Student $student): Collection
    {
        return $student->enrollments()
            ->with(['session', 'schoolClass', 'section'])
            ->latest('id')
            ->get();
    }

    /**
     * Update mutable profile fields. admission_no and birth_reg_no are immutable
     * and rejected at validation, so they never reach here.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Student $student, array $data): Student
    {
        $student->update($data);

        return $student->load([
            'media',
            'user',
            'application',
            'enrollments' => fn ($query) => $query
                ->latest('id')
                ->with(['session', 'schoolClass', 'section']),
        ]);
    }

    /**
     * Flip a student's status between active and inactive. The `tc` status is
     * blocked at validation (owned by the TC module).
     */
    public function setStatus(Student $student, StudentStatus $status): Student
    {
        $student->update(['status' => $status]);

        return $this->loadProfile($student);
    }

    /**
     * Store/replace the student's photo. The collection is single-file, so the
     * previous photo is removed automatically.
     */
    public function setPhoto(Student $student, UploadedFile $photo): Student
    {
        $student->addMedia($photo)->toMediaCollection('photo');

        return $this->loadProfile($student);
    }
}
