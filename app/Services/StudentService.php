<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Jobs\SendCredentials;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentService
{
    public function __construct(private readonly AdmissionNoGenerator $admissionNos) {}

    /**
     * Create a student directly (the office path, with no admission application)
     * in ONE transaction: a student login (email null, phone = father_mobile,
     * random password), the student row, its active enrollment, and — when
     * requested — a linked parent login + profile + parent_student row. The
     * branch is resolved by the caller (the caller's own, or the chosen one for
     * super admins) and stamped explicitly so it holds even when BranchScope is
     * bypassed. Any failure rolls back every row; credential jobs are dispatched
     * afterCommit so they never fire on a rollback. Mirrors
     * AdmissionService::approve minus the application.
     *
     * @param  array<string, mixed>  $data  Validated creation input.
     */
    public function create(array $data, int $branchId): Student
    {
        return DB::transaction(function () use ($data, $branchId): Student {
            $session = AcademicSession::findOrFail($data['session_id']);

            // Resolve the parent's contact up front (when requested) so the
            // student/parent logins never collide on the globally-unique
            // users.phone: the parent owns the shared number and the student's
            // phone is left null when the two would be identical.
            $createParent = (bool) $data['create_parent_account'];
            $parentName = $parentPhone = null;

            if ($createParent) {
                [$parentName, $parentPhone] = match ($data['parent_relation']) {
                    'mother' => [$data['mother_name_en'], $data['mother_mobile'] ?: $data['father_mobile']],
                    default => [$data['father_name_en'], $data['father_mobile']],
                };
            }

            $studentPhone = $data['father_mobile'];
            if ($createParent && $parentPhone === $studentPhone) {
                $studentPhone = null;
            }

            // Student login — phone is the only identifier (email null per spec).
            $studentPassword = Str::password(10);
            $studentUser = User::create([
                'branch_id' => $branchId,
                'name' => $data['name_en'],
                'email' => null,
                'phone' => $studentPhone,
                'password' => Hash::make($studentPassword),
                'is_active' => true,
            ]);
            $studentUser->assignRole('student');

            $admissionNo = ($data['admission_no'] ?? null)
                ?: $this->admissionNos->generate($branchId, (int) $session->start_date->year);

            $student = new Student(Arr::only($data, [
                'name_bn', 'name_en',
                'father_name_bn', 'father_name_en', 'father_nid',
                'mother_name_bn', 'mother_name_en', 'mother_nid',
                'present_village', 'present_post_office', 'present_upazila', 'present_district', 'present_division',
                'permanent_village', 'permanent_post_office', 'permanent_upazila', 'permanent_district', 'permanent_division',
                'father_mobile', 'mother_mobile',
                'birth_reg_no', 'date_of_birth', 'religion', 'nationality', 'caste',
            ]));
            $student->user_id = $studentUser->id;
            $student->admission_no = $admissionNo;
            $student->status = StudentStatus::Active;
            $student->admitted_at = now();
            $student->branch_id = $branchId;
            $student->save();

            Enrollment::create([
                'student_id' => $student->id,
                'session_id' => $session->id,
                'class_id' => $data['class_id'],
                'section_id' => $data['section_id'],
                'roll_no' => $data['roll_no'],
                'status' => EnrollmentStatus::Active,
            ]);

            if ($createParent) {
                $parentPassword = Str::password(10);
                $parentUser = User::create([
                    'branch_id' => $branchId,
                    'name' => $parentName,
                    'email' => null,
                    'phone' => $parentPhone,
                    'password' => Hash::make($parentPassword),
                    'is_active' => true,
                ]);
                $parentUser->assignRole('parent');

                $parent = new ParentProfile([
                    'user_id' => $parentUser->id,
                    'name' => $parentName,
                    'phone' => $parentPhone,
                    'relation' => $data['parent_relation'],
                ]);
                $parent->branch_id = $branchId;
                $parent->save();

                $parent->students()->attach($student->id);

                SendCredentials::dispatch($parentUser, $parentPassword, 'Parent')->afterCommit();
            }

            SendCredentials::dispatch($studentUser, $studentPassword, 'Student')->afterCommit();

            return $this->loadProfile($student);
        });
    }

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
            'branch',
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
            'branch',
            'enrollments' => fn ($query) => $query
                ->latest('id')
                ->with(['session', 'schoolClass', 'section']),
        ]);
    }

    /**
     * Update a single enrollment row and return it with its session/class/
     * section eager loaded (so the resource never lazy loads). Branch isolation
     * and ownership are enforced by the caller (the controller).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateEnrollment(Enrollment $enrollment, array $data): Enrollment
    {
        $enrollment->update($data);

        return $enrollment->load(['session', 'schoolClass', 'section']);
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
