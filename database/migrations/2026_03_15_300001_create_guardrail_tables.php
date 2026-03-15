<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guardrail policies — org-level defaults that cascade to projects/agents
        Schema::create('guardrail_policies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('scope')->default('organization'); // organization, project, agent
            $table->unsignedBigInteger('scope_id')->nullable(); // project_id or agent_id when scoped
            $table->json('budget_limits')->nullable(); // max_cost_usd, daily_limit_usd, max_tokens
            $table->json('tool_restrictions')->nullable(); // blocklist, allowlist
            $table->json('output_rules')->nullable(); // redact_pii, redact_secrets, max_output_length
            $table->json('access_rules')->nullable(); // external_apis, file_perms, project_scope
            $table->string('approval_level')->nullable(); // supervised, semi_autonomous, autonomous
            $table->integer('priority')->default(0); // higher priority overrides lower
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'scope', 'is_active']);

            if (config('database.default') !== 'sqlite') {
                $table->foreign('organization_id')
                    ->references('id')
                    ->on('organizations')
                    ->cascadeOnDelete();
            }
        });

        // Guardrail profiles — preset configurations (strict/moderate/permissive)
        Schema::create('guardrail_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false); // system presets can't be deleted
            $table->unsignedBigInteger('organization_id')->nullable(); // null for system presets
            $table->json('budget_limits')->nullable();
            $table->json('tool_restrictions')->nullable();
            $table->json('output_rules')->nullable();
            $table->json('access_rules')->nullable();
            $table->string('approval_level')->default('semi_autonomous');
            $table->json('input_sanitization')->nullable(); // sanitization rules
            $table->json('network_rules')->nullable(); // air-gap, allowed hosts
            $table->timestamps();

            if (config('database.default') !== 'sqlite') {
                $table->foreign('organization_id')
                    ->references('id')
                    ->on('organizations')
                    ->nullOnDelete();
            }
        });

        // Guardrail violations — audit trail for triggered guardrails
        Schema::create('guardrail_violations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->unsignedBigInteger('execution_run_id')->nullable();
            $table->string('guard_type'); // tool, budget, output, approval, data_access, input, network, delegation, endpoint
            $table->string('severity')->default('warning'); // info, warning, error, critical
            $table->string('rule_name'); // specific rule that triggered
            $table->text('message');
            $table->json('context')->nullable(); // tool_name, input, url, etc.
            $table->string('action_taken')->default('blocked'); // blocked, warned, logged
            $table->unsignedBigInteger('dismissed_by')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->text('dismissal_reason')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'guard_type', 'created_at']);
            $table->index(['project_id', 'created_at']);
            $table->index(['agent_id', 'created_at']);

            if (config('database.default') !== 'sqlite') {
                $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
                $table->foreign('agent_id')->references('id')->on('agents')->nullOnDelete();
                $table->foreign('dismissed_by')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardrail_violations');
        Schema::dropIfExists('guardrail_profiles');
        Schema::dropIfExists('guardrail_policies');
    }
};
