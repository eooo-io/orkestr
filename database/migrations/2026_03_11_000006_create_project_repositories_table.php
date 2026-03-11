<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // github, gitlab
            $table->string('owner'); // org or user (e.g. "eooo-io")
            $table->string('name'); // repo name (e.g. "agentis-studio")
            $table->string('full_name'); // "eooo-io/agentis-studio"
            $table->string('default_branch')->default('main');
            $table->string('url'); // https://github.com/eooo-io/agentis-studio
            $table->string('clone_url')->nullable(); // https://github.com/eooo-io/agentis-studio.git
            $table->text('access_token')->nullable(); // encrypted PAT or OAuth token
            $table->string('webhook_secret')->nullable(); // for verifying inbound webhooks
            $table->boolean('auto_scan_on_push')->default(true);
            $table->boolean('auto_sync_on_push')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_commit_sha')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_repositories');
    }
};
