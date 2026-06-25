<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\SendCredentials;
use App\Mail\CredentialsMail;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\ParentProfile;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ParentEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create();
        $this->session = AcademicSession::factory()->current()->create();
    }

    private function tokenForRole(string $role): string
    {
        $user = User::factory()
            ->create(['branch_id' => $role === 'super_admin' ? null : $this->branch->id])
            ->assignRole($role);

        return $user->createToken('web')->plainTextToken;
    }

    private function makeStudent(array $overrides = [], ?Branch $branch = null, array $enrollment = []): Student
    {
        $branch ??= $this->branch;
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('student');

        $student = Student::factory()->create(array_merge([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
        ], $overrides));

        if ($enrollment !== []) {
            Enrollment::factory()->create(array_merge([
                'student_id' => $student->id,
                'session_id' => $this->session->id,
            ], $enrollment));
        }

        return $student;
    }

    public function test_create_parent_with_links_in_one_transaction_queues_credentials(): void
    {
        Queue::fake();

        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Class 7']);
        $section = Section::factory()->create(['class_id' => $class->id, 'name' => 'A']);
        $student = $this->makeStudent(
            ['name_en' => 'Rahima Khatun'],
            enrollment: ['class_id' => $class->id, 'section_id' => $section->id, 'roll_no' => 12],
        );

        $response = $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/parents', [
                'name' => 'Abdul Karim',
                'phone' => '01811111111',
                'email' => null,
                'relation' => 'father',
                'student_ids' => [$student->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Abdul Karim')
            ->assertJsonPath('data.relation', 'father')
            ->assertJsonCount(1, 'data.students')
            ->assertJsonPath('data.students.0.id', $student->id)
            ->assertJsonPath('data.students.0.name_en', 'Rahima Khatun')
            ->assertJsonPath('data.students.0.class', 'Class 7')
            ->assertJsonPath('data.students.0.section', 'A');

        $parentId = $response->json('data.id');

        $this->assertDatabaseHas('parents', ['id' => $parentId, 'phone' => '01811111111', 'relation' => 'father']);
        $this->assertDatabaseHas('parent_student', ['parent_id' => $parentId, 'student_id' => $student->id]);

        $user = User::where('phone', '01811111111')->firstOrFail();
        $this->assertTrue($user->hasRole('parent'));

        Queue::assertPushed(SendCredentials::class, fn ($job) => $job->user->id === $user->id && $job->role === 'Parent');
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        $student = $this->makeStudent();
        User::factory()->create(['branch_id' => $this->branch->id, 'phone' => '01811111111']);

        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/parents', [
                'name' => 'Abdul Karim',
                'phone' => '01811111111',
                'relation' => 'father',
                'student_ids' => [$student->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }

    public function test_invalid_relation_is_rejected(): void
    {
        $student = $this->makeStudent();

        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/parents', [
                'name' => 'Abdul Karim',
                'phone' => '01811111111',
                'relation' => 'uncle',
                'student_ids' => [$student->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('relation');
    }

    public function test_create_with_foreign_branch_student_is_rejected(): void
    {
        $otherBranch = Branch::factory()->create();
        $foreign = $this->makeStudent(branch: $otherBranch);

        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/parents', [
                'name' => 'Abdul Karim',
                'phone' => '01811111111',
                'relation' => 'father',
                'student_ids' => [$foreign->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('student_ids');
    }

    public function test_link_unlink_with_conflict_and_not_found_semantics(): void
    {
        $parent = $this->makeParent();
        $linked = $this->makeStudent();
        $parent->students()->attach($linked->id);

        $other = $this->makeStudent();
        $token = $this->tokenForRole('admin');

        // duplicate link → 409
        $this->withToken($token)
            ->postJson("/api/v1/parents/{$parent->id}/students", ['student_id' => $linked->id])
            ->assertStatus(409);

        // fresh link → ok
        $this->withToken($token)
            ->postJson("/api/v1/parents/{$parent->id}/students", ['student_id' => $other->id])
            ->assertOk();
        $this->assertDatabaseHas('parent_student', ['parent_id' => $parent->id, 'student_id' => $other->id]);

        // unlink a non-linked (but valid, never linked) student → 404
        $never = $this->makeStudent();
        $this->withToken($token)
            ->deleteJson("/api/v1/parents/{$parent->id}/students/{$never->id}")
            ->assertStatus(404);

        // unlink a linked student → ok
        $this->withToken($token)
            ->deleteJson("/api/v1/parents/{$parent->id}/students/{$linked->id}")
            ->assertOk();
        $this->assertDatabaseMissing('parent_student', ['parent_id' => $parent->id, 'student_id' => $linked->id]);
    }

    public function test_link_foreign_branch_student_is_rejected(): void
    {
        $parent = $this->makeParent();
        $otherBranch = Branch::factory()->create();
        $foreign = $this->makeStudent(branch: $otherBranch);

        $this->withToken($this->tokenForRole('admin'))
            ->postJson("/api/v1/parents/{$parent->id}/students", ['student_id' => $foreign->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('student_id');
    }

    public function test_me_students_returns_exactly_linked_for_parent_role(): void
    {
        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Class 7']);
        $section = Section::factory()->create(['class_id' => $class->id, 'name' => 'A']);

        $linked = $this->makeStudent(
            ['name_en' => 'Rahima Khatun'],
            enrollment: ['class_id' => $class->id, 'section_id' => $section->id, 'roll_no' => 12],
        );
        $this->makeStudent(['name_en' => 'Unrelated Child']);

        $parentUser = User::factory()->create(['branch_id' => $this->branch->id]);
        $parentUser->assignRole('parent');
        $parent = ParentProfile::factory()->create([
            'branch_id' => $this->branch->id,
            'user_id' => $parentUser->id,
        ]);
        $parent->students()->attach($linked->id);

        $token = $parentUser->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/me/students')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $linked->id)
            ->assertJsonPath('data.0.name_en', 'Rahima Khatun')
            ->assertJsonPath('data.0.class', 'Class 7')
            ->assertJsonPath('data.0.section', 'A');
    }

    public function test_me_students_forbidden_for_non_parent_role(): void
    {
        $this->withToken($this->tokenForRole('admin'))
            ->getJson('/api/v1/me/students')
            ->assertStatus(403);
    }

    public function test_resend_credentials_queues_mail_for_parent(): void
    {
        Mail::fake();
        $parent = $this->makeParent();
        $parent->user->forceFill(['email' => 'guardian@example.test', 'phone' => '01822222222'])->save();
        $oldHash = $parent->user->password;

        $this->withToken($this->tokenForRole('admin'))
            ->postJson("/api/v1/parents/{$parent->public_id}/resend-credentials")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'New credentials are being sent to the parent.')
            ->assertJsonPath('data', null);

        $this->assertNotSame($oldHash, $parent->user->fresh()->password);

        Mail::assertSent(CredentialsMail::class, function (CredentialsMail $mail): bool {
            return $mail->hasTo('guardian@example.test')
                && $mail->role === 'Parent'
                && $mail->email === 'guardian@example.test'
                && $mail->phone === '01822222222';
        });
    }

    public function test_resend_credentials_422_when_parent_has_no_email(): void
    {
        Mail::fake();
        $parent = $this->makeParent();
        $parent->user->forceFill(['email' => null])->save();

        $this->withToken($this->tokenForRole('admin'))
            ->postJson("/api/v1/parents/{$parent->public_id}/resend-credentials")
            ->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_resend_credentials_requires_parent_manage_permission(): void
    {
        $parent = $this->makeParent();

        $this->withToken($this->tokenForRole('teacher'))
            ->postJson("/api/v1/parents/{$parent->public_id}/resend-credentials")
            ->assertStatus(403);
    }

    private function makeParent(?Branch $branch = null): ParentProfile
    {
        $branch ??= $this->branch;
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('parent');

        return ParentProfile::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
        ]);
    }
}
