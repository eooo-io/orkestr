<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Knowledge tables live on the PostgreSQL 'knowledge' connection (pgvector).
     * Skipped gracefully when PostgreSQL is not available (e.g. SQLite test env).
     */
    public function up(): void
    {
        $driver = config('database.connections.knowledge.driver');

        // Only run on PostgreSQL — skip in SQLite test environments
        if ($driver !== 'pgsql') {
            return;
        }

        try {
            // Enable pgvector extension
            DB::connection('knowledge')->statement('CREATE EXTENSION IF NOT EXISTS vector');

            Schema::connection('knowledge')->create('agent_memories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('agent_id');
                $table->unsignedBigInteger('project_id');
                $table->string('key', 255);
                $table->text('content');
                $table->jsonb('metadata')->nullable();
                $table->timestamps();

                $table->index(['agent_id', 'project_id']);
            });

            // Add vector column via raw SQL (Laravel doesn't natively support the vector type)
            DB::connection('knowledge')->statement(
                'ALTER TABLE agent_memories ADD COLUMN embedding vector(1536)'
            );

            // IVFFlat index on embedding for approximate nearest neighbor search
            // Requires rows to exist for training; create index with small lists for dev
            DB::connection('knowledge')->statement(
                'CREATE INDEX agent_memories_embedding_idx ON agent_memories USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)'
            );

            Schema::connection('knowledge')->create('agent_knowledge', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('agent_id');
                $table->unsignedBigInteger('project_id');
                $table->string('namespace', 100);
                $table->string('key', 255);
                $table->jsonb('value');
                $table->timestamps();

                $table->index(['agent_id', 'project_id']);
            });

            // GIN index on the JSONB value column for fast containment queries
            DB::connection('knowledge')->statement(
                'CREATE INDEX agent_knowledge_value_gin ON agent_knowledge USING gin (value)'
            );
        } catch (\Exception $e) {
            // If PostgreSQL is unreachable, log and skip — don't block the rest of migrations
            logger()->warning('Knowledge DB migration skipped: '.$e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = config('database.connections.knowledge.driver');

        if ($driver !== 'pgsql') {
            return;
        }

        try {
            Schema::connection('knowledge')->dropIfExists('agent_knowledge');
            Schema::connection('knowledge')->dropIfExists('agent_memories');
        } catch (\Exception $e) {
            logger()->warning('Knowledge DB rollback skipped: '.$e->getMessage());
        }
    }
};
