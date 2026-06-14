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
        Schema::create('marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->restrictOnDelete();
            // The 1.6-anticipated restrict FK to subjects: a subject in use by
            // marks cannot be deleted (delete-in-use → 409).
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->decimal('obtained_marks', 5, 2);
            // Grade + grade point are snapshotted from the grading scale at entry
            // so later scale edits never alter stored marks.
            $table->string('grade', 5);
            $table->decimal('grade_point', 3, 2);
            $table->foreignId('entered_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // One mark per student per subject per exam; re-entry updates the row.
            $table->unique(['exam_id', 'enrollment_id', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marks');
    }
};
