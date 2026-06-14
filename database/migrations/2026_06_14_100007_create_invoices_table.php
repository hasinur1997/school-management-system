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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->restrictOnDelete();
            $table->string('invoice_no', 30)->unique();
            $table->unsignedTinyInteger('month');
            $table->year('year');
            // Amount is copied from the class fee structure at generation time —
            // a later fee edit never changes an existing invoice.
            $table->decimal('amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('status', 10)->default('unpaid');
            $table->date('due_date')->nullable();
            $table->timestamps();

            // Idempotent generation: one invoice per student per month — re-runs
            // skip rather than duplicate.
            $table->unique(['student_id', 'month', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
