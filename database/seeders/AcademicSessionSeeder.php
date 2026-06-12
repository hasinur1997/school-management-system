<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Services\SessionService;
use Illuminate\Database\Seeder;

class AcademicSessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $session = AcademicSession::updateOrCreate(
            ['name' => '2026'],
            ['start_date' => '2026-01-01', 'end_date' => '2026-12-31'],
        );

        app(SessionService::class)->setCurrent($session);
    }
}
