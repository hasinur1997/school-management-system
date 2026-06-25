<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional father/mother contact emails. Stored alongside the existing
 * father_mobile/mother_mobile contact columns on both the admission application
 * (captured on the public form) and the student profile (copied on approval and
 * editable in the office). Plain nullable strings — guardians are not always
 * user accounts, so these mirror the mobile columns rather than users.email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->string('father_email', 150)->nullable()->after('father_mobile');
            $table->string('mother_email', 150)->nullable()->after('mother_mobile');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('father_email', 150)->nullable()->after('father_mobile');
            $table->string('mother_email', 150)->nullable()->after('mother_mobile');
        });
    }

    public function down(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->dropColumn(['father_email', 'mother_email']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['father_email', 'mother_email']);
        });
    }
};
