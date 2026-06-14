<?php

namespace Database\Seeders;

use App\Models\GradingScale;
use Illuminate\Database\Seeder;

class GradingScaleSeeder extends Seeder
{
    /**
     * The Bangladesh-standard default grading scale (highest band first).
     *
     * @var list<array{grade: string, min_marks: int, max_marks: int, grade_point: float, is_fail: bool}>
     */
    public const DEFAULT_SCALE = [
        ['grade' => 'A+', 'min_marks' => 80, 'max_marks' => 100, 'grade_point' => 5.00, 'is_fail' => false],
        ['grade' => 'A', 'min_marks' => 70, 'max_marks' => 79, 'grade_point' => 4.00, 'is_fail' => false],
        ['grade' => 'A-', 'min_marks' => 60, 'max_marks' => 69, 'grade_point' => 3.50, 'is_fail' => false],
        ['grade' => 'B', 'min_marks' => 50, 'max_marks' => 59, 'grade_point' => 3.00, 'is_fail' => false],
        ['grade' => 'C', 'min_marks' => 40, 'max_marks' => 49, 'grade_point' => 2.00, 'is_fail' => false],
        ['grade' => 'D', 'min_marks' => 33, 'max_marks' => 39, 'grade_point' => 1.00, 'is_fail' => false],
        ['grade' => 'F', 'min_marks' => 0, 'max_marks' => 32, 'grade_point' => 0.00, 'is_fail' => true],
    ];

    /**
     * Seed the default grading scale (idempotent — replaces any existing rows).
     */
    public function run(): void
    {
        GradingScale::query()->delete();

        GradingScale::insert(array_map(
            fn (array $band): array => [...$band, 'created_at' => now(), 'updated_at' => now()],
            self::DEFAULT_SCALE,
        ));
    }
}
