<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_secrets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('type', 20); // api_key, oauth_token, password, certificate, ssh_key, custom
            $table->text('encrypted_value');
            $table->json('metadata')->nullable();
            $table->timestamp('rotated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('vault_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('secret_id')->constrained('vault_secrets')->cascadeOnDelete();
            $table->string('grantee_type', 30); // agent, project, user
            $table->unsignedBigInteger('grantee_id');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['secret_id', 'grantee_type', 'grantee_id']);
        });

        Schema::create('vault_audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('secret_id')->constrained('vault_secrets')->cascadeOnDelete();
            $table->string('action', 30); // accessed, created, updated, rotated, deleted, grant_added, grant_revoked
            $table->string('actor_type', 30); // user, agent, system
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['secret_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_audit_log');
        Schema::dropIfExists('vault_access_grants');
        Schema::dropIfExists('vault_secrets');
    }
};
