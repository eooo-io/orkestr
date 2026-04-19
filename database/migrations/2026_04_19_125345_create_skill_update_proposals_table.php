<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_update_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->nullable()->constrained()->nullOnDelete(); // null = new-skill proposal
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('rationale')->nullable();
            $table->json('proposed_frontmatter')->nullable();
            $table->longText('proposed_body')->nullable();
            $table->json('evidence_memory_ids')->nullable();
            $table->string('pattern_key', 200);
            $table->string('status', 16)->default('draft'); // draft, accepted, rejected, superseded
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('suppress_until')->nullable();
            $table->timestamps();

            $table->index(['skill_id', 'status']);
            $table->index(['agent_id', 'status']);
            $table->unique(['agent_id', 'pattern_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_update_proposals');
    }
};
