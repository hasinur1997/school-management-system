<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the verified reset-token fields used by the staged forgot-password
     * flow. Kept separate from the create migration so existing databases get
     * the columns without a migrate:fresh.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('password_reset_codes', 'reset_token_hash')) {
            Schema::table('password_reset_codes', function (Blueprint $table): void {
                $table->string('reset_token_hash')->nullable()->unique()->after('code_hash');
            });
        }

        if (! Schema::hasColumn('password_reset_codes', 'verified_at')) {
            Schema::table('password_reset_codes', function (Blueprint $table): void {
                $table->timestamp('verified_at')->nullable()->after('expires_at');
            });
        }

        if (! Schema::hasColumn('password_reset_codes', 'reset_token_expires_at')) {
            Schema::table('password_reset_codes', function (Blueprint $table): void {
                $table->timestamp('reset_token_expires_at')->nullable()->after('verified_at');
            });
        }
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            Schema::hasColumn('password_reset_codes', 'reset_token_hash') ? 'reset_token_hash' : null,
            Schema::hasColumn('password_reset_codes', 'verified_at') ? 'verified_at' : null,
            Schema::hasColumn('password_reset_codes', 'reset_token_expires_at') ? 'reset_token_expires_at' : null,
        ]));

        if ($columns !== []) {
            Schema::table('password_reset_codes', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
