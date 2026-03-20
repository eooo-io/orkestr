<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_processes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('stopped'); // starting, running, idle, stopping, stopped, crashed
            $table->string('pid')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->json('state')->nullable(); // persistent state between iterations
            $table->string('restart_policy', 20)->default('on_failure'); // always, on_failure, never
            $table->unsignedInteger('max_restarts')->default(5);
            $table->unsignedInteger('restart_count')->default(0);
            $table->json('wake_conditions')->nullable(); // events, schedules, webhooks
            $table->text('stop_reason')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'project_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_processes');
    }
};
