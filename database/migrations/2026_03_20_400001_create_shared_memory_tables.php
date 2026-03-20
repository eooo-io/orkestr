<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_memory_pools', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->enum('access_policy', ['open', 'explicit', 'role_based'])->default('explicit');
            $table->unsignedInteger('retention_days')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'slug']);
        });

        Schema::create('shared_memory_pool_agent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shared_memory_pool_id')->constrained('shared_memory_pools')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->enum('access_mode', ['read', 'write', 'admin'])->default('write');
            $table->timestamp('created_at')->nullable();

            $table->unique(['shared_memory_pool_id', 'agent_id'], 'smp_agent_unique');
        });

        Schema::create('shared_memory_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('pool_id')->constrained('shared_memory_pools')->cascadeOnDelete();
            $table->foreignId('contributed_by_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('key');
            $table->json('content');
            $table->json('tags')->nullable();
            $table->decimal('confidence', 3, 2)->default(0.80);
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['pool_id', 'key']);
            $table->index(['pool_id', 'contributed_by_agent_id']);
        });

        // Add vector column via raw SQL for pgvector (skip on SQLite)
        if (! app()->runningUnitTests() && config('database.default') !== 'sqlite') {
            try {
                DB::statement('ALTER TABLE shared_memory_entries ADD COLUMN embedding vector(1536)');
            } catch (\Throwable $e) {
                // pgvector not available — column will be NULL / unused
            }
        }

        Schema::create('knowledge_graph_nodes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('pool_id')->nullable()->constrained('shared_memory_pools')->nullOnDelete();
            $table->string('entity_type');
            $table->string('entity_name');
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'entity_type']);
        });

        // Add vector column for knowledge graph nodes
        if (! app()->runningUnitTests() && config('database.default') !== 'sqlite') {
            try {
                DB::statement('ALTER TABLE knowledge_graph_nodes ADD COLUMN embedding vector(1536)');
            } catch (\Throwable $e) {
                // pgvector not available
            }
        }

        Schema::create('knowledge_graph_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_node_id')->constrained('knowledge_graph_nodes')->cascadeOnDelete();
            $table->foreignId('target_node_id')->constrained('knowledge_graph_nodes')->cascadeOnDelete();
            $table->string('relationship');
            $table->json('properties')->nullable();
            $table->decimal('weight', 5, 4)->default(1.0);
            $table->timestamp('created_at')->nullable();

            $table->index('source_node_id');
            $table->index('target_node_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_graph_edges');
        Schema::dropIfExists('knowledge_graph_nodes');
        Schema::dropIfExists('shared_memory_entries');
        Schema::dropIfExists('shared_memory_pool_agent');
        Schema::dropIfExists('shared_memory_pools');
    }
};
