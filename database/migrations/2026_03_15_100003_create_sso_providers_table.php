<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sso_providers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('type'); // saml, oidc
            $table->string('name');
            $table->string('entity_id')->nullable(); // SAML entity ID or OIDC client ID
            $table->text('metadata_url')->nullable(); // SAML metadata URL or OIDC discovery URL
            $table->text('sso_url')->nullable(); // SAML SSO endpoint
            $table->text('slo_url')->nullable(); // SAML SLO endpoint
            $table->text('certificate')->nullable(); // IdP certificate (PEM)
            $table->string('client_id')->nullable(); // OIDC client ID
            $table->text('client_secret')->nullable(); // OIDC client secret (encrypted)
            $table->json('claim_mapping')->nullable(); // Map IdP claims to user attributes
            $table->json('allowed_domains')->nullable(); // Restrict to specific email domains
            $table->boolean('auto_provision')->default(true); // Auto-create users
            $table->string('default_role')->default('member'); // Default org role for new users
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
            $table->unique(['organization_id', 'type']);

            if (config('database.default') !== 'sqlite') {
                $table->foreign('organization_id')
                    ->references('id')
                    ->on('organizations')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_providers');
    }
};
