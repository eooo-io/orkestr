<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_mcp_servers', function (Blueprint $table) {
            $table->string('approval_status')->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
        });

        Schema::table('project_a2a_agents', function (Blueprint $table) {
            $table->string('approval_status')->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
        });

        // Add foreign keys only on non-SQLite databases
        if (config('database.default') !== 'sqlite') {
            Schema::table('project_mcp_servers', function (Blueprint $table) {
                $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            });

            Schema::table('project_a2a_agents', function (Blueprint $table) {
                $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (config('database.default') !== 'sqlite') {
            Schema::table('project_mcp_servers', function (Blueprint $table) {
                $table->dropForeign(['approved_by']);
            });

            Schema::table('project_a2a_agents', function (Blueprint $table) {
                $table->dropForeign(['approved_by']);
            });
        }

        Schema::table('project_mcp_servers', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'approved_at', 'approved_by']);
        });

        Schema::table('project_a2a_agents', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'approved_at', 'approved_by']);
        });
    }
};
