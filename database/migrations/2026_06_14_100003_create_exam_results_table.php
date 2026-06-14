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
        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->restrictOnDelete();
            $table->decimal('total_marks', 7, 2);
            // GPA = average of subject grade points (avg of the marks' snapshots).
            $table->decimal('gpa', 3, 2);
            $table->string('grade', 5);
            // false if any subject grade is failing.
            $table->boolean('is_passed');
            // Null until the exam is published; publication freezes the snapshot.
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // One result row per student per exam; regenerate updates the row.
            $table->unique(['exam_id', 'enrollment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_results');
    }
};
