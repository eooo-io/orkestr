<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delegation_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('source_agent_id');
            $table->unsignedBigInteger('target_agent_id')->nullable();
            $table->unsignedBigInteger('target_a2a_agent_id')->nullable();
            $table->text('trigger_condition')->nullable();
            $table->boolean('pass_conversation_history')->default(true);
            $table->boolean('pass_agent_memory')->default(false);
            $table->boolean('pass_available_tools')->default(false);
            $table->json('custom_context')->nullable();
            $table->string('return_behavior')->default('report_back');
            $table->timestamps();

            // Foreign keys only on non-SQLite
            if (config('database.default') !== 'sqlite') {
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('source_agent_id')->references('id')->on('agents')->cascadeOnDelete();
                $table->foreign('target_agent_id')->references('id')->on('agents')->cascadeOnDelete();
            }

            $table->unique(['project_id', 'source_agent_id', 'target_agent_id'], 'deleg_project_source_target_agent');
            $table->unique(['project_id', 'source_agent_id', 'target_a2a_agent_id'], 'deleg_project_source_target_a2a');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegation_configs');
    }
};
