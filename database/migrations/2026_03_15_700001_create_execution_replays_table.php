<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_replays', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->string('name');
            $table->string('status')->default('running'); // running, completed, failed, cancelled
            $table->unsignedInteger('total_steps')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->unsignedInteger('total_cost_microcents')->default(0);
            $table->unsignedInteger('total_duration_ms')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['agent_id', 'created_at']);

            if (config('database.default') !== 'sqlite') {
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('agent_id')->references('id')->on('agents')->nullOnDelete();
            }
        });

        Schema::create('execution_replay_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('execution_replay_id');
            $table->unsignedInteger('step_number');
            $table->string('type'); // tool_call, llm_response, decision, observation, error
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('tokens_used')->nullable();
            $table->unsignedInteger('cost_microcents')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['execution_replay_id', 'step_number']);

            if (config('database.default') !== 'sqlite') {
                $table->foreign('execution_replay_id')->references('id')->on('execution_replays')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_replay_steps');
        Schema::dropIfExists('execution_replays');
    }
};
