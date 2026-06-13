<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubjectCrudTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        Cache::flush();

        $this->branch = Branch::factory()->create();
        $this->class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
    }

    /**
     * Create an API token for a user with the given role. Super admins get
     * no branch (per schema); everyone else defaults to the test branch.
     */
    private function tokenForRole(string $role, ?Branch $branch = null): string
    {
        $user = User::factory()
            ->create(['branch_id' => $role === 'super_admin' ? null : ($branch ?? $this->branch)->id])
            ->assignRole($role);

        Sanctum::actingAs($user);

        return $user->createToken('web')->plainTextToken;
    }

    public function test_create_subject_happy_path_and_duplicate_name_rejected(): void
    {
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->postJson("/api/v1/classes/{$this->class->id}/subjects", [
                'name' => 'Mathematics',
                'code' => 'MATH',
                'full_marks' => 100,
                'pass_marks' => 33,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Subject created')
            ->assertJsonPath('data.class_id', $this->class->id)
            ->assertJsonPath('data.name', 'Mathematics')
            ->assertJsonPath('data.code', 'MATH')
            ->assertJsonPath('data.full_marks', 100)
            ->assertJsonPath('data.pass_marks', 33);

        $this->assertDatabaseHas('subjects', [
            'class_id' => $this->class->id,
            'name' => 'Mathematics',
        ]);

        $this->withToken($token)
            ->postJson("/api/v1/classes/{$this->class->id}/subjects", ['name' => 'Mathematics'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        // The same name is free in a different class.
        $other = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $this->withToken($token)
            ->postJson("/api/v1/classes/{$other->id}/subjects", ['name' => 'Mathematics'])
            ->assertCreated();
    }

    public function test_marks_default_to_100_and_33_when_omitted(): void
    {
        $this->withToken($this->tokenForRole('admin'))
            ->postJson("/api/v1/classes/{$this->class->id}/subjects", ['name' => 'Bangla'])
            ->assertCreated()
            ->assertJsonPath('data.code', null)
            ->assertJsonPath('data.full_marks', 100)
            ->assertJsonPath('data.pass_marks', 33);
    }

    public function test_pass_marks_must_be_less_than_full_marks(): void
    {
        $token = $this->tokenForRole('admin');

        $payloads = [
            ['name' => 'Science', 'full_marks' => 100, 'pass_marks' => 100],
            ['name' => 'Science', 'full_marks' => 50, 'pass_marks' => 60],
            // Defaults fill the omitted side: pass 150 vs default full 100,
            // and explicit full 20 vs default pass 33.
            ['name' => 'Science', 'pass_marks' => 150],
            ['name' => 'Science', 'full_marks' => 20],
        ];

        foreach ($payloads as $payload) {
            $this->withToken($token)
                ->postJson("/api/v1/classes/{$this->class->id}/subjects", $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['pass_marks']);
        }

        $this->assertSame(0, Subject::count());
    }

    public function test_student_can_read_subjects_but_cannot_write(): void
    {
        $subject = Subject::factory()->create(['class_id' => $this->class->id, 'name' => 'English']);

        $token = $this->tokenForRole('student');

        $this->withToken($token)
            ->getJson("/api/v1/classes/{$this->class->id}/subjects")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'English');

        $this->withToken($token)
            ->getJson("/api/v1/subjects/{$subject->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'English');

        $this->withToken($token)
            ->postJson("/api/v1/classes/{$this->class->id}/subjects", ['name' => 'Bangla'])
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'This action is unauthorized.',
            ]);

        $this->withToken($token)
            ->putJson("/api/v1/subjects/{$subject->id}", ['name' => 'Bangla'])
            ->assertForbidden();

        $this->withToken($token)
            ->deleteJson("/api/v1/subjects/{$subject->id}")
            ->assertForbidden();
    }

    public function test_subject_list_cache_is_invalidated_on_writes(): void
    {
        $token = $this->tokenForRole('admin');
        $subject = Subject::factory()->create(['class_id' => $this->class->id, 'name' => 'English']);

        // Prime the cache.
        $this->withToken($token)
            ->getJson("/api/v1/classes/{$this->class->id}/subjects")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'English');

        // Update must bust the cache: the next read reflects the change.
        $this->withToken($token)
            ->putJson("/api/v1/subjects/{$subject->id}", ['name' => 'English Literature'])
            ->assertOk()
            ->assertJsonPath('message', 'Subject updated');

        $this->withToken($token)
            ->getJson("/api/v1/classes/{$this->class->id}/subjects")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'English Literature');

        // Create and delete bust it too.
        $this->withToken($token)
            ->postJson("/api/v1/classes/{$this->class->id}/subjects", ['name' => 'Bangla'])
            ->assertCreated();

        $this->withToken($token)
            ->getJson("/api/v1/classes/{$this->class->id}/subjects")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withToken($token)
            ->deleteJson("/api/v1/subjects/{$subject->id}")
            ->assertOk();

        $this->withToken($token)
            ->getJson("/api/v1/classes/{$this->class->id}/subjects")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bangla');
    }

    public function test_update_subject_duplicate_name_and_partial_marks_rules(): void
    {
        $token = $this->tokenForRole('admin');
        $subject = Subject::factory()->create([
            'class_id' => $this->class->id,
            'name' => 'English',
            'full_marks' => 50,
            'pass_marks' => 17,
        ]);
        Subject::factory()->create(['class_id' => $this->class->id, 'name' => 'Bangla']);

        // Keeping its own name ignores itself.
        $this->withToken($token)
            ->putJson("/api/v1/subjects/{$subject->id}", ['name' => 'English'])
            ->assertOk()
            ->assertJsonPath('message', 'Subject updated');

        $this->withToken($token)
            ->putJson("/api/v1/subjects/{$subject->id}", ['name' => 'Bangla'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        // A partial update is validated against the stored full_marks (50).
        $this->withToken($token)
            ->putJson("/api/v1/subjects/{$subject->id}", ['name' => 'English', 'pass_marks' => 50])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pass_marks']);

        $this->withToken($token)
            ->putJson("/api/v1/subjects/{$subject->id}", ['name' => 'English', 'pass_marks' => 25])
            ->assertOk()
            ->assertJsonPath('data.full_marks', 50)
            ->assertJsonPath('data.pass_marks', 25);
    }

    public function test_route_bound_subjects_are_branch_scoped(): void
    {
        $otherBranch = Branch::factory()->create();
        $otherClass = SchoolClass::factory()->create(['branch_id' => $otherBranch->id]);
        $subject = Subject::factory()->create(['class_id' => $otherClass->id, 'name' => 'English']);

        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->getJson("/api/v1/classes/{$otherClass->id}/subjects")
            ->assertNotFound()
            ->assertJsonPath('message', 'Resource not found.');

        $this->withToken($token)
            ->postJson("/api/v1/classes/{$otherClass->id}/subjects", ['name' => 'Bangla'])
            ->assertNotFound();

        $this->withToken($token)
            ->getJson("/api/v1/subjects/{$subject->id}")
            ->assertNotFound();

        $this->withToken($token)
            ->putJson("/api/v1/subjects/{$subject->id}", ['name' => 'Bangla'])
            ->assertNotFound();

        $this->withToken($token)
            ->deleteJson("/api/v1/subjects/{$subject->id}")
            ->assertNotFound();

        $this->assertModelExists($subject);

        // Super admins see across branches.
        $this->withToken($this->tokenForRole('super_admin'))
            ->getJson("/api/v1/subjects/{$subject->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'English');
    }

    public function test_delete_subject_in_use_returns_conflict(): void
    {
        $token = $this->tokenForRole('admin');
        $subject = Subject::factory()->create(['class_id' => $this->class->id]);

        // The referencing table (marks) arrives in Task 7.3; a synthetic
        // restrict-FK table exercises the same path.
        Schema::create('subject_refs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')
                ->constrained('subjects')
                ->restrictOnDelete();
        });
        DB::table('subject_refs')->insert(['subject_id' => $subject->id]);

        $this->withToken($token)
            ->deleteJson("/api/v1/subjects/{$subject->id}")
            ->assertStatus(409)
            ->assertExactJson([
                'success' => false,
                'message' => 'Subject is in use and cannot be deleted',
            ]);

        $this->assertModelExists($subject);
    }

    public function test_class_with_subjects_is_restrict_protected(): void
    {
        $token = $this->tokenForRole('admin');
        $subject = Subject::factory()->create(['class_id' => $this->class->id]);

        // The subjects.class_id FK restricts at the DB level too.
        try {
            DB::table('school_classes')->where('id', $this->class->id)->delete();
            $this->fail('Expected a restrict-FK violation deleting a class with subjects.');
        } catch (QueryException) {
            // expected
        }

        $this->withToken($token)
            ->deleteJson("/api/v1/classes/{$this->class->id}")
            ->assertStatus(409)
            ->assertExactJson([
                'success' => false,
                'message' => 'Class is in use and cannot be deleted',
            ]);

        $this->assertModelExists($this->class);
        $this->assertModelExists($subject);
    }
}
