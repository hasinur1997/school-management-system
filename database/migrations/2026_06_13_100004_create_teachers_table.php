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
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('email', 150)->unique();
            $table->string('phone', 20);
            $table->string('designation', 100);
            $table->date('joining_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        // The deferred FKs from Tasks 1.5 and 1.8: teachers now exists, so the
        // columns left unconstrained there can finally reference it.
        Schema::table('teacher_assignments', function (Blueprint $table) {
            $table->foreign('teacher_id')->references('id')->on('teachers')->restrictOnDelete();
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->foreign('class_teacher_id')->references('id')->on('teachers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropForeign(['class_teacher_id']);
        });

        Schema::table('teacher_assignments', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
        });

        Schema::dropIfExists('teachers');
    }
};
