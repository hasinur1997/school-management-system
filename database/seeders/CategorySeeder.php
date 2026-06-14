<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Sensible default categories, seeded per branch. Safe to re-run.
     *
     * @var array<string, list<string>>
     */
    public const DEFAULTS = [
        'income' => ['Tuition Fee', 'Donation'],
        'expense' => ['Salary', 'Utilities', 'Maintenance', 'Stationery'],
    ];

    /**
     * Seed the default income/expense categories for every branch. Requires
     * BranchSeeder to have run first.
     */
    public function run(): void
    {
        Branch::query()->each(function (Branch $branch): void {
            foreach (self::DEFAULTS as $type => $names) {
                foreach ($names as $name) {
                    Category::updateOrCreate([
                        'branch_id' => $branch->id,
                        'name' => $name,
                        'type' => $type,
                    ]);
                }
            }
        });
    }
}
