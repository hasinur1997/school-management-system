<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Income ledger. A successful fee payment auto-creates exactly one income row
     * linked back to the payment (payment_id unique → system-generated fee income,
     * not editable by Phase 11's CRUD). Schema only for Task 10.3; the CRUD
     * endpoints arrive in Task 11.2.
     */
    public function up(): void
    {
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->unique()->constrained('payments')->cascadeOnDelete();
            $table->string('title', 150);
            $table->decimal('amount', 12, 2);
            $table->date('date')->index();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
