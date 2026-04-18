<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skill_eval_runs', function (Blueprint $table) {
            $table->foreignId('skill_version_id')
                ->nullable()
                ->after('eval_suite_id')
                ->constrained('skill_versions')
                ->nullOnDelete();

            $table->foreignId('baseline_run_id')
                ->nullable()
                ->after('skill_version_id')
                ->constrained('skill_eval_runs')
                ->nullOnDelete();

            $table->decimal('delta_score', 5, 2)->nullable()->after('overall_score');

            $table->index(['skill_version_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('skill_eval_runs', function (Blueprint $table) {
            $table->dropIndex(['skill_version_id', 'status']);
            $table->dropConstrainedForeignId('baseline_run_id');
            $table->dropConstrainedForeignId('skill_version_id');
            $table->dropColumn('delta_score');
        });
    }
};
