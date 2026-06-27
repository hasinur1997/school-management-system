<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Admission applications are now soft-deleted: a delete
     * moves the row to the trash (deleted_at stamped) so it can be restored or
     * permanently removed later, rather than being lost outright.
     */
    public function up(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
