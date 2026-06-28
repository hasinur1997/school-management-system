<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the absent flag to marks. An absent student is recorded as 0 obtained
     * marks (so result generation still sums cleanly and the grade resolves to
     * the fail band), with this flag distinguishing "marked absent" from "scored
     * zero" for display.
     */
    public function up(): void
    {
        Schema::table('marks', function (Blueprint $table) {
            $table->boolean('is_absent')->default(false)->after('obtained_marks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marks', function (Blueprint $table) {
            $table->dropColumn('is_absent');
        });
    }
};
