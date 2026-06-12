<?php

namespace Tests\Feature\Api\V1;

use App\Models\AcademicSession;
use App\Models\User;
use Database\Seeders\AcademicSessionSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SessionCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    /**
     * Create an API token for a user with the given role.
     */
    private function tokenForRole(string $role): string
    {
        $user = User::factory()->create()->assignRole($role);

        return $user->createToken('web')->plainTextToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
            ...$overrides,
        ];
    }

    public function test_create_as_current_unsets_previous_current(): void
    {
        $token = $this->tokenForRole('admin');
        $previous = AcademicSession::factory()->current()->create(['name' => '2025']);

        $this->withToken($token)
            ->postJson('/api/v1/sessions', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Session created')
            ->assertJsonPath('data.name', '2026')
            ->assertJsonPath('data.start_date', '2026-01-01')
            ->assertJsonPath('data.end_date', '2026-12-31')
            ->assertJsonPath('data.is_current', true);

        $this->assertDatabaseHas('academic_sessions', ['name' => '2026', 'is_current' => true]);
        $this->assertDatabaseHas('academic_sessions', ['name' => '2025', 'is_current' => false]);
        $this->assertSame(1, AcademicSession::where('is_current', true)->count());
        $this->assertFalse($previous->refresh()->is_current);
    }

    public function test_first_session_becomes_current_even_without_flag(): void
    {
        $token = $this->tokenForRole('admin');

        $payload = $this->validPayload();
        unset($payload['is_current']);

        $this->withToken($token)
            ->postJson('/api/v1/sessions', $payload)
            ->assertCreated()
            ->assertJsonPath('data.is_current', true);

        $this->assertSame(1, AcademicSession::where('is_current', true)->count());
    }

    public function test_creating_first_session_explicitly_not_current_is_rejected(): void
    {
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->postJson('/api/v1/sessions', $this->validPayload(['is_current' => false]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_current']);

        $this->assertSame(0, AcademicSession::count());
    }

    public function test_cannot_unset_the_only_current_session(): void
    {
        $token = $this->tokenForRole('admin');
        $session = AcademicSession::factory()->current()->create(['name' => '2026']);

        $this->withToken($token)
            ->putJson("/api/v1/sessions/{$session->id}", $this->validPayload(['is_current' => false]))
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['is_current']);

        $this->assertTrue($session->refresh()->is_current);
    }

    public function test_update_switching_current_unsets_the_other_session(): void
    {
        $token = $this->tokenForRole('admin');
        $current = AcademicSession::factory()->current()->create(['name' => '2025']);
        $next = AcademicSession::factory()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $this->withToken($token)
            ->putJson("/api/v1/sessions/{$next->id}", $this->validPayload())
            ->assertOk()
            ->assertJsonPath('message', 'Session updated')
            ->assertJsonPath('data.is_current', true);

        $this->assertFalse($current->refresh()->is_current);
        $this->assertTrue($next->refresh()->is_current);
        $this->assertSame(1, AcademicSession::where('is_current', true)->count());
    }

    public function test_date_order_and_duplicate_name_validation(): void
    {
        $token = $this->tokenForRole('admin');
        AcademicSession::factory()->current()->create(['name' => '2025']);

        $this->withToken($token)
            ->postJson('/api/v1/sessions', $this->validPayload([
                'start_date' => '2026-12-31',
                'end_date' => '2026-01-01',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);

        $this->withToken($token)
            ->postJson('/api/v1/sessions', $this->validPayload([
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-01',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);

        $this->withToken($token)
            ->postJson('/api/v1/sessions', $this->validPayload(['name' => '2025']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->withToken($token)
            ->postJson('/api/v1/sessions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'start_date', 'end_date']);
    }

    public function test_delete_session_in_use_returns_conflict(): void
    {
        $token = $this->tokenForRole('admin');
        AcademicSession::factory()->current()->create(['name' => '2026']);
        $referenced = AcademicSession::factory()->create(['name' => '2025']);

        // Referencing tables (enrollments, exams, fee_structures) arrive in
        // later phases; a synthetic restrict-FK table exercises the same path.
        Schema::create('session_refs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')
                ->constrained('academic_sessions')
                ->restrictOnDelete();
        });
        DB::table('session_refs')->insert(['academic_session_id' => $referenced->id]);

        $this->withToken($token)
            ->deleteJson("/api/v1/sessions/{$referenced->id}")
            ->assertStatus(409)
            ->assertExactJson([
                'success' => false,
                'message' => 'Session is in use and cannot be deleted',
            ]);

        $this->assertModelExists($referenced);
    }

    public function test_delete_unreferenced_session_succeeds_but_current_session_is_protected(): void
    {
        $token = $this->tokenForRole('admin');
        $current = AcademicSession::factory()->current()->create(['name' => '2026']);
        $old = AcademicSession::factory()->create(['name' => '2025']);

        $this->withToken($token)
            ->deleteJson("/api/v1/sessions/{$current->id}")
            ->assertUnprocessable()
            ->assertExactJson([
                'success' => false,
                'message' => 'One session must be current',
            ]);

        $this->assertModelExists($current);

        $this->withToken($token)
            ->deleteJson("/api/v1/sessions/{$old->id}")
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Session deleted',
                'data' => null,
            ]);

        $this->assertModelMissing($old);
    }

    public function test_list_returns_all_sessions_newest_first_without_pagination(): void
    {
        $token = $this->tokenForRole('admin');
        AcademicSession::factory()->create(['name' => '2024']);
        AcademicSession::factory()->create(['name' => '2025']);
        AcademicSession::factory()->current()->create(['name' => '2026']);

        $this->withToken($token)
            ->getJson('/api/v1/sessions')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', '2026')
            ->assertJsonPath('data.2.name', '2024')
            ->assertJsonMissingPath('meta');
    }

    public function test_teacher_is_denied_session_management(): void
    {
        $token = $this->tokenForRole('teacher');

        $this->withToken($token)
            ->getJson('/api/v1/sessions')
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'This action is unauthorized.',
            ]);
    }

    public function test_session_seeder_creates_current_2026_session_idempotently(): void
    {
        $this->seed(AcademicSessionSeeder::class);

        $session = AcademicSession::where('name', '2026')->firstOrFail();
        $this->assertTrue($session->is_current);

        $this->seed(AcademicSessionSeeder::class);

        $this->assertSame(1, AcademicSession::count());
        $this->assertSame(1, AcademicSession::where('is_current', true)->count());
    }
}
