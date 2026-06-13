<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\CheckinIpWhitelist;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TeacherCheckinTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Teacher $teacher;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create();

        $user = User::factory()
            ->create(['branch_id' => $this->branch->id])
            ->assignRole('teacher');

        $this->teacher = Teacher::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->token = $user->createToken('web')->plainTextToken;

        CheckinIpWhitelist::factory()->create([
            'branch_id' => $this->branch->id,
            'ip_address' => '103.4.5.0/24',
            'is_active' => true,
        ]);
    }

    private function fromIp(string $ip): self
    {
        $this->withServerVariables(['REMOTE_ADDR' => $ip]);

        return $this;
    }

    public function test_teacher_checks_in_from_allowed_ip_as_present(): void
    {
        $this->travelTo(Carbon::parse('2026-06-11 08:32:10'));

        $response = $this->fromIp('103.4.5.10')
            ->withToken($this->token)
            ->postJson('/api/v1/teacher-attendance/check-in');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Checked in')
            ->assertJsonPath('data.date', '2026-06-11')
            ->assertJsonPath('data.status', 'present')
            ->assertJsonPath('data.check_out_at', null);

        $this->assertDatabaseHas('teacher_attendances', [
            'teacher_id' => $this->teacher->id,
            'date' => '2026-06-11 00:00:00',
            'check_in_ip' => '103.4.5.10',
            'status' => 'present',
        ]);
    }

    public function test_check_in_after_threshold_is_late(): void
    {
        $this->travelTo(Carbon::parse('2026-06-11 09:30:00'));

        $this->fromIp('103.4.5.10')
            ->withToken($this->token)
            ->postJson('/api/v1/teacher-attendance/check-in')
            ->assertOk()
            ->assertJsonPath('data.status', 'late');
    }

    public function test_check_in_from_blocked_ip_is_forbidden_and_leaks_nothing(): void
    {
        $response = $this->fromIp('10.0.0.5')
            ->withToken($this->token)
            ->postJson('/api/v1/teacher-attendance/check-in');

        $response->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'Check-in is not permitted from this network',
            ]);

        $this->assertDatabaseCount('teacher_attendances', 0);
    }

    public function test_double_check_in_is_a_conflict(): void
    {
        $this->fromIp('103.4.5.10')
            ->withToken($this->token)
            ->postJson('/api/v1/teacher-attendance/check-in')
            ->assertOk();

        $this->fromIp('103.4.5.10')
            ->withToken($this->token)
            ->postJson('/api/v1/teacher-attendance/check-in')
            ->assertStatus(409);

        $this->assertDatabaseCount('teacher_attendances', 1);
    }

    public function test_check_out_stamps_check_out_at(): void
    {
        $this->fromIp('103.4.5.10')
            ->withToken($this->token)
            ->postJson('/api/v1/teacher-attendance/check-in')
            ->assertOk();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/teacher-attendance/check-out');

        $response->assertOk()
            ->assertJsonPath('message', 'Checked out')
            ->assertJsonPath('data.check_out_at', fn ($value) => $value !== null);

        $this->assertNotNull(
            TeacherAttendance::where('teacher_id', $this->teacher->id)->first()->check_out_at
        );
    }

    public function test_check_out_without_check_in_is_a_conflict(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/teacher-attendance/check-out')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Not checked in');
    }

    public function test_double_check_out_is_a_conflict(): void
    {
        $this->fromIp('103.4.5.10')
            ->withToken($this->token)
            ->postJson('/api/v1/teacher-attendance/check-in')
            ->assertOk();

        $this->withToken($this->token)
            ->postJson('/api/v1/teacher-attendance/check-out')
            ->assertOk();

        $this->withToken($this->token)
            ->postJson('/api/v1/teacher-attendance/check-out')
            ->assertStatus(409);
    }

    public function test_inactive_whitelist_entry_does_not_permit_check_in(): void
    {
        CheckinIpWhitelist::query()->update(['is_active' => false]);

        $this->fromIp('103.4.5.10')
            ->withToken($this->token)
            ->postJson('/api/v1/teacher-attendance/check-in')
            ->assertForbidden();
    }

    public function test_non_teacher_is_forbidden(): void
    {
        $admin = User::factory()
            ->create(['branch_id' => $this->branch->id])
            ->assignRole('admin');

        $this->fromIp('103.4.5.10')
            ->withToken($admin->createToken('web')->plainTextToken)
            ->postJson('/api/v1/teacher-attendance/check-in')
            ->assertForbidden();
    }
}
