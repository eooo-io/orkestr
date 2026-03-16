<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_tasks', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->unsignedBigInteger('parent_task_id')->nullable();

            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('medium');
            $table->string('status', 20)->default('pending');

            $table->json('input_data')->nullable();
            $table->json('output_data')->nullable();

            $table->unsignedBigInteger('execution_id')->nullable();
            $table->unsignedBigInteger('assigned_by_user_id')->nullable();
            $table->unsignedBigInteger('assigned_by_agent_id')->nullable();

            $table->timestamps();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // SQLite-safe foreign keys
            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('agent_id')->references('id')->on('agents')->nullOnDelete();
                $table->foreign('parent_task_id')->references('id')->on('agent_tasks')->nullOnDelete();
                $table->foreign('execution_id')->references('id')->on('execution_runs')->nullOnDelete();
                $table->foreign('assigned_by_user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('assigned_by_agent_id')->references('id')->on('agents')->nullOnDelete();
            }

            $table->index(['project_id', 'status']);
            $table->index(['agent_id', 'status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_tasks');
    }
};
