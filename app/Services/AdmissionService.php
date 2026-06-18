<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Jobs\SendCredentials;
use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\Enrollment;
use App\Models\ParentProfile;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Owns the public admission write/read paths. The submission persists the
 * application, its previous-education rows, and its media in a single
 * transaction so a media failure rolls the whole thing back — no orphaned
 * application without its photo.
 */
class AdmissionService
{
    public function __construct(
        private readonly ApplicationNoGenerator $applicationNos,
        private readonly AdmissionNoGenerator $admissionNos,
    ) {}

    /**
     * Persist a public admission submission atomically: the application row
     * (branch stamped explicitly — the public context has no auth user, so
     * BelongsToBranch does not stamp it), its previous-education children, and
     * the uploaded photo + documents.
     *
     * The application number is generated inside this transaction so the
     * branch row lock the generator takes spans the insert, serializing
     * concurrent same-branch submissions.
     *
     * @param  array<string, mixed>  $data  Validated request data.
     */
    public function submit(array $data): AdmissionApplication
    {
        $photo = $data['photo'];
        $documents = $data['documents'] ?? [];
        $previousEducations = $data['previous_educations'] ?? [];

        $attributes = Arr::except($data, ['photo', 'documents', 'previous_educations']);

        // The column is NOT NULL (schema item 7) while the contract marks the
        // field optional; absence persists as an empty string.
        $attributes['mother_mobile'] ??= '';

        return DB::transaction(function () use ($attributes, $previousEducations, $photo, $documents): AdmissionApplication {
            $branchId = (int) $attributes['branch_id'];

            // branch_id is stamped explicitly below (it is intentionally not
            // mass-assignable), so keep it out of the constructor.
            $application = new AdmissionApplication(Arr::except($attributes, ['branch_id']));
            $application->branch_id = $branchId;
            $application->application_no = $this->applicationNos->generate($branchId);
            $application->save();

            if ($previousEducations !== []) {
                $application->previousEducations()->createMany($previousEducations);
            }

            $application->addMedia($photo)->toMediaCollection('photo');

            foreach ($documents as $document) {
                $application->addMedia($document)->toMediaCollection('documents');
            }

            return $application;
        });
    }

    /**
     * List admission applications in the caller's branch (branch isolation is
     * automatic via BranchScope). Defaults to pending; supports filtering by
     * desired class and a created-date range, plus a free-text search across
     * the applicant/father identifiers. The desired class is eager loaded for
     * the compact rows so it never lazy loads in the Resource.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        $status = $filters['status'] ?? AdmissionStatus::Pending->value;

        return AdmissionApplication::query()
            ->with('desiredClass')
            ->where('status', $status)
            ->when(isset($filters['desired_class_id']), fn (Builder $query) => $query->where('desired_class_id', $filters['desired_class_id']))
            ->when(isset($filters['from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->when(isset($filters['search']), function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where(fn (Builder $q) => $q
                    ->where('name_en', 'like', $term)
                    ->orWhere('name_bn', 'like', $term)
                    ->orWhere('application_no', 'like', $term)
                    ->orWhere('father_mobile', 'like', $term)
                    ->orWhere('birth_reg_no', 'like', $term));
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Eager load everything the detail Resource touches: the desired class, the
     * previous-education child rows, the reviewer, and the media (photo +
     * documents).
     */
    public function loadDetail(AdmissionApplication $application): AdmissionApplication
    {
        return $application->load(['desiredClass', 'previousEducations', 'reviewer', 'media']);
    }

