<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->string('tuned_for_model', 100)->nullable()->after('model');
            $table->string('last_validated_model', 100)->nullable()->after('tuned_for_model');
            $table->timestamp('last_validated_at')->nullable()->after('last_validated_model');
            $table->foreignId('last_validated_eval_run_id')
                ->nullable()
                ->after('last_validated_at')
                ->constrained('skill_eval_runs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_validated_eval_run_id');
            $table->dropColumn(['tuned_for_model', 'last_validated_model', 'last_validated_at']);
        });
    }
};
