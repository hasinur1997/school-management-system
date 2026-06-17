<?php

namespace Database\Factories;

use App\Enums\IdCardBatchStatus;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\IdCardBatch;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdCardBatch>
 */
class IdCardBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'class_id' => SchoolClass::factory(),
            'section_id' => null,
            'session_id' => AcademicSession::factory(),
            'status' => IdCardBatchStatus::Processing,
            'file_path' => null,
            'error' => null,
            'requested_by' => User::factory(),
        ];
    }

    /**
     * A finished batch with its merged PDF stored at the given path.
     */
    public function done(string $filePath): static
    {
        return $this->state(fn () => [
            'status' => IdCardBatchStatus::Done,
            'file_path' => $filePath,
        ]);
    }
}
