<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_memories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('agent_id');
            $table->unsignedBigInteger('project_id');
            $table->string('type'); // conversation, working, long_term
            $table->string('key')->nullable();
            $table->json('content');
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'project_id', 'type']);
            $table->index(['type', 'key']);

            if (config('database.default') !== 'sqlite') {
                $table->foreign('agent_id')->references('id')->on('agents')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            }
        });

        Schema::create('agent_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('agent_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('execution_run_id')->nullable();
            $table->json('messages');
            $table->text('summary')->nullable();
            $table->unsignedInteger('token_count')->default(0);
            $table->timestamps();

            $table->index(['agent_id', 'project_id']);

            if (config('database.default') !== 'sqlite') {
                $table->foreign('agent_id')->references('id')->on('agents')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('execution_run_id')->references('id')->on('execution_runs')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_conversations');
        Schema::dropIfExists('agent_memories');
    }
};
