<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    // Settings are managed by super admins (same gate as the grading scale).
    private string $superToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create();
        $super = User::factory()->create(['branch_id' => null])->assignRole('super_admin');
        $this->superToken = $super->createToken('web')->plainTextToken;
    }

    public function test_upsert_returns_effective_settings_with_secrets_masked(): void
    {
        $response = $this->withToken($this->superToken)->putJson('/api/v1/settings', [
            'branch_id' => $this->branch->id,
            'settings' => [
                'school_name' => 'Haji Jabed Ali Memorial School',
                'sslcommerz_store_password' => 'super-secret',
                'partial_payment_enabled' => true,
                'teacher_late_threshold' => '09:15',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.global.school_name', 'Haji Jabed Ali Memorial School')
            ->assertJsonPath('data.global.sslcommerz_store_password.is_set', true)
            ->assertJsonPath('data.branch.partial_payment_enabled', true)
            ->assertJsonPath('data.branch.teacher_late_threshold', '09:15');

        // The raw secret value is never echoed back.
        $response->assertDontSee('super-secret');

        $this->assertDatabaseHas('settings', ['branch_id' => null, 'key' => 'school_name']);
        $this->assertDatabaseHas('settings', ['branch_id' => $this->branch->id, 'key' => 'partial_payment_enabled']);
    }

    public function test_unknown_key_is_rejected(): void
    {
        $this->withToken($this->superToken)->putJson('/api/v1/settings', [
            'settings' => ['foo' => 'bar'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.foo' => 'Unknown setting']);
    }

    public function test_type_mismatch_is_rejected(): void
    {
        $this->withToken($this->superToken)->putJson('/api/v1/settings', [
            'settings' => ['partial_payment_enabled' => 'yes'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.partial_payment_enabled' => 'Must be a boolean']);
    }

    public function test_invoice_due_day_out_of_range_is_rejected(): void
    {
        $this->withToken($this->superToken)->putJson('/api/v1/settings', [
            'branch_id' => $this->branch->id,
            'settings' => ['invoice_due_day' => 31],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings.invoice_due_day');
    }

    public function test_get_returns_masked_secrets(): void
    {
        Setting::create(['branch_id' => null, 'key' => 'school_name', 'value' => json_encode('My School')]);
        Setting::create(['branch_id' => null, 'key' => 'sslcommerz_store_password', 'value' => json_encode('hidden')]);

        $response = $this->withToken($this->superToken)->getJson('/api/v1/settings');

        $response->assertOk()
            ->assertJsonPath('data.global.school_name', 'My School')
            ->assertJsonPath('data.global.sslcommerz_store_password.is_set', true);

        $response->assertDontSee('hidden');
    }

    public function test_public_settings_expose_safe_subset_only(): void
    {
        Setting::create(['branch_id' => null, 'key' => 'school_name', 'value' => json_encode('Public School')]);
        Setting::create(['branch_id' => null, 'key' => 'school_logo', 'value' => json_encode('https://cdn.example/logo.png')]);
        Setting::create(['branch_id' => null, 'key' => 'sslcommerz_store_password', 'value' => json_encode('top-secret')]);
        Setting::create(['branch_id' => $this->branch->id, 'key' => 'sms_api_key', 'value' => json_encode('sms-secret')]);

        SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Class 1', 'is_active' => true]);
        SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Closed Class', 'is_active' => false]);

        $response = $this->getJson('/api/v1/public/settings');

        $response->assertOk()
            ->assertJsonPath('data.school_name', 'Public School')
            ->assertJsonPath('data.school_logo', 'https://cdn.example/logo.png')
            ->assertJsonPath('data.branches.0.id', $this->branch->id)
            ->assertJsonCount(1, 'data.branches.0.classes')
            ->assertJsonPath('data.branches.0.classes.0.name', 'Class 1');

        // Secrets never appear on the public surface — neither value nor key.
        $response->assertDontSee('top-secret');
        $response->assertDontSee('sms-secret');
        $response->assertDontSee('sslcommerz_store_password');
        $response->assertDontSee('sms_api_key');
    }

    public function test_public_settings_skips_inactive_branches(): void
    {
        $inactive = Branch::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/public/settings')->assertOk();

        $ids = array_column($response->json('data.branches'), 'id');
        $this->assertContains($this->branch->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_partial_toggle_flips_local_payment_validation_live(): void
    {
        $session = AcademicSession::factory()->current()->create();
        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $section = Section::factory()->create(['class_id' => $class->id]);
        $student = Student::factory()->create(['branch_id' => $this->branch->id]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => EnrollmentStatus::Active,
        ]);
        $invoice = Invoice::factory()->create([
            'branch_id' => $this->branch->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'month' => 6,
            'year' => 2026,
            'amount' => '1500.00',
            'paid_amount' => '0.00',
            'status' => InvoiceStatus::Unpaid,
        ]);

        // Staff (in-branch) collects payments; the super admin owns settings.
        $staffToken = User::factory()->create(['branch_id' => $this->branch->id])
            ->assignRole('admin')
            ->createToken('web')->plainTextToken;

        // Partial disabled by default: an under-payment is rejected.
        $this->withToken($staffToken)
            ->postJson("/api/v1/invoices/{$invoice->id}/payments/local", ['amount' => '600.00'])
            ->assertStatus(422)
            ->assertJsonPath('errors.amount.0', 'Full payment of 1500.00 required');

        $this->app['auth']->forgetGuards();

        // Flip the per-branch toggle on through the settings endpoint.
        $this->withToken($this->superToken)
            ->putJson('/api/v1/settings', [
                'branch_id' => $this->branch->id,
                'settings' => ['partial_payment_enabled' => true],
            ])
            ->assertOk()
            ->assertJsonPath('data.branch.partial_payment_enabled', true);

        $this->app['auth']->forgetGuards();

        // The same partial amount is now accepted — 10.3 reads the live DB value.
        $this->withToken($staffToken)
            ->postJson("/api/v1/invoices/{$invoice->id}/payments/local", ['amount' => '600.00'])
            ->assertCreated()
            ->assertJsonPath('data.invoice.status', 'partial');
    }

    public function test_super_admin_targets_branch_via_branch_id(): void
    {
        $this->withToken($this->superToken)->putJson('/api/v1/settings', [
            'branch_id' => $this->branch->id,
            'settings' => ['partial_payment_enabled' => true],
        ])
            ->assertOk()
            ->assertJsonPath('data.branch.partial_payment_enabled', true);

        $this->assertDatabaseHas('settings', [
            'branch_id' => $this->branch->id,
            'key' => 'partial_payment_enabled',
        ]);
    }

    public function test_branch_setting_without_branch_is_rejected_for_super_admin(): void
    {
        $this->withToken($this->superToken)->putJson('/api/v1/settings', [
            'settings' => ['partial_payment_enabled' => true],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.partial_payment_enabled' => 'A branch is required for this setting']);
    }

    public function test_branch_settings_are_isolated(): void
    {
        $otherBranch = Branch::factory()->create();
        Setting::create([
            'branch_id' => $otherBranch->id,
            'key' => 'teacher_late_threshold',
            'value' => json_encode('08:00'),
        ]);

        $this->withToken($this->superToken)->getJson("/api/v1/settings?branch_id={$this->branch->id}")
            ->assertOk()
            ->assertJsonPath('data.branch.teacher_late_threshold', null);
    }

    public function test_requires_setting_manage_permission(): void
    {
        $teacher = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('teacher');

        $this->withToken($teacher->createToken('web')->plainTextToken)
            ->getJson('/api/v1/settings')
            ->assertForbidden();
    }

    public function test_public_settings_need_no_auth(): void
    {
        $this->getJson('/api/v1/public/settings')->assertOk();
    }
}
