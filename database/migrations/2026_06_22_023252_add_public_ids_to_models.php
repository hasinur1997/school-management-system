<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Tables backed by application Eloquent models that may be exposed via API.
     *
     * @var list<string>
     */
    private array $tables = [
        'users',
        'roles',
        'branches',
        'academic_sessions',
        'school_classes',
        'sections',
        'subjects',
        'teacher_assignments',
        'teachers',
        'admission_applications',
        'admission_previous_educations',
        'students',
        'parents',
        'enrollments',
        'student_attendances',
        'teacher_attendances',
        'checkin_ip_whitelists',
        'grading_scales',
        'exams',
        'marks',
        'exam_results',
        'annual_results',
        'promotions',
        'fee_structures',
        'invoices',
        'categories',
        'payments',
        'incomes',
        'expenses',
        'assets',
        'id_card_batches',
        'transfer_certificates',
        'settings',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasColumn($tableName, 'public_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->string('public_id', 26)->nullable()->after('id')->unique();
                });
            }

            DB::table($tableName)
                ->whereNull('public_id')
                ->orderBy('id')
                ->chunkById(500, function ($models) use ($tableName): void {
                    foreach ($models as $model) {
                        DB::table($tableName)
                            ->where('id', $model->id)
                            ->update(['public_id' => (string) Str::ulid()]);
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            if (! Schema::hasColumn($tableName, 'public_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropUnique(['public_id']);
                $table->dropColumn('public_id');
            });
        }
    }
};
