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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            // The closed enrollment the student moved out of.
            $table->foreignId('from_enrollment_id')->constrained('enrollments')->restrictOnDelete();
            // The new enrollment; null when the student was held back (failed).
            $table->foreignId('to_enrollment_id')->nullable()->constrained('enrollments')->restrictOnDelete();
            // bulk | individual (PromotionType).
            $table->string('type', 10);
            $table->foreignId('promoted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('promoted_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
