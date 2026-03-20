<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_peers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->text('base_url');
            $table->string('api_key_hash', 64);
            $table->enum('status', ['pending', 'active', 'suspended', 'revoked'])->default('pending');
            $table->json('capabilities')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->enum('trust_level', ['untrusted', 'basic', 'verified', 'full'])->default('basic');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('federation_delegations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('peer_id')->constrained('federation_peers')->cascadeOnDelete();
            $table->foreignId('local_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('remote_agent_slug');
            $table->enum('direction', ['outbound', 'inbound']);
            $table->enum('status', ['pending', 'active', 'completed', 'failed'])->default('pending');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->unsignedInteger('cost_microcents')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->index(['peer_id', 'direction', 'status']);
        });

        Schema::create('federated_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('peer_id')->constrained('federation_peers')->cascadeOnDelete();
            $table->string('remote_user_id');
            $table->string('remote_email')->nullable();
            $table->string('remote_role')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'peer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federated_identities');
        Schema::dropIfExists('federation_delegations');
        Schema::dropIfExists('federation_peers');
    }
};
