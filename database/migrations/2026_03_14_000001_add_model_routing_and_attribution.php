<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->string('model_override')->nullable()->after('config');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->json('fallback_models')->nullable()->after('model');
            $table->string('routing_strategy')->default('default')->after('fallback_models');
        });

        Schema::table('execution_steps', function (Blueprint $table) {
            $table->string('model_used')->nullable()->after('error');
            $table->string('model_requested')->nullable()->after('model_used');
        });

        Schema::table('execution_runs', function (Blueprint $table) {
            $table->string('model_used')->nullable()->after('error');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn('model_override');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['fallback_models', 'routing_strategy']);
        });

        Schema::table('execution_steps', function (Blueprint $table) {
            $table->dropColumn(['model_used', 'model_requested']);
        });

        Schema::table('execution_runs', function (Blueprint $table) {
            $table->dropColumn('model_used');
        });
    }
};
