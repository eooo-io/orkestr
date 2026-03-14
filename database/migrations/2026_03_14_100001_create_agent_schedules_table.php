<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('agent_id');
            $table->string('name');
            $table->string('trigger_type'); // cron, webhook, event
            $table->string('cron_expression')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('webhook_token')->nullable()->unique();
            $table->string('webhook_secret')->nullable();
            $table->string('event_name')->nullable();
            $table->json('event_filters')->nullable();
            $table->json('input_template')->nullable();
            $table->json('execution_config')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->unsignedInteger('max_retries')->default(0);
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'is_enabled']);
            $table->index(['trigger_type', 'is_enabled']);
            $table->index(['next_run_at', 'is_enabled']);
            $table->index('event_name');

            if (config('database.default') !== 'sqlite') {
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('agent_id')->references('id')->on('agents')->cascadeOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            }
        });

        Schema::table('execution_runs', function (Blueprint $table) {
            $table->unsignedBigInteger('schedule_id')->nullable()->after('workflow_run_id');

            if (config('database.default') !== 'sqlite') {
                $table->foreign('schedule_id')->references('id')->on('agent_schedules')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('execution_runs', function (Blueprint $table) {
            if (config('database.default') !== 'sqlite') {
                $table->dropForeign(['schedule_id']);
            }
            $table->dropColumn('schedule_id');
        });

        Schema::dropIfExists('agent_schedules');
    }
};
