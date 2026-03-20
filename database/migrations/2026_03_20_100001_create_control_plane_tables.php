<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('control_plane_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->json('context')->nullable(); // tracks active project/agent
            $table->timestamps();

            $table->index('user_id');
            $table->index('organization_id');
            $table->index('created_at');
        });

        Schema::create('control_plane_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('control_plane_sessions')->cascadeOnDelete();
            $table->string('role'); // user, assistant, system, tool_result
            $table->text('content');
            $table->json('tool_calls')->nullable();
            $table->json('metadata')->nullable(); // tokens, latency, actions taken
            $table->timestamp('created_at')->useCurrent();

            $table->index('session_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_plane_messages');
        Schema::dropIfExists('control_plane_sessions');
    }
};
