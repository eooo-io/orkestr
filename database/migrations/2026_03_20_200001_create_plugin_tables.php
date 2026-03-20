<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugins', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('version', 50);
            $table->string('author')->nullable();
            $table->enum('type', ['tool', 'node', 'panel', 'provider', 'composite']);
            $table->json('manifest');
            $table->string('entry_point');
            $table->json('config')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
        });

        Schema::create('plugin_hooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plugin_id')->constrained()->cascadeOnDelete();
            $table->string('hook_name');
            $table->string('handler');
            $table->integer('priority')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['plugin_id', 'hook_name']);
            $table->index(['hook_name', 'enabled', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_hooks');
        Schema::dropIfExists('plugins');
    }
};
