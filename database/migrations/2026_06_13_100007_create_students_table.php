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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('application_id')->nullable()->unique()->constrained('admission_applications')->restrictOnDelete();
            $table->string('admission_no', 30)->unique();

            // Applicant name (bilingual) — mirrors the admission form
            $table->string('name_bn', 150);
            $table->string('name_en', 150);

            // Father
            $table->string('father_name_bn', 150);
            $table->string('father_name_en', 150);
            $table->string('father_nid', 20)->nullable();

            // Mother
            $table->string('mother_name_bn', 150);
            $table->string('mother_name_en', 150);
            $table->string('mother_nid', 20)->nullable();

            // Present address
            $table->string('present_village', 100);
            $table->string('present_post_office', 100);
            $table->string('present_upazila', 100);
            $table->string('present_district', 100);
            $table->string('present_division', 100);

            // Permanent address
            $table->string('permanent_village', 100);
            $table->string('permanent_post_office', 100);
            $table->string('permanent_upazila', 100);
            $table->string('permanent_district', 100);
            $table->string('permanent_division', 100);

            // Contact
            $table->string('father_mobile', 20);
            $table->string('mother_mobile', 20)->nullable();

            // Identity
            $table->string('birth_reg_no', 25)->unique();
            $table->date('date_of_birth');
            $table->string('religion', 50);
            $table->string('nationality', 50)->default('Bangladeshi');
            $table->string('caste', 50)->nullable();

            $table->string('status', 20)->default('active');
            $table->date('admitted_at');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
