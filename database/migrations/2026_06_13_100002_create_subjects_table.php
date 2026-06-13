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
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('school_classes')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('code', 20)->nullable();
            $table->unsignedSmallInteger('full_marks')->default(100);
            $table->unsignedSmallInteger('pass_marks')->default(33);
            $table->timestamps();

            $table->unique(['class_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
