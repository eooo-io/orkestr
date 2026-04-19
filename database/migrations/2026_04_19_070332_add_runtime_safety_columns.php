<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->unsignedInteger('max_agent_turns_per_run')->default(40)->after('name');
            $table->unsignedInteger('default_run_token_budget')->nullable()->after('max_agent_turns_per_run');
            $table->decimal('default_run_cost_budget_usd', 10, 4)->nullable()->after('default_run_token_budget');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->unsignedInteger('run_token_budget')->nullable()->after('max_iterations');
            $table->decimal('run_cost_budget_usd', 10, 4)->nullable()->after('run_token_budget');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['run_token_budget', 'run_cost_budget_usd']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['max_agent_turns_per_run', 'default_run_token_budget', 'default_run_cost_budget_usd']);
        });
    }
};
