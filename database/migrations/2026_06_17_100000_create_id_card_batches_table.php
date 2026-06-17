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
        Schema::create('id_card_batches', function (Blueprint $table) {
            // UUID primary key — the poll/download URLs expose this id, so an
            // opaque, non-enumerable identifier is preferable to an auto-int.
            $table->uuid('id')->primary();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('class_id')->constrained('school_classes')->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignId('session_id')->constrained('academic_sessions')->restrictOnDelete();
            // processing → done | failed. Stored as VARCHAR(20) per enum convention.
            $table->string('status', 20)->default('processing');
            // Disk-relative path of the merged PDF; null until the job finishes.
            $table->string('file_path')->nullable();
            // Short failure reason surfaced by the poll endpoint when failed.
            $table->string('error')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('created_at'); // pruning scans by age
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('id_card_batches');
    }
};
