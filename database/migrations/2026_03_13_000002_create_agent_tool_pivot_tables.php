<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_mcp_server', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id');
            $table->unsignedBigInteger('project_mcp_server_id');
            $table->unsignedBigInteger('project_id');
            $table->json('config_overrides')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'project_mcp_server_id', 'project_id'], 'agent_mcp_unique');

            if (config('database.default') !== 'sqlite') {
                $table->foreign('agent_id')->references('id')->on('agents')->cascadeOnDelete();
                $table->foreign('project_mcp_server_id')->references('id')->on('project_mcp_servers')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            }
        });

        Schema::create('agent_a2a_agent', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id');
            $table->unsignedBigInteger('project_a2a_agent_id');
            $table->unsignedBigInteger('project_id');
            $table->json('config_overrides')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'project_a2a_agent_id', 'project_id'], 'agent_a2a_unique');

            if (config('database.default') !== 'sqlite') {
                $table->foreign('agent_id')->references('id')->on('agents')->cascadeOnDelete();
                $table->foreign('project_a2a_agent_id')->references('id')->on('project_a2a_agents')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_a2a_agent');
        Schema::dropIfExists('agent_mcp_server');
    }
};
