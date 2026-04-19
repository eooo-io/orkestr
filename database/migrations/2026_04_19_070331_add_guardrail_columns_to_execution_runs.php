<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('execution_runs', function (Blueprint $table) {
            $table->string('halt_reason', 50)->nullable()->after('error');
            $table->unsignedBigInteger('halt_step_id')->nullable()->after('halt_reason');
            $table->unsignedInteger('token_budget')->nullable()->after('total_tokens');
            $table->unsignedInteger('cost_budget_microcents')->nullable()->after('total_cost_microcents');

            $table->index('halt_reason');
        });
    }

    public function down(): void
    {
        Schema::table('execution_runs', function (Blueprint $table) {
            $table->dropIndex(['halt_reason']);
            $table->dropColumn(['halt_reason', 'halt_step_id', 'token_budget', 'cost_budget_microcents']);
        });
    }
};
