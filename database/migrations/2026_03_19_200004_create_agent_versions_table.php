<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('config_snapshot');
            $table->json('skill_snapshot');
            $table->json('mcp_snapshot');
            $table->json('a2a_snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'version_number']);
        });

        Schema::create('agent_health_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('check_type', 30); // mcp_connectivity, skill_validity, model_availability, credential_access
            $table->string('status', 20); // passed, failed, warning
            $table->json('details')->nullable();
            $table->timestamp('checked_at');

            $table->index(['agent_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_health_checks');
        Schema::dropIfExists('agent_versions');
    }
};
