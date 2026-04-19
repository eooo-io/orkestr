<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_propagations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_skill_id')->constrained('skills')->cascadeOnDelete();
            $table->foreignId('target_project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('target_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('status', 16)->default('suggested'); // suggested, accepted, dismissed, modified
            $table->foreignId('modified_skill_id')->nullable()->constrained('skills')->nullOnDelete();
            $table->decimal('suggestion_score', 5, 2)->default(0);
            $table->timestamp('suggested_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_skill_id', 'target_project_id']);
            $table->index(['target_project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_propagations');
    }
};
