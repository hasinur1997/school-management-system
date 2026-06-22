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
        Schema::create('admission_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('application_no', 30)->unique();
            $table->foreignId('desired_class_id')->constrained('school_classes')->restrictOnDelete();

            // Item 1 — applicant name (bilingual)
            $table->string('name_bn', 150);
            $table->string('name_en', 150);

            // Items 2–3 — father
            $table->string('father_name_bn', 150);
            $table->string('father_name_en', 150);
            $table->string('father_nid', 20)->nullable();

            // Items 4–5 — mother
            $table->string('mother_name_bn', 150);
            $table->string('mother_name_en', 150);
            $table->string('mother_nid', 20)->nullable();

            // Item 6 — present address + father mobile
            $table->string('present_village', 100);
            $table->string('present_post_office', 100);
            $table->string('present_upazila', 100);
            $table->string('present_district', 100);
            $table->string('present_division', 100);
            $table->string('father_mobile', 20);

            // Item 7 — permanent address (bn) + mother mobile
            $table->string('permanent_village', 100);
            $table->string('permanent_post_office', 100);
            $table->string('permanent_upazila', 100);
            $table->string('permanent_district', 100);
            $table->string('permanent_division', 100);
            $table->string('mother_mobile', 20);

            // Items 9–11 — identity
            $table->string('birth_reg_no', 25)->unique();
            $table->date('date_of_birth');
            $table->string('religion', 50);
            $table->string('nationality', 50)->default('Bangladeshi');
            $table->string('caste', 50)->nullable();

            // Review lifecycle
            $table->string('status', 20)->default('pending');
            $table->string('rejection_reason', 255)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_applications');
    }
};
