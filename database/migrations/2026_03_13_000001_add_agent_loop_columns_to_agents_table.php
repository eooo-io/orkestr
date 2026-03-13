<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // Identity
            $table->longText('persona_prompt')->nullable();

            // Goal
            $table->text('objective_template')->nullable();
            $table->json('success_criteria')->nullable();
            $table->unsignedInteger('max_iterations')->nullable();
            $table->unsignedInteger('timeout_seconds')->nullable();

            // Perception
            $table->json('input_schema')->nullable();
            $table->json('memory_sources')->nullable();
            $table->string('context_strategy')->default('full');

            // Reasoning
            $table->string('planning_mode')->default('none');
            $table->decimal('temperature', 3, 2)->nullable();
            $table->longText('system_prompt')->nullable();

            // Observation
            $table->json('eval_criteria')->nullable();
            $table->json('output_schema')->nullable();
            $table->string('loop_condition')->default('goal_met');

            // Orchestration
            $table->unsignedBigInteger('parent_agent_id')->nullable();
            $table->json('delegation_rules')->nullable();
            $table->boolean('can_delegate')->default(false);

            // Actions
            $table->json('custom_tools')->nullable();

            // Meta
            $table->boolean('is_template')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
        });

        // Add foreign keys only on non-SQLite databases
        if (config('database.default') !== 'sqlite') {
            Schema::table('agents', function (Blueprint $table) {
                $table->foreign('parent_agent_id')->references('id')->on('agents')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (config('database.default') !== 'sqlite') {
            Schema::table('agents', function (Blueprint $table) {
                $table->dropForeign(['parent_agent_id']);
                $table->dropForeign(['created_by']);
            });
        }

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn([
                'persona_prompt',
                'objective_template',
                'success_criteria',
                'max_iterations',
                'timeout_seconds',
                'input_schema',
                'memory_sources',
                'context_strategy',
                'planning_mode',
                'temperature',
                'system_prompt',
                'eval_criteria',
                'output_schema',
                'loop_condition',
                'parent_agent_id',
                'delegation_rules',
                'can_delegate',
                'custom_tools',
                'is_template',
                'created_by',
            ]);
        });
    }
};
