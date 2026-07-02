<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sections are optional on enrollments: a class may have no sections, in which
 * case students enrol directly under the class. Roll uniqueness stays on the
 * (session, class, section, roll) index — null-section rows are validated
 * app-side (ApproveAdmissionRequest) since SQL unique indexes don't compare
 * NULLs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable(false)->change();
        });
    }
};
