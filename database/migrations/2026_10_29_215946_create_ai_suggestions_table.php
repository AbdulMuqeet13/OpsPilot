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
        Schema::create('ai_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_usage_log_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('subject');
            $table->string('feature', 40);
            $table->string('field', 30);
            $table->json('suggested_value');
            $table->json('final_value')->nullable();
            $table->string('decision', 20);
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'field', 'decision']);
            $table->index(['workspace_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_suggestions');
    }
};
