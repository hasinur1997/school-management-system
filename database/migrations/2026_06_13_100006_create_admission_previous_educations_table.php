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
        Schema::create('admission_previous_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('admission_applications')->cascadeOnDelete();
            $table->string('exam_name', 100);
            $table->string('institution_name', 150);
            $table->decimal('gpa', 4, 2)->nullable();
            $table->year('passing_year')->nullable();
            $table->string('board_roll', 30)->nullable();
            $table->string('board_reg_no', 30)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_previous_educations');
    }
};
