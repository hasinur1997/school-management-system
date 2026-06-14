<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherAttendanceAdminTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create();

        $admin = User::factory()
            ->create(['branch_id' => $this->branch->id])
            ->assignRole('admin');

        $this->adminToken = $admin->createToken('web')->plainTextToken;
    }

    private function teacher(string $name = 'Teacher'): Teacher
    {
        return Teacher::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => $name,
        ]);
    }

    public function test_browse_filters_by_teacher_and_status(): void
    {
        $alice = $this->teacher('Alice');
        $bob = $this->teacher('Bob');

        TeacherAttendance::factory()->create([
            'teacher_id' => $alice->id,
            'date' => '2026-06-10',
            'status' => 'present',
        ]);
        TeacherAttendance::factory()->create([
            'teacher_id' => $bob->id,
            'date' => '2026-06-10',
            'status' => 'late',
        ]);

        $this->withToken($this->adminToken)
            ->getJson('/api/v1/teacher-attendance?teacher_id='.$alice->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.teacher.name', 'Alice')
            ->assertJsonPath('data.0.status', 'present')
            ->assertJsonPath('meta.total', 1);

        $this->withToken($this->adminToken)
            ->getJson('/api/v1/teacher-attendance?status=late')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.teacher.name', 'Bob');
    }

    public function test_correction_stamps_corrected_by(): void
    {
        $record = TeacherAttendance::factory()->create([
            'teacher_id' => $this->teacher()->id,
            'status' => 'present',
        ]);

        $admin = User::where('branch_id', $this->branch->id)->first();

        $this->withToken($this->adminToken)
            ->putJson("/api/v1/teacher-attendance/{$record->id}", ['status' => 'leave'])
            ->assertOk()
            ->assertJsonPath('data.status', 'leave')
            ->assertJsonPath('data.corrected_by.id', $admin->id)
            ->assertJsonPath('data.corrected_by.name', $admin->name);

        $this->assertDatabaseHas('teacher_attendances', [
            'id' => $record->id,
            'status' => 'leave',
            'corrected_by' => $admin->id,
        ]);
    }

    public function test_correction_with_checkout_before_checkin_is_unprocessable(): void
    {
        $record = TeacherAttendance::factory()->create([
            'teacher_id' => $this->teacher()->id,
            'check_in_at' => '2026-06-10 09:00:00',
        ]);

        $this->withToken($this->adminToken)
            ->putJson("/api/v1/teacher-attendance/{$record->id}", [
                'check_out_at' => '2026-06-10 08:00:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('check_out_at');
    }

    public function test_cross_branch_record_is_not_found(): void
    {
        $other = Branch::factory()->create();
        $otherTeacher = Teacher::factory()->create(['branch_id' => $other->id]);
        $record = TeacherAttendance::factory()->create(['teacher_id' => $otherTeacher->id]);

        $this->withToken($this->adminToken)
            ->putJson("/api/v1/teacher-attendance/{$record->id}", ['status' => 'leave'])
            ->assertNotFound();
    }

    public function test_me_returns_summary_and_records(): void
    {
        $user = User::factory()
            ->create(['branch_id' => $this->branch->id])
            ->assignRole('teacher');

        $teacher = Teacher::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $this->branch->id,
        ]);

        TeacherAttendance::factory()->create([
            'teacher_id' => $teacher->id,
            'date' => '2026-06-01',
            'status' => 'present',
        ]);
        TeacherAttendance::factory()->create([
            'teacher_id' => $teacher->id,
            'date' => '2026-06-02',
            'status' => 'late',
        ]);
        // Different month — excluded from June summary.
        TeacherAttendance::factory()->create([
            'teacher_id' => $teacher->id,
            'date' => '2026-05-15',
            'status' => 'present',
        ]);

        $this->withToken($user->createToken('web')->plainTextToken)
            ->getJson('/api/v1/me/teacher-attendance?month=6&year=2026')
            ->assertOk()
            ->assertJsonPath('data.summary.present', 1)
            ->assertJsonPath('data.summary.late', 1)
            ->assertJsonPath('data.summary.absent', 0)
            ->assertJsonPath('data.summary.leave', 0)
            ->assertJsonCount(2, 'data.records');
    }

    public function test_me_is_forbidden_for_non_teacher(): void
    {
        $this->withToken($this->adminToken)
            ->getJson('/api/v1/me/teacher-attendance')
            ->assertForbidden();
    }
}
