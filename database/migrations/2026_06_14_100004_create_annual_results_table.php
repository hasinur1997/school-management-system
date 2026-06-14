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
        Schema::create('annual_results', function (Blueprint $table) {
            $table->id();
            // One annual result per enrollment (per student per session+class).
            $table->foreignId('enrollment_id')->unique()->constrained('enrollments')->restrictOnDelete();
            // The three published per-exam GPAs the annual figure is weighted from.
            $table->decimal('first_semester_gpa', 3, 2);
            $table->decimal('second_semester_gpa', 3, 2);
            $table->decimal('final_exam_gpa', 3, 2);
            // Annual GPA = 0.25·S1 + 0.25·S2 + 0.50·Final (2 dp, half-up).
            $table->decimal('annual_gpa', 3, 2);
            $table->string('grade', 5);
            // false unless the final exam passed and the annual grade is not F.
            $table->boolean('is_passed');
            // Null until published; publication freezes the snapshot.
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('annual_results');
    }
};