    /**
     * Approve an application, converting it into a real student in ONE
     * transaction: a student login (email null, phone = father_mobile, random
     * password), the student row (a faithful copy of the application data plus
     * its photo), the active enrollment, and — when requested — a linked parent
     * login + profile + parent_student row. The application is marked approved
     * with the reviewer stamped. Any failure rolls back every row. Credential
     * jobs are dispatched only afterCommit, so they never fire on a rollback.
     *
     * @param  array<string, mixed>  $data  Validated approval input.
     * @return array{student: Student, parent_created: bool}
     */
    public function approve(AdmissionApplication $application, array $data): array
    {
        if ($application->status !== AdmissionStatus::Pending) {
            abort(409, 'Application has already been reviewed.');
        }

        $reviewerId = Auth::id();

        return DB::transaction(function () use ($application, $data, $reviewerId): array {
            $branchId = $application->branch_id;

            $session = AcademicSession::findOrFail($data['session_id']);
            $class = SchoolClass::findOrFail($data['class_id']);
            $section = Section::findOrFail($data['section_id']);

            // Resolve the parent's contact up front (when requested) so the
            // student/parent logins never collide on the globally-unique
            // users.phone: the parent is the human who actually owns the shared
            // number and must be able to log in, so they claim it and the
            // student's phone is left null when the two would be identical.
            $createParent = (bool) $data['create_parent_account'];
            $parentName = $parentPhone = null;

            if ($createParent) {
                [$parentName, $parentPhone] = match ($data['parent_relation']) {
                    'mother' => [$application->mother_name_en, $application->mother_mobile ?: $application->father_mobile],
                    default => [$application->father_name_en, $application->father_mobile],
                };
            }

            $studentPhone = $application->father_mobile;
            if ($createParent && $parentPhone === $studentPhone) {
                $studentPhone = null;
            }

            // Student login — phone is the only identifier (email null per spec).
            $studentPassword = Str::password(10);
            $studentUser = User::create([
                'branch_id' => $branchId,
                'name' => $application->name_en,
                'email' => null,
                'phone' => $studentPhone,
                'password' => Hash::make($studentPassword),
                'is_active' => true,
            ]);
            $studentUser->assignRole('student');

            $admissionNo = $data['admission_no']
                ?? $this->admissionNos->generate($branchId, (int) $session->start_date->year);

            // Copy the application's bilingual identity + address fields verbatim.
            $student = new Student($application->only([
                'name_bn', 'name_en',
                'father_name_bn', 'father_name_en', 'father_nid',
                'mother_name_bn', 'mother_name_en', 'mother_nid',
                'present_village', 'present_post_office', 'present_upazila', 'present_district',
                'permanent_village_bn', 'permanent_post_office_bn', 'permanent_upazila_bn', 'permanent_district_bn',
                'permanent_village_en', 'permanent_post_office_en', 'permanent_upazila_en', 'permanent_district_en',
                'father_mobile', 'mother_mobile',
                'birth_reg_no', 'date_of_birth', 'religion', 'nationality', 'caste',
            ]));
            $student->user_id = $studentUser->id;
            $student->application_id = $application->id;
            $student->admission_no = $admissionNo;
            $student->status = StudentStatus::Active;
            $student->admitted_at = now();
            $student->branch_id = $branchId;
            $student->save();

            // Copy the applicant photo onto the student (single-file collection).
            if (($photo = $application->getFirstMedia('photo')) !== null) {
                $photo->copy($student, 'photo');
            }

            $enrollment = Enrollment::create([
                'student_id' => $student->id,
                'session_id' => $session->id,
                'class_id' => $class->id,
                'section_id' => $section->id,
                'roll_no' => $data['roll_no'],
                'status' => EnrollmentStatus::Active,
            ]);

            $parentCreated = false;

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
                $parentCreated = true;

                SendCredentials::dispatch($parentUser, $parentPassword, 'Parent')->afterCommit();
            }

            $application->update([
                'status' => AdmissionStatus::Approved,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);

            SendCredentials::dispatch($studentUser, $studentPassword, 'Student')->afterCommit();

            // Preload the relations the approval Resource reads, no lazy loads.
            $enrollment->setRelation('session', $session);
            $enrollment->setRelation('schoolClass', $class);
            $enrollment->setRelation('section', $section);
            $student->setRelation('enrollments', collect([$enrollment]));

            return ['student' => $student, 'parent_created' => $parentCreated];
        });
    }

    /**
     * Reject an application, stamping the reason and reviewer. Re-review is
     * blocked (409); a rejected application can therefore never be approved.
     */
    public function reject(AdmissionApplication $application, string $reason): AdmissionApplication
    {
        if ($application->status !== AdmissionStatus::Pending) {
            abort(409, 'Application has already been reviewed.');
        }

        $application->update([
            'status' => AdmissionStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return $application;
    }

    /**
     * Resolve an application for a public status check. Both the application
     * number and date_of_birth must match; a miss throws model-not-found
     * (rendered 404) so existence is never revealed.
     */
    public function findForStatus(string $applicationNo, string $dateOfBirth): AdmissionApplication
    {
        return AdmissionApplication::query()
            ->where('application_no', $applicationNo)
            ->whereDate('date_of_birth', $dateOfBirth)
            ->firstOrFail();
    }
}
