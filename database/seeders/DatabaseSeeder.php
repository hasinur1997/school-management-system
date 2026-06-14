<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            BranchSeeder::class,
            AcademicSessionSeeder::class,
            SchoolClassSeeder::class,
            GradingScaleSeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Hasinur Rahman',
            'email' => 'hasinur@gmail.com',
            'phone' => '01700000000',
            'password' => bcrypt('password'),
        ])->assignRole('super_admin');
    }
}
