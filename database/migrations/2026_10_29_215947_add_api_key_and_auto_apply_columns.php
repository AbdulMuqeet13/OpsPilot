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
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('api_key_hash', 64)->nullable()->unique()->after('support_email');
            $table->string('api_key_last_four', 4)->nullable()->after('api_key_hash');
            $table->timestamp('api_key_created_at')->nullable()->after('api_key_last_four');
        });

        Schema::table('ai_feature_settings', function (Blueprint $table) {
            $table->boolean('auto_apply')->default(false)->after('is_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_feature_settings', function (Blueprint $table) {
            $table->dropColumn('auto_apply');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropUnique(['api_key_hash']);
            $table->dropColumn(['api_key_hash', 'api_key_last_four', 'api_key_created_at']);
        });
    }
};
