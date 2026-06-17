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
        Schema::create('transfer_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            // One TC per student — the DB unique index is the final guard behind
            // the service's 409 check.
            $table->foreignId('student_id')->unique()->constrained('students')->restrictOnDelete();
            // Per-branch sequence: TC-{branchCode}-{seq}.
            $table->string('tc_no', 30)->unique();
            $table->string('reason', 255);
            $table->date('issue_date');
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_certificates');
    }
};
