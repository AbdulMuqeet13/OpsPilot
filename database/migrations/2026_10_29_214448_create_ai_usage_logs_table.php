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
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->string('feature', 40);
            $table->string('provider', 40);
            $table->string('model', 100);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 12, 6)->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('status', 20);
            $table->string('failure_reason', 40)->nullable();
            $table->text('error')->nullable();
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index(['workspace_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
