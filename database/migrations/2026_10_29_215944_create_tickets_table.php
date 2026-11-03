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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('source', 20);
            $table->string('status', 20);
            $table->string('requester_name', 120);
            $table->string('requester_email');
            $table->string('subject', 200);
            $table->text('body');
            $table->foreignId('category_id')->nullable()->constrained('ticket_categories')->nullOnDelete();
            $table->string('priority', 20)->nullable();
            $table->string('sentiment', 20)->nullable();
            $table->decimal('sentiment_score', 3, 2)->nullable();
            $table->string('language', 10)->nullable();
            $table->string('language_name', 40)->nullable();
            $table->string('summary', 200)->nullable();
            $table->string('triage_status', 20);
            $table->string('triage_error')->nullable();
            $table->string('draft_status', 20);
            $table->string('draft_error')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'number']);
            $table->index(['workspace_id', 'status', 'last_activity_at']);
            $table->index(['workspace_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
