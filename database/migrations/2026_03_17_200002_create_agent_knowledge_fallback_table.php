<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fallback agent_knowledge table on the default connection.
 * Used when the PostgreSQL 'knowledge' connection is unavailable (e.g. SQLite tests, dev without PG).
 * The N.2 migration creates agent_knowledge on the 'knowledge' (pgsql) connection;
 * this ensures the table exists on the default DB for environments without pgvector.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Check if the knowledge connection is actually reachable.
        // The N.2 migration creates agent_knowledge on pgsql, but in SQLite test environments
        // or dev without PostgreSQL, we need a fallback table on the default connection.
        $knowledgeReachable = false;

        try {
            DB::connection('knowledge')->getPdo();
            $knowledgeReachable = true;
        } catch (\Exception) {
            // Knowledge DB not available
        }

        if ($knowledgeReachable) {
            return;
        }

        if (Schema::hasTable('agent_knowledge')) {
            return;
        }

        Schema::create('agent_knowledge', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id');
            $table->unsignedBigInteger('project_id');
            $table->string('namespace', 100);
            $table->string('key', 255);
            $table->json('value');
            $table->timestamps();

            $table->index(['agent_id', 'project_id']);
            $table->index(['namespace', 'key']);

            if (config('database.default') !== 'sqlite') {
                $table->foreign('agent_id')->references('id')->on('agents')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        $knowledgeReachable = false;

        try {
            DB::connection('knowledge')->getPdo();
            $knowledgeReachable = true;
        } catch (\Exception) {
            // Knowledge DB not available
        }

        if ($knowledgeReachable) {
            return;
        }

        Schema::dropIfExists('agent_knowledge');
    }
};
