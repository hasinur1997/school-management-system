<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SchoolClassSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClassSectionCrudTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        Cache::flush();

        $this->branch = Branch::factory()->create();
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

    public function test_create_class_happy_path_and_duplicate_level_rejected(): void
    {
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->postJson('/api/v1/classes', ['name' => 'Class 7', 'numeric_level' => 7])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Class created')
            ->assertJsonPath('data.name', 'Class 7')
            ->assertJsonPath('data.numeric_level', 7)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.branch_id', $this->branch->id)
            ->assertJsonPath('data.sections', []);

        $this->assertDatabaseHas('school_classes', [
            'branch_id' => $this->branch->id,
            'numeric_level' => 7,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/classes', ['name' => 'Class Seven', 'numeric_level' => 7])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['numeric_level']);

        // The same level is free in a different branch.
        $otherToken = $this->tokenForRole('admin', Branch::factory()->create());
        $this->withToken($otherToken)
            ->postJson('/api/v1/classes', ['name' => 'Class 7', 'numeric_level' => 7])
            ->assertCreated();
    }

    public function test_numeric_level_must_be_between_1_and_12(): void
    {
        $token = $this->tokenForRole('admin');

        foreach ([0, 13] as $level) {
            $this->withToken($token)
                ->postJson('/api/v1/classes', ['name' => 'Class X', 'numeric_level' => $level])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['numeric_level']);
        }

        $this->assertSame(0, SchoolClass::count());
    }

    public function test_create_section_happy_path_and_duplicate_name_rejected(): void
    {
        $token = $this->tokenForRole('admin');
        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);

        $this->withToken($token)
            ->postJson("/api/v1/classes/{$class->id}/sections", ['name' => 'A'])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Section created')
            ->assertJsonPath('data.class_id', $class->id)
            ->assertJsonPath('data.name', 'A')
            ->assertJsonPath('data.class_teacher', null);

        $this->withToken($token)
            ->postJson("/api/v1/classes/{$class->id}/sections", ['name' => 'A'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        // The same name is free in a different class.
        $other = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $this->withToken($token)
            ->postJson("/api/v1/classes/{$other->id}/sections", ['name' => 'A'])
            ->assertCreated();
    }

    public function test_student_can_read_classes_but_cannot_write(): void
    {
        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        Section::factory()->create(['class_id' => $class->id, 'name' => 'A']);

        $token = $this->tokenForRole('student');

        $this->withToken($token)
            ->getJson('/api/v1/classes')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sections.0.name', 'A');

        $this->withToken($token)
            ->getJson("/api/v1/classes/{$class->id}/sections")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)
            ->postJson('/api/v1/classes', ['name' => 'Class 9', 'numeric_level' => 9])
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'This action is unauthorized.',
            ]);

        $this->withToken($token)
            ->postJson("/api/v1/classes/{$class->id}/sections", ['name' => 'B'])
            ->assertForbidden();

        $this->withToken($token)
            ->deleteJson("/api/v1/classes/{$class->id}")
            ->assertForbidden();
    }

    public function test_list_is_branch_scoped_active_only_and_ordered_by_level(): void
    {
        SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'numeric_level' => 3, 'name' => 'Class 3']);
        SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'numeric_level' => 1, 'name' => 'Class 1']);
        $two = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'numeric_level' => 2, 'name' => 'Class 2']);
        Section::factory()->create(['class_id' => $two->id, 'name' => 'B']);
        Section::factory()->create(['class_id' => $two->id, 'name' => 'A']);
        SchoolClass::factory()->inactive()->create(['branch_id' => $this->branch->id, 'numeric_level' => 4]);

        $otherBranch = Branch::factory()->create();
        SchoolClass::factory()->create(['branch_id' => $otherBranch->id, 'numeric_level' => 1]);

        $this->withToken($this->tokenForRole('admin'))
            ->getJson('/api/v1/classes')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.numeric_level', 1)
            ->assertJsonPath('data.1.numeric_level', 2)
            ->assertJsonPath('data.2.numeric_level', 3)
            ->assertJsonPath('data.1.sections.0.name', 'A')
            ->assertJsonPath('data.1.sections.1.name', 'B')
            ->assertJsonMissingPath('meta');

        // Super admin sees every branch and can narrow to one.
        $superToken = $this->tokenForRole('super_admin');

        $this->withToken($superToken)
            ->getJson('/api/v1/classes')
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $this->withToken($superToken)
            ->getJson("/api/v1/classes?branch_id={$otherBranch->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.branch_id', $otherBranch->id);
    }

    public function test_route_bound_classes_and_sections_are_branch_scoped(): void
    {
        $otherBranch = Branch::factory()->create();
        $class = SchoolClass::factory()->create(['branch_id' => $otherBranch->id, 'numeric_level' => 8]);
        $section = Section::factory()->create(['class_id' => $class->id, 'name' => 'A']);

        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->getJson("/api/v1/classes/{$class->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Resource not found.');

        $this->withToken($token)
            ->getJson("/api/v1/classes/{$class->id}/sections")
            ->assertNotFound();

        $this->withToken($token)
            ->postJson("/api/v1/classes/{$class->id}/sections", ['name' => 'B'])
            ->assertNotFound();

        $this->withToken($token)
            ->putJson("/api/v1/classes/{$class->id}", ['name' => 'Class Eight', 'numeric_level' => 8])
            ->assertNotFound();

        $this->withToken($token)
            ->getJson("/api/v1/sections/{$section->id}")
            ->assertNotFound();

        $this->withToken($token)
            ->putJson("/api/v1/sections/{$section->id}", ['name' => 'B'])
            ->assertNotFound();

        $this->withToken($token)
            ->deleteJson("/api/v1/sections/{$section->id}")
            ->assertNotFound();

        $this->assertModelExists($class);
        $this->assertModelExists($section);
    }

    public function test_class_list_cache_is_invalidated_on_writes(): void
    {
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->postJson('/api/v1/classes', ['name' => 'Class 1', 'numeric_level' => 1])
            ->assertCreated();

        $this->withToken($token)->getJson('/api/v1/classes')->assertOk()->assertJsonCount(1, 'data');

        // Writes after the list has been cached must bust the cache.
        $classId = $this->withToken($token)
            ->postJson('/api/v1/classes', ['name' => 'Class 2', 'numeric_level' => 2])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($token)
            ->getJson('/api/v1/classes')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withToken($token)
            ->postJson("/api/v1/classes/{$classId}/sections", ['name' => 'A'])
            ->assertCreated();

        $this->withToken($token)
            ->getJson('/api/v1/classes')
            ->assertOk()
            ->assertJsonPath('data.1.sections.0.name', 'A');
    }

    public function test_branch_id_is_super_admin_only_input(): void
    {
        $other = Branch::factory()->create();

        // Submitted branch_id is ignored for non-super-admins (Task 1.7):
        // the class is stamped with the caller's own branch.
        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/classes', ['name' => 'Class 4', 'numeric_level' => 4, 'branch_id' => $other->id])
            ->assertCreated()
            ->assertJsonPath('data.branch_id', $this->branch->id);

        $this->assertDatabaseMissing('school_classes', ['branch_id' => $other->id]);

        $superToken = $this->tokenForRole('super_admin');

        // Super admins have no branch of their own, so branch_id is required.
        $this->withToken($superToken)
            ->postJson('/api/v1/classes', ['name' => 'Class 5', 'numeric_level' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id']);

        $this->withToken($superToken)
            ->postJson('/api/v1/classes', ['name' => 'Class 5', 'numeric_level' => 5, 'branch_id' => $other->id])
            ->assertCreated()
            ->assertJsonPath('data.branch_id', $other->id);

        $this->assertDatabaseHas('school_classes', ['branch_id' => $other->id, 'numeric_level' => 5]);
    }

    public function test_update_class_and_duplicate_level_rules(): void
    {
        $token = $this->tokenForRole('admin');
        $class = SchoolClass::factory()->create([
            'branch_id' => $this->branch->id,
            'numeric_level' => 5,
            'name' => 'Class 5',
        ]);
        SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'numeric_level' => 6]);

        // Keeping its own level is fine; renaming and deactivating works.
        $this->withToken($token)
            ->putJson("/api/v1/classes/{$class->id}", ['name' => 'Class Five', 'numeric_level' => 5, 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('message', 'Class updated')
            ->assertJsonPath('data.name', 'Class Five')
            ->assertJsonPath('data.is_active', false);

        // Colliding with a sibling class's level is rejected.
        $this->withToken($token)
            ->putJson("/api/v1/classes/{$class->id}", ['name' => 'Class Five', 'numeric_level' => 6])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['numeric_level']);
    }

    public function test_update_section_duplicate_name_rejected(): void
    {
        $token = $this->tokenForRole('admin');
        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $sectionA = Section::factory()->create(['class_id' => $class->id, 'name' => 'A']);
        Section::factory()->create(['class_id' => $class->id, 'name' => 'B']);

        // Keeping its own name ignores itself.
        $this->withToken($token)
            ->putJson("/api/v1/sections/{$sectionA->id}", ['name' => 'A'])
            ->assertOk()
            ->assertJsonPath('message', 'Section updated');

        $this->withToken($token)
            ->putJson("/api/v1/sections/{$sectionA->id}", ['name' => 'B'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_delete_class_with_sections_returns_conflict(): void
    {
        $token = $this->tokenForRole('admin');
        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $section = Section::factory()->create(['class_id' => $class->id]);

        $this->withToken($token)
            ->deleteJson("/api/v1/classes/{$class->id}")
            ->assertStatus(409)
            ->assertExactJson([
                'success' => false,
                'message' => 'Class is in use and cannot be deleted',
            ]);

        $this->assertModelExists($class);

        // Once its section is gone the class can be deleted.
        $this->withToken($token)
            ->deleteJson("/api/v1/sections/{$section->id}")
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Section deleted',
                'data' => null,
            ]);

        $this->withToken($token)
            ->deleteJson("/api/v1/classes/{$class->id}")
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Class deleted',
                'data' => null,
            ]);

        $this->assertModelMissing($class);
    }

    public function test_delete_section_in_use_returns_conflict(): void
    {
        $token = $this->tokenForRole('admin');
        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $section = Section::factory()->create(['class_id' => $class->id]);

        // Referencing tables (enrollments, teacher_assignments) arrive in
        // later tasks; a synthetic restrict-FK table exercises the same path.
        Schema::create('section_refs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')
                ->constrained('sections')
                ->restrictOnDelete();
        });
        DB::table('section_refs')->insert(['section_id' => $section->id]);

        $this->withToken($token)
            ->deleteJson("/api/v1/sections/{$section->id}")
            ->assertStatus(409)
            ->assertExactJson([
                'success' => false,
                'message' => 'Section is in use and cannot be deleted',
            ]);

        $this->assertModelExists($section);
    }

    public function test_seeder_creates_classes_1_to_10_with_section_a_idempotently(): void
    {
        $this->seed(BranchSeeder::class);
        $this->seed(SchoolClassSeeder::class);
        $this->seed(SchoolClassSeeder::class);

        $firstBranch = Branch::query()->orderBy('id')->firstOrFail();

        $this->assertSame(10, SchoolClass::count());
        $this->assertSame(10, SchoolClass::where('branch_id', $firstBranch->id)->count());
        $this->assertSame(range(1, 10), SchoolClass::orderBy('numeric_level')->pluck('numeric_level')->all());
        $this->assertSame(10, Section::count());
        $this->assertSame(10, Section::where('name', 'A')->count());
    }
}
