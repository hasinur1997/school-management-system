<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Database\Seeder;

class SchoolClassSeeder extends Seeder
{
    /**
     * Seed classes 1–10 with a section "A" for the first branch. Safe to
     * re-run. Requires BranchSeeder to have run first.
     */
    public function run(): void
    {
        $branch = Branch::query()->orderBy('id')->firstOrFail();

        foreach (range(1, 10) as $level) {
            $class = SchoolClass::updateOrCreate(
                ['branch_id' => $branch->id, 'numeric_level' => $level],
                ['name' => "Class {$level}", 'is_active' => true],
            );

            Section::updateOrCreate(['class_id' => $class->id, 'name' => 'A']);
        }
    }
}
