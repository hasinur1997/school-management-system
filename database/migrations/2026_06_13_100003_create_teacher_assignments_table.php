<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            // FK to teachers is added in Task 2.1 — the teachers table does not exist yet.
            $table->unsignedBigInteger('teacher_id');
            $table->foreignId('session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->foreignId('class_id')->constrained('school_classes')->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('sections')->restrictOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->restrictOnDelete();
            $table->timestamps();

            // Defends the fully-populated tuple at the DB level. Rows with a
            // NULL section_id/subject_id are NOT deduplicated here (SQL treats
            // NULLs as distinct) — that case is enforced in the Form Requests.
            $table->unique(
                ['teacher_id', 'session_id', 'class_id', 'section_id', 'subject_id'],
                'teacher_assignments_unique',
            );
            $table->index('teacher_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_assignments');
    }
};
