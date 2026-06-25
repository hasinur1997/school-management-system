<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TeacherStatus;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeacherEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create();
    }

    private function tokenForRole(string $role): string
    {
        $user = User::factory()
            ->create(['branch_id' => $role === 'super_admin' ? null : $this->branch->id])
            ->assignRole($role);

        return $user->createToken('web')->plainTextToken;
    }

    private function makeTeacher(array $overrides = [], ?Branch $branch = null): Teacher
    {
        $branch ??= $this->branch;
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'password' => Hash::make('original-password'),
        ]);
        $user->assignRole('teacher');

        return Teacher::factory()->create(array_merge([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
        ], $overrides));
    }

    public function test_index_filters_by_status_and_search(): void
    {
        $rahim = $this->makeTeacher(['name' => 'Rahim Uddin', 'designation' => 'Senior Teacher', 'status' => TeacherStatus::Active]);
        $rahim->user->update(['email' => 'rahim.user@example.test']);
        $this->makeTeacher(['name' => 'Karim Mia', 'status' => TeacherStatus::Active]);
        $this->makeTeacher(['name' => 'Rahima Begum', 'status' => TeacherStatus::Inactive]);

        $token = $this->tokenForRole('admin');

        // status filter
        $this->withToken($token)
            ->getJson('/api/v1/teachers?status=active')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        // search across name
        $this->withToken($token)
            ->getJson('/api/v1/teachers?search=Uddin')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Rahim Uddin');

        // search across the linked user's email
        $this->withToken($token)
            ->getJson('/api/v1/teachers?search=rahim.user')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'rahim.user@example.test');
    }

    public function test_index_sorts_by_name(): void
    {
        $this->makeTeacher(['name' => 'Zubair Ahmed']);
        $this->makeTeacher(['name' => 'Abdul Karim']);

        $this->withToken($this->tokenForRole('admin'))
            ->getJson('/api/v1/teachers?sort=name&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Abdul Karim')
            ->assertJsonPath('data.1.name', 'Zubair Ahmed');
    }

    public function test_show_includes_current_session_assignments(): void
    {
        $teacher = $this->makeTeacher();
        $session = AcademicSession::factory()->current()->create();
        $past = AcademicSession::factory()->create();

        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $section = Section::factory()->create(['class_id' => $class->id]);
        $subject = Subject::factory()->create(['class_id' => $class->id]);

        TeacherAssignment::factory()->create([
            'teacher_id' => $teacher->id,
            'session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
        ]);
        // An assignment in a non-current session must be excluded.
        TeacherAssignment::factory()->create([
            'teacher_id' => $teacher->id,
            'session_id' => $past->id,
            'class_id' => $class->id,
        ]);

        $this->withToken($this->tokenForRole('admin'))
            ->getJson("/api/v1/teachers/{$teacher->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.assignments')
            ->assertJsonPath('data.assignments.0.class.name', $class->name)
            ->assertJsonPath('data.assignments.0.section.name', $section->name)
            ->assertJsonPath('data.assignments.0.subject.name', $subject->name);
    }

    public function test_update_changes_profile_and_mirrors_phone_to_login(): void
    {
        $teacher = $this->makeTeacher(['name' => 'Old Name', 'phone' => '01700000000']);

        $this->withToken($this->tokenForRole('admin'))
            ->putJson("/api/v1/teachers/{$teacher->public_id}", [
                'name' => 'New Name',
                'email' => 'new.teacher@example.test',
                'phone' => '01799999999',
                'designation' => 'Head Teacher',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.email', 'new.teacher@example.test')
            ->assertJsonPath('data.designation', 'Head Teacher');

        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id,
            'name' => 'New Name',
            'email' => 'new.teacher@example.test',
            'phone' => '01799999999',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $teacher->user_id,
            'name' => 'New Name',
            'email' => 'new.teacher@example.test',
            'phone' => '01799999999',
        ]);
    }

    public function test_update_rejects_duplicate_user_email(): void
    {
        $teacher = $this->makeTeacher();
        $other = User::factory()->create([
            'branch_id' => $this->branch->id,
            'email' => 'taken@school.com',
        ]);

        $this->withToken($this->tokenForRole('admin'))
            ->putJson("/api/v1/teachers/{$teacher->public_id}", [
                'name' => 'New Name',
                'email' => $other->email,
                'phone' => '01799999999',
                'designation' => 'Head Teacher',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_status_flip_to_inactive_blocks_login_and_revokes_tokens(): void
    {
        $teacher = $this->makeTeacher();
        $teacherToken = $teacher->user->createToken('web')->plainTextToken;

        $this->withToken($this->tokenForRole('admin'))
            ->patchJson("/api/v1/teachers/{$teacher->id}/status", ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('users', ['id' => $teacher->user_id, 'is_active' => false]);
        $this->app['auth']->forgetGuards();

        // Existing token revoked.
        $this->withToken($teacherToken)->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->app['auth']->forgetGuards();

        // Fresh login refused (1.1 inactive rule → 403).
        $this->postJson('/api/v1/auth/login', [
            'login' => $teacher->user->email,
            'password' => 'original-password',
            'device_name' => 'web',
        ])->assertStatus(403);
    }

    public function test_photo_upload_then_replacement(): void
    {
        Storage::fake('public');
        $teacher = $this->makeTeacher();
        $token = $this->tokenForRole('admin');

        $first = $this->withToken($token)
            ->postJson("/api/v1/teachers/{$teacher->id}/photo", [
                'photo' => UploadedFile::fake()->image('first.jpg'),
            ])
            ->assertOk();
        $firstUrl = $first->json('data.photo_url');
        $this->assertNotNull($firstUrl);
        $this->assertSame(1, $teacher->fresh()->getMedia('photo')->count());

        // Replacement keeps a single file.
        $this->withToken($token)
            ->postJson("/api/v1/teachers/{$teacher->id}/photo", [
                'photo' => UploadedFile::fake()->image('second.png'),
            ])
            ->assertOk();
        $this->assertSame(1, $teacher->fresh()->getMedia('photo')->count());
    }

    public function test_photo_rejects_wrong_type_and_oversize(): void
    {
        Storage::fake('public');
        $teacher = $this->makeTeacher();
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->postJson("/api/v1/teachers/{$teacher->id}/photo", [
                'photo' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);

        $this->withToken($token)
            ->postJson("/api/v1/teachers/{$teacher->id}/photo", [
                'photo' => UploadedFile::fake()->image('big.jpg')->size(3000),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_cross_branch_access_returns_404(): void
    {
        $other = Branch::factory()->create();
        $teacher = $this->makeTeacher([], $other);
        $token = $this->tokenForRole('admin');

        $this->withToken($token)->getJson("/api/v1/teachers/{$teacher->id}")->assertStatus(404);
        $this->withToken($token)->putJson("/api/v1/teachers/{$teacher->id}", [
            'name' => 'X', 'email' => 'x@example.test', 'phone' => '01711111111', 'designation' => 'Y',
        ])->assertStatus(404);
        $this->withToken($token)->patchJson("/api/v1/teachers/{$teacher->id}/status", ['status' => 'inactive'])->assertStatus(404);
    }

    public function test_index_requires_view_permission(): void
    {
        // accountant lacks teacher.view
        $this->withToken($this->tokenForRole('accountant'))
            ->getJson('/api/v1/teachers')
            ->assertStatus(403);
    }
}
