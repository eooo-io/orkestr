<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_agent', function (Blueprint $table) {
            $table->text('objective_override')->nullable();
            $table->json('success_criteria_override')->nullable();
            $table->unsignedInteger('max_iterations_override')->nullable();
            $table->unsignedInteger('timeout_override')->nullable();
            $table->string('model_override')->nullable();
            $table->decimal('temperature_override', 3, 2)->nullable();
            $table->string('context_strategy_override')->nullable();
            $table->string('planning_mode_override')->nullable();
            $table->json('custom_tools_override')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('project_agent', function (Blueprint $table) {
            $table->dropColumn([
                'objective_override',
                'success_criteria_override',
                'max_iterations_override',
                'timeout_override',
                'model_override',
                'temperature_override',
                'context_strategy_override',
                'planning_mode_override',
                'custom_tools_override',
            ]);
        });
    }
};
