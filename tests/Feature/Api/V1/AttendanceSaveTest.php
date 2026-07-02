<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceSaveTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create();
        $this->session = AcademicSession::factory()->current()->create(['name' => '2026']);
        $this->class = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Class 7']);
        $this->section = Section::factory()->create(['class_id' => $this->class->id, 'name' => 'A']);
    }

    private function token(string $role = 'admin'): string
    {
        $user = User::factory()
            ->create(['branch_id' => $role === 'super_admin' ? null : $this->branch->id])
            ->assignRole($role);

        return $user->createToken('web')->plainTextToken;
    }

    /**
     * Create a teacher login (users row + teacher profile) and return its token.
     */
    private function teacherToken(): array
    {
        $user = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('teacher');
        $teacher = Teacher::factory()->create(['branch_id' => $this->branch->id, 'user_id' => $user->id]);

        return [$teacher, $user->createToken('web')->plainTextToken];
    }

    private function enroll(int $rollNo, ?Section $section = null, EnrollmentStatus $status = EnrollmentStatus::Active): Enrollment
    {
        $student = Student::factory()->create(['branch_id' => $this->branch->id]);

        return Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => ($section ?? $this->section)->id,
            'roll_no' => $rollNo,
            'status' => $status,
        ]);
    }

    public function test_bulk_save_inserts_then_updates_idempotently(): void
    {
        $a = $this->enroll(1);
        $b = $this->enroll(2);

        DB::connection()->enableQueryLog();

        $this->withToken($this->token())
            ->postJson('/api/v1/attendance', [
                'section_id' => $this->section->id,
                'date' => '2026-06-11',
                'records' => [
                    ['enrollment_id' => $a->id, 'status' => 'present'],
                    ['enrollment_id' => $b->id, 'status' => 'absent'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.saved', 2);

        // The bulk write is a single insert statement, not one per record.
        $inserts = array_filter(
            DB::connection()->getQueryLog(),
            fn (array $entry): bool => str_contains(strtolower($entry['query']), 'insert into "student_attendances"'),
        );
        $this->assertCount(1, $inserts);
        DB::connection()->disableQueryLog();

        $this->assertDatabaseHas('student_attendances', [
            'enrollment_id' => $a->id, 'date' => '2026-06-11', 'status' => 'present',
        ]);

        // Re-post the same date with changed statuses — updates, does not duplicate.
        $this->withToken($this->token())
            ->postJson('/api/v1/attendance', [
                'section_id' => $this->section->id,
                'date' => '2026-06-11',
                'records' => [
                    ['enrollment_id' => $a->id, 'status' => 'late'],
                    ['enrollment_id' => $b->id, 'status' => 'leave'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.saved', 2);

        $this->assertSame(2, StudentAttendance::count());
        $this->assertDatabaseHas('student_attendances', [
            'enrollment_id' => $a->id, 'date' => '2026-06-11', 'status' => 'late',
        ]);
        $this->assertDatabaseHas('student_attendances', [
            'enrollment_id' => $b->id, 'date' => '2026-06-11', 'status' => 'leave',
        ]);
    }

    public function test_recorded_by_is_stamped_from_the_caller(): void
    {
        $enrollment = $this->enroll(1);
        $user = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('admin');
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/attendance', [
                'section_id' => $this->section->id,
                'date' => '2026-06-11',
                'records' => [['enrollment_id' => $enrollment->id, 'status' => 'present']],
            ])
            ->assertOk();

        $this->assertDatabaseHas('student_attendances', [
            'enrollment_id' => $enrollment->id,
            'recorded_by' => $user->id,
        ]);
    }

    public function test_unassigned_teacher_is_forbidden_but_admin_succeeds(): void
    {
        $enrollment = $this->enroll(1);
        [, $teacherToken] = $this->teacherToken();

        $this->withToken($teacherToken)
            ->postJson('/api/v1/attendance', [
                'section_id' => $this->section->id,
                'date' => '2026-06-11',
                'records' => [['enrollment_id' => $enrollment->id, 'status' => 'present']],
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'You are not assigned to this class');

        // The sanctum guard caches the resolved user across requests in a
        // single test, so reset it before authenticating as a different user.
        $this->app['auth']->forgetGuards();

        // An admin (non-teacher staff with attendance.create) bypasses the check.
        $this->withToken($this->token())
            ->postJson('/api/v1/attendance', [
                'section_id' => $this->section->id,
                'date' => '2026-06-11',
                'records' => [['enrollment_id' => $enrollment->id, 'status' => 'present']],
            ])
            ->assertOk()
            ->assertJsonPath('data.saved', 1);
    }

    public function test_assigned_teacher_can_save(): void
    {
        $enrollment = $this->enroll(1);
        [$teacher, $teacherToken] = $this->teacherToken();

        TeacherAssignment::factory()->create([
            'teacher_id' => $teacher->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
        ]);

        $this->withToken($teacherToken)
            ->postJson('/api/v1/attendance', [
                'section_id' => $this->section->id,
                'date' => '2026-06-11',
                'records' => [['enrollment_id' => $enrollment->id, 'status' => 'present']],
            ])
            ->assertOk()
            ->assertJsonPath('data.saved', 1);
    }

    public function test_future_date_is_422(): void
    {
        $enrollment = $this->enroll(1);

        $this->withToken($this->token())
            ->postJson('/api/v1/attendance', [
                'section_id' => $this->section->id,
                'date' => now()->addDay()->toDateString(),
                'records' => [['enrollment_id' => $enrollment->id, 'status' => 'present']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_tc_enrollment_is_rejected_keyed_to_the_record(): void
    {
        $active = $this->enroll(1);
        $tc = $this->enroll(2, status: EnrollmentStatus::Tc);

        $this->withToken($this->token())
            ->postJson('/api/v1/attendance', [
                'section_id' => $this->section->id,
                'date' => '2026-06-11',
                'records' => [
                    ['enrollment_id' => $active->id, 'status' => 'present'],
                    ['enrollment_id' => $tc->id, 'status' => 'absent'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['records.1.enrollment_id']);
    }

    public function test_enrollment_from_another_section_is_rejected(): void
    {
        $otherSection = Section::factory()->create(['class_id' => $this->class->id, 'name' => 'B']);
        $foreign = $this->enroll(1, section: $otherSection);

        $this->withToken($this->token())
            ->postJson('/api/v1/attendance', [
                'section_id' => $this->section->id,
                'date' => '2026-06-11',
                'records' => [['enrollment_id' => $foreign->id, 'status' => 'present']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['records.0.enrollment_id']);
    }

    public function test_invalid_status_is_422(): void
    {
        $enrollment = $this->enroll(1);

        $this->withToken($this->token())
            ->postJson('/api/v1/attendance', [
                'section_id' => $this->section->id,
                'date' => '2026-06-11',
                'records' => [['enrollment_id' => $enrollment->id, 'status' => 'holiday']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['records.0.status']);
    }

    public function test_correction_endpoint_updates_a_single_record(): void
    {
        $enrollment = $this->enroll(1);
        $record = StudentAttendance::factory()->create([
            'enrollment_id' => $enrollment->id,
            'date' => '2026-06-11',
            'status' => AttendanceStatus::Absent,
        ]);

        $this->withToken($this->token())
            ->putJson("/api/v1/attendance/{$record->id}", ['status' => 'late'])
            ->assertOk()
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.status', 'late');

        $this->assertDatabaseHas('student_attendances', [
            'id' => $record->id, 'status' => 'late',
        ]);
    }

    public function test_correction_of_another_branch_record_is_404(): void
    {
        // A record whose student lives in another branch.
        $otherBranch = Branch::factory()->create();
        $otherClass = SchoolClass::factory()->create(['branch_id' => $otherBranch->id]);
        $otherSection = Section::factory()->create(['class_id' => $otherClass->id]);
        $student = Student::factory()->create(['branch_id' => $otherBranch->id]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $this->session->id,
            'class_id' => $otherClass->id,
            'section_id' => $otherSection->id,
            'roll_no' => 1,
        ]);
        $record = StudentAttendance::factory()->create([
            'enrollment_id' => $enrollment->id,
            'date' => '2026-06-11',
            'status' => AttendanceStatus::Present,
        ]);

        $this->withToken($this->token())
            ->putJson("/api/v1/attendance/{$record->id}", ['status' => 'late'])
            ->assertStatus(404);
    }

    public function test_unknown_record_correction_is_404(): void
    {
        $this->withToken($this->token())
            ->putJson('/api/v1/attendance/999999', ['status' => 'late'])
            ->assertStatus(404);
    }

    public function test_browse_lists_records_in_branch(): void
    {
        $enrollment = $this->enroll(1);
        StudentAttendance::factory()->create([
            'enrollment_id' => $enrollment->id,
            'date' => '2026-06-11',
            'status' => AttendanceStatus::Present,
        ]);

        $this->withToken($this->token())
            ->getJson("/api/v1/attendance?section_id={$this->section->id}&date=2026-06-11")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'present')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_browse_requires_attendance_view_permission(): void
    {
        // Teacher holds attendance.create/view; accountant holds neither.
        $this->withToken($this->token('accountant'))
            ->getJson('/api/v1/attendance')
            ->assertStatus(403);
    }

    public function test_whole_class_save_with_class_id_and_no_section(): void
    {
        $a = $this->enroll(1);

        // A second section of the same class with its own student.
        $sectionB = Section::factory()->create(['class_id' => $this->class->id, 'name' => 'B']);
        $studentB = Student::factory()->create(['branch_id' => $this->branch->id]);
        $b = Enrollment::factory()->create([
            'student_id' => $studentB->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $sectionB->id,
            'roll_no' => 1,
            'status' => EnrollmentStatus::Active,
        ]);

        $this->withToken($this->token())
            ->postJson('/api/v1/attendance', [
                'class_id' => $this->class->id,
                'date' => '2026-06-11',
                'records' => [
                    ['enrollment_id' => $a->id, 'status' => 'present'],
                    ['enrollment_id' => $b->id, 'status' => 'absent'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.saved', 2);

        $this->assertDatabaseHas('student_attendances', [
            'enrollment_id' => $b->id, 'date' => '2026-06-11', 'status' => 'absent',
        ]);
    }

    public function test_save_without_class_or_section_is_422(): void
    {
        $a = $this->enroll(1);

        $this->withToken($this->token())
            ->postJson('/api/v1/attendance', [
                'date' => '2026-06-11',
                'records' => [['enrollment_id' => $a->id, 'status' => 'present']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['class_id', 'section_id']);
    }

    public function test_whole_class_save_rejects_enrollment_of_another_class(): void
    {
        $otherClass = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Class 8']);
        $otherSection = Section::factory()->create(['class_id' => $otherClass->id, 'name' => 'A']);
        $foreign = $this->enroll(1, $otherSection);
        // enroll() pins class_id to the test class; move it to the other class.
        $foreign->update(['class_id' => $otherClass->id]);

        $this->withToken($this->token())
            ->postJson('/api/v1/attendance', [
                'class_id' => $this->class->id,
                'date' => '2026-06-11',
                'records' => [['enrollment_id' => $foreign->id, 'status' => 'present']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['records.0.enrollment_id']);
    }

    public function test_super_admin_browse_narrows_by_branch_filter(): void
    {
        $inBranch = $this->enroll(1);
        StudentAttendance::factory()->create([
            'enrollment_id' => $inBranch->id,
            'date' => '2026-06-11',
            'status' => AttendanceStatus::Present,
        ]);

        // A second branch with its own class/section/enrollment + mark.
        $otherBranch = Branch::factory()->create();
        $otherClass = SchoolClass::factory()->create(['branch_id' => $otherBranch->id, 'name' => 'Class 8']);
        $otherSection = Section::factory()->create(['class_id' => $otherClass->id, 'name' => 'B']);
        $otherStudent = Student::factory()->create(['branch_id' => $otherBranch->id]);
        $otherEnrollment = Enrollment::factory()->create([
            'student_id' => $otherStudent->id,
            'session_id' => $this->session->id,
            'class_id' => $otherClass->id,
            'section_id' => $otherSection->id,
            'roll_no' => 1,
            'status' => EnrollmentStatus::Active,
        ]);
        StudentAttendance::factory()->create([
            'enrollment_id' => $otherEnrollment->id,
            'date' => '2026-06-11',
            'status' => AttendanceStatus::Absent,
        ]);

        $token = $this->token('super_admin');

        // No filter: super admin sees both branches' records.
        $this->withToken($token)
            ->getJson('/api/v1/attendance?date=2026-06-11')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // Narrowed to one branch: only that branch's record remains.
        $this->withToken($token)
            ->getJson("/api/v1/attendance?date=2026-06-11&branch_id={$this->branch->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'present');

        // The sanctum guard caches the resolved user across requests in a
        // single test, so reset it before authenticating as a different user.
        $this->app['auth']->forgetGuards();

        // Non-super-admins have branch_id excluded; branch scope still governs.
        $this->withToken($this->token())
            ->getJson("/api/v1/attendance?date=2026-06-11&branch_id={$otherBranch->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'present');
    }
}
