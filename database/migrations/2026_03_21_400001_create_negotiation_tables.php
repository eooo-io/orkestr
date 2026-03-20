<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_bids', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('task_id')->nullable()->constrained('agent_tasks')->nullOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->decimal('bid_score', 5, 2);
            $table->unsignedInteger('estimated_duration_ms');
            $table->unsignedInteger('estimated_cost_microcents');
            $table->decimal('confidence', 3, 2);
            $table->text('reasoning')->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'expired', 'withdrawn'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['task_id', 'status']);
        });

        Schema::create('capability_advertisements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->json('capabilities');
            $table->enum('availability_status', ['available', 'busy', 'offline'])->default('available');
            $table->unsignedInteger('max_concurrent_tasks')->default(3);
            $table->unsignedInteger('current_load')->default(0);
            $table->timestamp('advertised_at');
            $table->timestamp('expires_at');

            $table->index(['agent_id', 'project_id']);
        });

        Schema::create('team_formations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->text('objective');
            $table->enum('formation_strategy', ['capability_match', 'cost_optimized', 'speed_optimized'])->default('capability_match');
            $table->json('agent_ids');
            $table->enum('status', ['forming', 'active', 'disbanded'])->default('forming');
            $table->foreignId('formed_by_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('formed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('performance_score', 5, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('disbanded_at')->nullable();

            $table->index(['project_id', 'status']);
        });

        Schema::create('negotiation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedBigInteger('team_formation_id')->nullable();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->string('action');
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('negotiation_logs');
        Schema::dropIfExists('team_formations');
        Schema::dropIfExists('capability_advertisements');
        Schema::dropIfExists('task_bids');
    }
};
