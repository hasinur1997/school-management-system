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
        Schema::create('exam_class', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('school_classes')->restrictOnDelete();

            // A class appears at most once per exam. Cross-exam overlap (the
            // same class in two exams of one session+type) is enforced in the
            // StoreExamRequest, since `all_classes` exams carry no pivot rows.
            $table->unique(['exam_id', 'class_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_class');
    }
};
