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
            CategorySeeder::class,
        ]);

        // Idempotent so the whole seed can be re-run without colliding on the
        // unique email.
        $superAdmin = User::firstOrCreate(
            ['email' => 'hasinur@gmail.com'],
            [
                'name' => 'Hasinur Rahman',
                'phone' => '01700000000',
                'password' => bcrypt('password'),
            ],
        );
        $superAdmin->assignRole('super_admin');

        $this->call(DemoSeeder::class);
    }
}
