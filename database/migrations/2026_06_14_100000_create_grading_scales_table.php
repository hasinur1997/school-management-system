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
        // Single global scale (no branch_id): one mapping of marks → grade
        // + grade point, shared across every branch and editable in settings.
        Schema::create('grading_scales', function (Blueprint $table) {
            $table->id();
            $table->string('grade', 5);
            $table->unsignedTinyInteger('min_marks');
            $table->unsignedTinyInteger('max_marks');
            $table->decimal('grade_point', 3, 2);
            $table->boolean('is_fail')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grading_scales');
    }
};
