<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presence_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('resource_type'); // skill, agent, workflow
            $table->unsignedBigInteger('resource_id');
            $table->json('cursor_position')->nullable(); // {line, column}
            $table->json('selection')->nullable();
            $table->string('color', 7); // hex color e.g. #FF5733
            $table->timestamp('last_seen_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['resource_type', 'resource_id']);
            $table->unique(['user_id', 'resource_type', 'resource_id']);
        });

        Schema::create('collaboration_comments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id');
            $table->unsignedBigInteger('thread_id')->nullable();
            $table->unsignedInteger('line_number')->nullable();
            $table->text('body');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['resource_type', 'resource_id']);
            $table->index('thread_id');

            $table->foreign('thread_id')
                ->references('id')
                ->on('collaboration_comments')
                ->nullOnDelete();
        });

        Schema::create('debug_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->unsignedBigInteger('execution_run_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('active');
            $table->json('participants'); // array of user IDs
            $table->json('breakpoints')->nullable();
            $table->timestamps();
            $table->timestamp('ended_at')->nullable();

            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debug_sessions');
        Schema::dropIfExists('collaboration_comments');
        Schema::dropIfExists('presence_sessions');
    }
};
