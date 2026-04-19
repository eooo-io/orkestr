<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('execution_runs', function (Blueprint $table) {
            // varchar (not native enum) so swapping values later doesn't require a migration
            $table->string('visibility', 16)->default('private')->after('status');
            $table->foreignId('forked_from_run_id')
                ->nullable()
                ->after('visibility')
                ->constrained('execution_runs')
                ->nullOnDelete();

            $table->index(['visibility', 'created_at']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('default_run_visibility', 16)->default('private')->after('default_run_cost_budget_usd');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('default_run_visibility');
        });

        Schema::table('execution_runs', function (Blueprint $table) {
            $table->dropIndex(['visibility', 'created_at']);
            $table->dropConstrainedForeignId('forked_from_run_id');
            $table->dropColumn('visibility');
        });
    }
};
