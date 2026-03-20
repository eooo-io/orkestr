<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('capability'); // e.g. 'code_review', 'security_audit'
            $table->decimal('proficiency', 3, 2)->default(0.50);
            $table->unsignedInteger('avg_duration_ms')->default(0);
            $table->unsignedInteger('avg_cost_microcents')->default(0);
            $table->decimal('success_rate', 3, 2)->default(0.50);
            $table->unsignedInteger('task_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['agent_id', 'project_id', 'capability']);
        });

        Schema::create('routing_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('conditions'); // task matching criteria
            $table->enum('target_strategy', [
                'best_fit',
                'round_robin',
                'least_loaded',
                'cost_optimized',
                'fastest',
            ])->default('best_fit');
            $table->json('target_agents')->nullable(); // explicit agent list
            $table->json('sla_config')->nullable(); // max_wait_seconds, max_cost, priority_boost
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['project_id', 'enabled', 'priority']);
        });

        Schema::create('routing_decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->foreignId('selected_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('strategy_used');
            $table->json('candidates'); // scored agent list
            $table->text('reasoning')->nullable();
            $table->boolean('sla_met')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index('task_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routing_decisions');
        Schema::dropIfExists('routing_rules');
        Schema::dropIfExists('agent_capabilities');
    }
};
