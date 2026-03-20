<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_agents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category')->nullable()->index();
            $table->json('tags');
            $table->json('agent_config');
            $table->json('skills_config');
            $table->json('workflow_config')->nullable();
            $table->json('wiring_config')->nullable();
            $table->string('author');
            $table->string('author_url')->nullable();
            $table->string('source')->nullable();
            $table->string('version')->default('1.0.0');
            $table->unsignedInteger('downloads')->default(0);
            $table->unsignedInteger('upvotes')->default(0)->index();
            $table->unsignedInteger('downvotes')->default(0);
            $table->json('screenshots')->nullable();
            $table->text('readme')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            if (DB::getDriverName() !== 'sqlite') {
                $table->fullText(['name', 'description']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_agents');
    }
};
