<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->json('scopes')->nullable();
            $table->unsignedInteger('rate_limit_per_minute')->nullable();
            $table->unsignedInteger('rate_limit_per_hour')->nullable();
            $table->json('allowed_ips')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('agent_resource_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->unsignedBigInteger('max_tokens_per_day')->nullable();
            $table->decimal('max_cost_per_day', 10, 4)->nullable();
            $table->unsignedInteger('max_concurrent_executions')->default(3);
            $table->unsignedInteger('max_execution_duration_seconds')->default(3600);
            $table->unsignedInteger('max_mcp_connections')->default(10);
            $table->json('allowed_domains')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'project_id']);
        });

        Schema::create('agent_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->string('permission_type', 30); // tool, data_source, agent_delegate, mcp_server
            $table->string('permission_target', 255);
            $table->boolean('allowed')->default(true);
            $table->timestamps();

            $table->unique(['agent_id', 'permission_type', 'permission_target'], 'agent_perm_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_permissions');
        Schema::dropIfExists('agent_resource_quotas');
        Schema::dropIfExists('agent_identities');
    }
};
