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
        Schema::create('daily_briefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->date('brief_date');
            $table->string('trigger', 20);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('generation_status', 20);
            $table->string('generation_error')->nullable();
            $table->json('stats')->nullable();
            $table->json('content')->nullable();
            $table->foreignId('ai_usage_log_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'brief_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_briefs');
    }
};
