<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves exams from a single class to a set of classes. The legacy `class_id` is
 * backfilled into the `exam_class` pivot (created by the previous migration),
 * then the column, its foreign key, and the (session, class, type) unique index
 * are dropped and an `all_classes` flag is added. Data-preserving so it can run
 * on an existing database via `php artisan migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Backfill the pivot from the legacy single class_id (existing rows).
        if (Schema::hasColumn('exams', 'class_id')) {
            DB::table('exams')
                ->select('id', 'class_id')
                ->whereNotNull('class_id')
                ->orderBy('id')
                ->each(function (object $exam): void {
                    DB::table('exam_class')->insertOrIgnore([
                        'exam_id' => $exam->id,
                        'class_id' => $exam->class_id,
                    ]);
                });
        }

        if (! Schema::hasColumn('exams', 'all_classes')) {
            Schema::table('exams', function (Blueprint $table): void {
                $table->boolean('all_classes')->default(false)->after('type');
            });
        }

        if (! Schema::hasColumn('exams', 'class_id')) {
            return;
        }

        // Drop in dependency order, each guarded so the migration is safe on a
        // fresh database and on one left partway through by an earlier attempt.
        $hasForeignKey = collect(Schema::getForeignKeys('exams'))
            ->contains(fn (array $fk): bool => in_array('class_id', $fk['columns'], true));
        $indexes = collect(Schema::getIndexes('exams'));
        $hasUnique = $indexes->contains(fn (array $i): bool => $i['name'] === 'exams_session_id_class_id_type_unique');
        $hasClassIndex = $indexes->contains(fn (array $i): bool => $i['name'] === 'exams_class_id_foreign');
        $hasSessionIndex = $indexes->contains(fn (array $i): bool => $i['name'] === 'exams_session_id_index');

        // The composite unique is the only index covering session_id, so the
        // session_id foreign key relies on it (MySQL). Give that FK a standalone
        // index before the composite is dropped.
        if (! $hasSessionIndex) {
            Schema::table('exams', function (Blueprint $table): void {
                $table->index('session_id');
            });
        }

        Schema::table('exams', function (Blueprint $table) use ($hasForeignKey, $hasUnique, $hasClassIndex): void {
            // The foreign key must go before its supporting index (MySQL).
            if ($hasForeignKey) {
                $table->dropForeign(['class_id']);
            }
            if ($hasUnique) {
                $table->dropUnique('exams_session_id_class_id_type_unique');
            }
            if ($hasClassIndex) {
                $table->dropIndex('exams_class_id_foreign');
            }
            $table->dropColumn('class_id');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table): void {
            $table->foreignId('class_id')->nullable()->after('session_id')
                ->constrained('school_classes')->restrictOnDelete();
            $table->dropColumn('all_classes');
        });

        // Restore the single class_id from the first pivot row, then the index.
        DB::table('exam_class')
            ->orderBy('exam_id')
            ->get()
            ->groupBy('exam_id')
            ->each(function ($rows, $examId): void {
                DB::table('exams')->where('id', $examId)->update([
                    'class_id' => $rows->first()->class_id,
                ]);
            });

        Schema::table('exams', function (Blueprint $table): void {
            $table->unique(['session_id', 'class_id', 'type']);
        });
    }
};
