<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_gates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('execution_run_id')->nullable();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // artifact_review, action_confirm, budget_increase, escalation
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('context')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, expired, auto_approved
            $table->timestamp('requested_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('responded_by')->nullable();
            $table->text('response_note')->nullable();
            $table->unsignedInteger('auto_approve_after_minutes')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['status']);

            $table->foreign('execution_run_id')->references('id')->on('execution_runs')->nullOnDelete();
            $table->foreign('responded_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_gates');
    }
};
