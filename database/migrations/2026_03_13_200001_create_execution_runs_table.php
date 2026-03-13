<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('agent_id');
            $table->unsignedBigInteger('workflow_run_id')->nullable();
            $table->string('status')->default('pending'); // pending, running, completed, failed, cancelled
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->json('config')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('total_tokens')->default(0);
            $table->unsignedInteger('total_cost_microcents')->default(0); // cost in 1/10000 of a cent
            $table->unsignedInteger('total_duration_ms')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['agent_id', 'created_at']);

            if (config('database.default') !== 'sqlite') {
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('agent_id')->references('id')->on('agents')->cascadeOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            }
        });

        Schema::create('execution_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('execution_run_id');
            $table->unsignedInteger('step_number');
            $table->string('phase'); // perceive, reason, act, observe
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->json('tool_calls')->nullable();
            $table->json('token_usage')->nullable(); // {input_tokens, output_tokens, cache_read, cache_write}
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['execution_run_id', 'step_number']);

            if (config('database.default') !== 'sqlite') {
                $table->foreign('execution_run_id')->references('id')->on('execution_runs')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_steps');
        Schema::dropIfExists('execution_runs');
    }
};
