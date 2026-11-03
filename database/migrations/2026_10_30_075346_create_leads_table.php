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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_import_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20);
            $table->string('status', 20);
            $table->string('name', 120);
            $table->string('email');
            $table->string('company', 120)->nullable();
            $table->string('job_title', 120)->nullable();
            $table->string('company_size', 40)->nullable();
            $table->string('budget', 40)->nullable();
            $table->string('website')->nullable();
            $table->text('message')->nullable();
            $table->string('score', 10)->nullable();
            $table->text('score_reason')->nullable();
            $table->string('scoring_status', 20);
            $table->string('scoring_error')->nullable();
            $table->string('follow_up_status', 20);
            $table->string('follow_up_error')->nullable();
            $table->string('follow_up_subject', 200)->nullable();
            $table->text('follow_up_body')->nullable();
            $table->timestamp('follow_up_sent_at')->nullable();
            $table->foreignId('follow_up_sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['workspace_id', 'score']);
            $table->index(['workspace_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
