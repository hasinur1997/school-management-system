<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payments settle invoices. A counter (cash) payment is created and settled
     * in one shot (Task 10.3); an online (sslcommerz) payment is created pending
     * and settled by the IPN (10.5). receipt_no is set on success; transaction_id
     * is the gateway tran_id (unique, nullable for cash) used to keep IPN replays
     * idempotent.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->string('receipt_no', 30)->unique()->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);
            $table->string('status', 20)->default('pending');
            $table->string('transaction_id', 100)->unique()->nullable();
            $table->json('gateway_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
