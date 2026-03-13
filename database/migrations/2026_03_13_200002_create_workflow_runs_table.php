<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workflow_id');
            $table->unsignedBigInteger('project_id');
            $table->string('status')->default('pending'); // pending, running, waiting_checkpoint, completed, failed, cancelled
            $table->json('input')->nullable();
            $table->json('context_snapshot')->nullable();
            $table->unsignedBigInteger('current_step_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'status']);
            $table->index(['project_id', 'created_at']);

            if (config('database.default') !== 'sqlite') {
                $table->foreign('workflow_id')->references('id')->on('workflows')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            }
        });

        Schema::create('workflow_run_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workflow_run_id');
            $table->unsignedBigInteger('workflow_step_id');
            $table->unsignedBigInteger('execution_run_id')->nullable(); // links to agent execution
            $table->string('status')->default('pending'); // pending, running, waiting_approval, completed, failed, skipped
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['workflow_run_id', 'status']);

            if (config('database.default') !== 'sqlite') {
                $table->foreign('workflow_run_id')->references('id')->on('workflow_runs')->cascadeOnDelete();
                $table->foreign('workflow_step_id')->references('id')->on('workflow_steps')->cascadeOnDelete();
                $table->foreign('execution_run_id')->references('id')->on('execution_runs')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_run_steps');
        Schema::dropIfExists('workflow_runs');
    }
};
