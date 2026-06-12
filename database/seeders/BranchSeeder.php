<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Branch::updateOrCreate(
            ['code' => 'MP'],
            ['name' => 'Madani PathShala', 'is_active' => true],
        );

        Branch::updateOrCreate(
            ['code' => 'JA'],
            ['name' => 'Jabed Ali', 'is_active' => true],
        );
    }
}
