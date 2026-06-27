<?php

use App\Enums\ExamStatus;
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
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->foreignId('class_id')->constrained('school_classes')->restrictOnDelete();
            $table->string('type', 20);
            $table->string('name', 100);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default(ExamStatus::Upcoming->value);
            $table->timestamps();

            // One exam per (session, class, type). Superseded by the multi-class
            // model — see the convert_exams_to_multi_class migration, which moves
            // class_id into the exam_class pivot and adds the all_classes flag.
            $table->unique(['session_id', 'class_id', 'type']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
