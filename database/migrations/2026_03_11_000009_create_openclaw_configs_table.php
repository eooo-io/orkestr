<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // OpenClaw-specific config (SOUL, AGENTS, TOOLS)
        Schema::create('openclaw_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('soul_content')->nullable();
            $table->json('tools')->nullable();       // [{name, description, instructions, enabled, api_key_env, env, config}]
            $table->json('a2a_agents')->nullable();   // [{name, url, description, skills}]
            $table->timestamps();
        });

        // Project-level MCP server config (shared across all providers)
        Schema::create('project_mcp_servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('transport')->default('stdio'); // stdio | sse | streamable-http
            $table->string('command')->nullable();          // for stdio
            $table->json('args')->nullable();                // for stdio
            $table->string('url')->nullable();               // for sse/http
            $table->json('env')->nullable();                 // env vars to inject
            $table->json('headers')->nullable();             // for http transports
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['project_id', 'name']);
        });

        // Project-level A2A agent connections (shared across all providers)
        Schema::create('project_a2a_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->text('description')->nullable();
            $table->json('skills')->nullable();              // capabilities this agent exposes
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['project_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_a2a_agents');
        Schema::dropIfExists('project_mcp_servers');
        Schema::dropIfExists('openclaw_configs');
    }
};
